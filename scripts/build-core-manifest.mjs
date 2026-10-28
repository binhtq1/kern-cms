import { readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const root = process.argv[2] ?? 'platform'
const paths = ['core', ...readdirSync(join(root, 'packages')).sort().map(name => `packages/${name}`)]
const modules = paths.map(path => [path, JSON.parse(readFileSync(join(root, path, 'composer.json'), 'utf8'))])
const manifest = {
    name: 'binhtq1/kern-core', type: 'library', description: 'Upgradeable Kern CMS runtime', license: 'MIT',
    homepage: 'https://github.com/binhtq1/kern-core', bin: ['bin/migrate-to-core.php'],
    require: { php: '^8.4', 'inertiajs/inertia-laravel': '^3.0' }, replace: {}, autoload: { 'psr-4': {}, files: [] },
    extra: { laravel: { providers: [], aliases: {} } },
}
for (const [path, module] of modules) {
    manifest.replace[module.name] = module.version ?? '1.0.0'
    for (const [name, constraint] of Object.entries(module.require ?? {})) {
        if (name.startsWith('kern/') || name === 'php') continue
        if (manifest.require[name] && manifest.require[name] !== constraint) throw new Error(`Conflicting constraint: ${name}`)
        manifest.require[name] = constraint
    }
    for (const [namespace, directories] of Object.entries(module.autoload?.['psr-4'] ?? {})) {
        manifest.autoload['psr-4'][namespace] = Array.isArray(directories) ? directories.map(dir => `${path}/${dir}`) : `${path}/${directories}`
    }
    for (const file of module.autoload?.files ?? []) manifest.autoload.files.push(`${path}/${file}`)
    manifest.extra.laravel.providers.push(...module.extra?.laravel?.providers ?? [])
    Object.assign(manifest.extra.laravel.aliases, module.extra?.laravel?.aliases ?? {})
}
Object.assign(manifest.extra.laravel.aliases, modules[0][1].extra.laravel.aliases)
writeFileSync(join(root, 'composer.json'), `${JSON.stringify(manifest, null, 4)}\n`)
