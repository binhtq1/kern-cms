import { createHash } from 'node:crypto'
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

export function runtimeAssetPath(path) {
    if (path.startsWith('public/build/admin/')) return path.slice('public/'.length)
    const match = path.match(/^(core|packages)\/([^/]+)\/public\/(.+)$/)
    return match ? `vendor/core/${match[1]}/${match[2]}/${match[3]}` : null
}

export function collectRuntimeAssets(root) {
    const assets = {}
    function visit(directory) {
        if (lstatSync(`${root}/${directory}`).isSymbolicLink()) throw new Error(`Symlink in runtime assets: ${directory}`)
        for (const entry of readdirSync(`${root}/${directory}`, { withFileTypes: true })) {
            const path = `${directory}/${entry.name}`
            if (lstatSync(`${root}/${path}`).isSymbolicLink()) throw new Error(`Symlink in runtime assets: ${path}`)
            if (entry.isDirectory()) visit(path)
            else {
                const key = runtimeAssetPath(path)
                if (key) assets[key] = createHash('sha256').update(readFileSync(`${root}/${path}`)).digest('hex')
            }
        }
    }
    for (const directory of ['core', 'packages']) {
        if (!existsSync(`${root}/${directory}`)) continue
        for (const entry of readdirSync(`${root}/${directory}`, { withFileTypes: true })) {
            if (entry.isDirectory() && existsSync(`${root}/${directory}/${entry.name}/public`)) visit(`${directory}/${entry.name}/public`)
        }
    }
    if (existsSync(`${root}/public/build/admin`)) visit('public/build/admin')
    return Object.fromEntries(Object.entries(assets).sort(([a], [b]) => a.localeCompare(b)))
}

function validate(catalog) {
    if (catalog?.format !== 1 || !catalog.releases || Array.isArray(catalog.releases) || typeof catalog.releases !== 'object') throw new Error('Invalid stock asset catalog')
    for (const [version, assets] of Object.entries(catalog.releases)) {
        if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version) || !assets || typeof assets !== 'object' || Array.isArray(assets)) throw new Error('Invalid stock asset release')
        for (const [path, hash] of Object.entries(assets)) {
            if (!runtimeAssetPath(`public/${path}`) && !/^vendor\/core\/(core|packages)\/[^/]+\/.+/.test(path)) throw new Error(`Invalid stock asset path: ${path}`)
            if (path.includes('\\') || path.split('/').some(part => !part || part === '.' || part === '..') || typeof hash !== 'string' || !/^[a-f0-9]{64}$/.test(hash)) throw new Error(`Invalid stock asset fingerprint: ${path}`)
        }
    }
}

export function mergeStockAssets(current, previous) {
    validate(current)
    validate(previous)
    const releases = { ...previous.releases }
    for (const [version, assets] of Object.entries(current.releases)) {
        const normalized = Object.fromEntries(Object.entries(assets).sort())
        if (releases[version] && JSON.stringify(Object.fromEntries(Object.entries(releases[version]).sort())) !== JSON.stringify(normalized)) throw new Error(`Conflicting stock assets for immutable release ${version}`)
        releases[version] = normalized
    }
    return { format: 1, sources: { ...previous.sources, ...current.sources }, releases: Object.fromEntries(Object.entries(releases).sort(([a], [b]) => a.localeCompare(b, undefined, { numeric: true }))) }
}

export function recordStockAssets(root, version) {
    const path = `${root}/core/base/resources/upgrade/stock-assets.json`
    const history = existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : { format: 1, releases: {} }
    const catalog = mergeStockAssets({ format: 1, releases: { [version]: collectRuntimeAssets(root) } }, history)
    mkdirSync(dirname(path), { recursive: true })
    writeFileSync(path, JSON.stringify(catalog, null, 2) + '\n')
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const [current, previous] = process.argv.slice(2)
    if (!current || !previous) throw new Error('Usage: runtime-asset-manifest.mjs current-catalog previous-catalog')
    const catalog = mergeStockAssets(JSON.parse(readFileSync(current, 'utf8')), JSON.parse(readFileSync(previous, 'utf8')))
    writeFileSync(current, JSON.stringify(catalog, null, 2) + '\n')
}
