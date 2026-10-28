import { execFileSync } from 'node:child_process'
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { dirname, isAbsolute, join, relative, resolve } from 'node:path'

const [version, destination] = process.argv.slice(2)
const root = process.cwd()

function fail(message) {
    throw new Error(message)
}

if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version ?? '') || !destination) {
    fail('Usage: node scripts/prepare-release.mjs <version: X.Y.Z> <new-directory>')
}

const target = resolve(destination)
if (existsSync(target)) fail(`Release directory already exists: ${target}`)
const targetPath = relative(root, target)
if (!targetPath.startsWith(`..`) && !isAbsolute(targetPath) && !targetPath.startsWith('storage/')) {
    fail('Use a release directory outside the checkout or inside storage/.')
}

function checkManifest(directory) {
    const path = join(directory, 'manifest.json')
    if (!existsSync(path)) fail(`Build assets before packaging: missing ${path}`)
    const manifest = JSON.parse(readFileSync(path, 'utf8'))
    if (!Object.keys(manifest).length) fail(`Empty asset manifest: ${path}`)

    for (const entry of Object.values(manifest)) {
        for (const file of [entry.file, ...(entry.css ?? []), ...(entry.assets ?? [])]) {
            if (typeof file !== 'string' || isAbsolute(file) || file.split(/[\\/]/).includes('..')) {
                fail(`Invalid asset path in ${path}`)
            }
            if (!existsSync(join(directory, file))) fail(`Missing built asset: ${join(directory, file)}`)
        }
    }
}

checkManifest('public/build/admin')
const registry = 'public/build/admin/admin-registry.json'
if (!existsSync(registry)) fail(`Missing admin registry: ${registry}`)
JSON.parse(readFileSync(registry, 'utf8'))

const builtDirectories = ['public/build/admin']
for (const theme of readdirSync('platform/themes', { withFileTypes: true })) {
    if (!theme.isDirectory()) continue
    const source = `platform/themes/${theme.name}`
    const metadata = JSON.parse(readFileSync(`${source}/theme.json`, 'utf8'))
    if (!metadata.vite) continue
    for (const directory of [`${source}/public/build`, `public/themes/${theme.name}/build`]) {
        checkManifest(directory)
        builtDirectories.push(directory)
    }
}

const roots = new Set(['app', 'bootstrap', 'config', 'database', 'lang', 'platform', 'public', 'resources', 'routes', 'scripts', 'tests'])
const files = new Set(['.editorconfig', '.gitattributes', '.gitignore', '.npmrc', '.env.example', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'pnpm-lock.yaml', 'pnpm-workspace.yaml', 'phpunit.xml', 'vite.config.ts', 'vite.theme.config.mjs', 'tsconfig.json', 'eslint.config.mjs', 'components.json', 'README.md', 'LICENSE', 'LICENSE.md', 'LICENSE.txt'])
const paths = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean)

function include(path) {
    if (path === '.env.example') return true
    const parts = path.split('/')
    if (parts.some((part) => part === '.env' || part.startsWith('.env.') || ['node_modules', '.git'].includes(part))) return false
    if (parts.includes('vendor') && !path.startsWith('public/vendor/') && !path.startsWith('lang/vendor/')) return false
    if (path.startsWith('bootstrap/cache/') && path !== 'bootstrap/cache/.gitignore') return false
    if (path.startsWith('storage/')) return path.endsWith('/.gitignore')
    if (path.startsWith('database/') && /\.(sqlite(?:-.*)?|sqlite3|db|sql)$/i.test(path)) return false
    if (path === 'public/storage' || path.startsWith('public/storage/')) return false
    if (path.endsWith('.hot') || path.endsWith('.log') || path === 'public/hot' || path.endsWith('.map')) return false
    return files.has(path) || roots.has(parts[0])
}

function copy(path) {
    const source = resolve(root, path)
    if (!existsSync(source)) return
    if (lstatSync(source).isSymbolicLink()) fail(`Release files must not be symlinks: ${path}`)
    const output = join(target, path)
    mkdirSync(dirname(output), { recursive: true })
    cpSync(source, output, { recursive: true, dereference: false, filter: (file) => {
        if (!include(relative(root, file))) return false
        if (lstatSync(file).isSymbolicLink()) fail(`Release files must not be symlinks: ${relative(root, file)}`)
        return true
    } })
}

mkdirSync(target, { recursive: true })
for (const path of paths.filter(include)) copy(path)
for (const directory of builtDirectories) copy(directory)

for (const directory of ['app/public', 'framework/cache/data', 'framework/cache/locks', 'framework/sessions', 'framework/testing', 'framework/views', 'logs']) {
    const path = join(target, 'storage', directory)
    mkdirSync(path, { recursive: true })
    writeFileSync(join(path, '.gitignore'), '*\n!.gitignore\n')
}

console.log(`Prepared binhtq1/kern-cms ${version} in ${target}`)
