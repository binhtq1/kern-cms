import { execFileSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { dirname, isAbsolute, join, relative, resolve } from 'node:path'
import { assertMemberAssets } from './assert-member-assets.mjs'

const [version, destination] = process.argv.slice(2)
const root = process.cwd()
const splitRuntime = Number(version?.split('.')[0]) > 0 || Number(version?.split('.')[1]) >= 2

function fail(message) {
    throw new Error(message)
}

if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version ?? '') || !destination) {
    fail('Usage: node scripts/prepare-release.mjs <version: X.Y.Z> <new-directory>')
}

if (splitRuntime && !/^[a-f0-9]{40}$/.test(process.env.CORE_REFERENCE ?? '')) throw new Error('CORE_REFERENCE must identify the verified runtime commit.')

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
assertMemberAssets(root, JSON.parse(readFileSync('public/build/admin/manifest.json', 'utf8')))
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
    if (splitRuntime && /^(platform\/(core\/|packages\/|bin\/|public\/|composer.json)|public\/build\/admin\/)/.test(path)) return false
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
for (const directory of builtDirectories) {
    if (!splitRuntime || directory !== 'public/build/admin') copy(directory)
}

if (splitRuntime) {
    const manifestPath = join(target, 'composer.json')
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
    delete manifest.repositories
    manifest.require['binhtq1/kern-core'] = `^${version}`
    const lockPath = join(target, 'composer.lock')
    const lock = JSON.parse(readFileSync(lockPath, 'utf8'))
    const core = lock.packages.find(pkg => pkg.name === 'binhtq1/kern-core')
    if (!core) fail('Install the split runtime before packaging.')
    core.version = version
    core.source = { type: 'git', url: 'https://github.com/binhtq1/kern-core.git', reference: process.env.CORE_REFERENCE }
    core.dist = { type: 'zip', url: `https://api.github.com/repos/binhtq1/kern-core/zipball/${process.env.CORE_REFERENCE}`, reference: process.env.CORE_REFERENCE, shasum: '' }
    delete core['transport-options']
    const relevant = {}
    for (const key of ['name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'minimum-stability', 'prefer-stable', 'repositories', 'extra'].sort()) {
        if (Object.hasOwn(manifest, key)) relevant[key] = manifest[key]
    }
    if (manifest.config?.platform) relevant.config = { platform: manifest.config.platform }
    const sorted = Object.fromEntries(Object.entries(relevant).sort(([a], [b]) => a.localeCompare(b)))
    const encoded = JSON.stringify(sorted).replace(/\//g, '\\/').replace(/[^\x00-\x7f]/g, char => `\\u${char.charCodeAt(0).toString(16).padStart(4, '0')}`)
    lock['content-hash'] = createHash('md5').update(encoded).digest('hex')
    writeFileSync(manifestPath, JSON.stringify(manifest, null, 4) + '\n')
    writeFileSync(lockPath, JSON.stringify(lock, null, 4) + '\n')
}

for (const directory of ['app/public', 'framework/cache/data', 'framework/cache/locks', 'framework/sessions', 'framework/testing', 'framework/views', 'logs']) {
    const path = join(target, 'storage', directory)
    mkdirSync(path, { recursive: true })
    writeFileSync(join(path, '.gitignore'), '*\n!.gitignore\n')
}

console.log(`Prepared binhtq1/kern-cms ${version} in ${target}`)
