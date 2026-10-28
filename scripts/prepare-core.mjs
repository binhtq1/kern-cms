import { execFileSync } from 'node:child_process'
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

const [version, destination] = process.argv.slice(2)
if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version ?? '') || !destination) throw new Error('Usage: prepare-core.mjs X.Y.Z new-directory')
const target = resolve(destination)
if (existsSync(target) || target.startsWith(`${process.cwd()}/platform/`)) throw new Error('Use a new directory outside platform/.')
const manifest = JSON.parse(readFileSync('public/build/admin/manifest.json', 'utf8'))
if (!Object.keys(manifest).length || !existsSync('public/build/admin/admin-registry.json')) throw new Error('Build the admin assets first.')
for (const entry of Object.values(manifest)) {
    for (const file of [entry.file, ...entry.css ?? [], ...entry.assets ?? []]) {
        if (typeof file !== 'string' || file.startsWith('/') || file.split(/[\\/]/).includes('..') || !existsSync(`public/build/admin/${file}`)) throw new Error('Missing or unsafe admin asset')
    }
}
const paths = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], { encoding: 'utf8' }).split('\0')
for (const path of paths) {
    if (!/^(platform\/(core\/|packages\/|bin\/|composer.json)|LICENSE$)/.test(path)) continue
    if (path.split('/').some(part => part === 'vendor' || part === 'node_modules' || part.startsWith('.env')) || path.endsWith('.log')) continue
    if (lstatSync(path).isSymbolicLink()) throw new Error(`Symlink in runtime: ${path}`)
    const output = `${target}/${path.replace(/^platform\//, '')}`
    mkdirSync(dirname(output), { recursive: true })
    cpSync(path, output)
}
cpSync('public/build/admin', `${target}/public/build/admin`, { recursive: true, filter: path => {
    if (lstatSync(path).isSymbolicLink()) throw new Error('Symlink in admin assets')
    return !path.endsWith('.map') && !path.split('/').some(part => part.startsWith('.env'))
} })
for (const path of ['vite.config.ts', 'tsconfig.json', 'resources/js/admin.tsx', 'scripts/public-assets.mjs']) {
    if (existsSync(path)) {
        const output = `${target}/core/base/resources/site-template/${path}`
        mkdirSync(dirname(output), { recursive: true })
        cpSync(path, output)
    }
}
writeFileSync(`${target}/core/base/resources/version.json`, JSON.stringify({ version }) + '\n')
console.log(`Prepared binhtq1/kern-core ${version} in ${target}`)
