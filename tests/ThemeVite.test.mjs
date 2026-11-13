import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync, existsSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import { basename, join } from 'node:path'

test('a new theme builds and packages its own manifest, assets, and lazy chunks', () => {
    const directory = mkdtempSync('platform/themes/vite-fixture-')
    const theme = basename(directory)
    const published = `public/themes/${theme}`

    try {
        mkdirSync(join(directory, 'assets/js'), { recursive: true })
        mkdirSync(join(directory, 'assets/css'), { recursive: true })
        writeFileSync(join(directory, 'theme.json'), JSON.stringify({
            vite: { entrypoints: ['assets/css/site.css', 'assets/js/site.js'] },
        }))
        writeFileSync(join(directory, 'assets/css/site.css'), 'body { color: red; }')
        writeFileSync(join(directory, 'assets/js/site.js'), "import('./lazy.js').then(console.log)")
        writeFileSync(join(directory, 'assets/js/lazy.js'), "export default 'lazy'")

        const result = spawnSync(process.execPath, ['scripts/theme-vite.mjs', 'build', theme], { encoding: 'utf8' })
        assert.equal(result.status, 0, result.stderr)
        const packagedManifest = join(directory, 'public/build/manifest.json')
        const manifest = JSON.parse(readFileSync(packagedManifest, 'utf8'))
        const entry = manifest[`platform/themes/${theme}/assets/js/site.js`]
        const style = manifest[`platform/themes/${theme}/assets/css/site.css`]
        assert.ok(entry.isEntry)
        assert.ok(style.isEntry)
        assert.equal(entry.dynamicImports.length, 1)
        for (const file of [entry.file, style.file, manifest[entry.dynamicImports[0]].file]) {
            assert.ok(existsSync(join(directory, 'public/build', file)), file)
            assert.ok(existsSync(join(published, 'build', file)), file)
        }
        assert.deepEqual(JSON.parse(readFileSync(join(published, 'build/manifest.json'), 'utf8')), manifest)
    } finally {
        rmSync(directory, { recursive: true, force: true })
        rmSync(published, { recursive: true, force: true })
    }
})
