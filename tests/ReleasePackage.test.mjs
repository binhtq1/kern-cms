import assert from 'node:assert/strict'
import { execFileSync, spawnSync } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { test } from 'node:test'

const script = resolve('scripts/prepare-release.mjs')

function fixture(t) {
    const directory = mkdtempSync(join(tmpdir(), 'kern-release-'))
    t.after(() => rmSync(directory, { recursive: true, force: true }))
    const root = join(directory, 'source')
    mkdirSync(root)
    execFileSync('git', ['init', '--quiet', root])
    const write = (path, content = '') => {
        mkdirSync(dirname(join(root, path)), { recursive: true })
        writeFileSync(join(root, path), content)
    }

    write('composer.json', '{"name":"binhtq1/kern-cms","type":"project"}\n')
    write('composer.lock', '{"content-hash":"preserve-the-lock"}\n')
    write('.env.example', 'APP_KEY=\n')
    write('.gitignore', '/public/build\n/platform/themes/*/public/build\n/public/themes/*/build\n')
    write('platform/core/composer.json', '{"name":"kern/platform"}')
    write('platform/packages/installer/public/css/style.css', 'installer')
    write('public/vendor/core/packages/installer/css/style.css', 'installer')
    write('platform/themes/example/theme.json', '{"vite":{"entrypoints":["assets/app.js"]}}')
    for (const path of ['public/build/admin', 'platform/themes/example/public/build', 'public/themes/example/build']) {
        write(`${path}/manifest.json`, JSON.stringify({ 'entry.js': { file: 'assets/entry.js', css: ['assets/entry.css'] } }))
        write(`${path}/assets/entry.js`, 'console.log("ready")')
        write(`${path}/assets/entry.css`, 'body{}')
    }
    write('public/build/admin/admin-registry.json', '{"version":1,"packages":{}}')

    const target = join(directory, 'distribution')
    return { root, target, write, run: (version = '0.1.0') => spawnSync(process.execPath, [script, version, target], { cwd: root, encoding: 'utf8' }) }
}

test('the distribution includes ignored build outputs and internal packages without local data', (t) => {
    const { root, target, write, run } = fixture(t)
    for (const path of ['.env', '.env.production', 'platform/core/.env', 'storage/installed', 'storage/installing', 'storage/logs/laravel.log', 'storage/app/public/upload.jpg', 'bootstrap/cache/config.php', 'database/database.sqlite', 'database/dump.sql', 'public/admin.hot', 'public/hot', 'public/themes/example.hot', 'public/storage/upload.jpg', 'node_modules/package/index.js', 'vendor/autoload.php']) {
        write(path, 'local data')
    }
    write('public/build/admin/.env.production', 'ignored local data')
    write('public/build/admin/assets/entry.js.map', 'source map')
    write('lang/vendor/core/base/vi/base.php', '<?php return ["name" => "Tên"];')
    const result = run()
    assert.equal(result.status, 0, result.stderr)
    for (const path of ['.env.example', 'platform/core/composer.json', 'public/build/admin/manifest.json', 'public/build/admin/assets/entry.js', 'public/build/admin/admin-registry.json', 'platform/themes/example/public/build/assets/entry.css', 'public/themes/example/build/assets/entry.js', 'public/vendor/core/packages/installer/css/style.css', 'lang/vendor/core/base/vi/base.php', 'storage/framework/sessions/.gitignore']) {
        assert.ok(existsSync(join(target, path)), path)
    }
    for (const path of ['.env', '.env.production', 'platform/core/.env', 'storage/installed', 'storage/installing', 'storage/logs/laravel.log', 'storage/app/public/upload.jpg', 'bootstrap/cache/config.php', 'database/database.sqlite', 'database/dump.sql', 'public/admin.hot', 'public/hot', 'public/themes/example.hot', 'public/storage', 'node_modules', 'vendor', '.git']) {
        assert.ok(!existsSync(join(target, path)), path)
    }
    assert.ok(!existsSync(join(target, 'public/build/admin/.env.production')))
    assert.ok(!existsSync(join(target, 'public/build/admin/assets/entry.js.map')))
    assert.equal(readFileSync(join(target, 'composer.json'), 'utf8'), readFileSync(join(root, 'composer.json'), 'utf8'))
    assert.equal(readFileSync(join(target, 'composer.lock'), 'utf8'), readFileSync(join(root, 'composer.lock'), 'utf8'))
})

test('packaging rejects a missing asset referenced by a manifest', (t) => {
    const { root, target, run } = fixture(t)
    rmSync(join(root, 'public/build/admin/assets/entry.css'))
    const result = run()
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /Missing built asset/)
    assert.ok(!existsSync(target))
})

test('packaging rejects a missing theme build', (t) => {
    const { root, target, run } = fixture(t)
    rmSync(join(root, 'platform/themes/example/public/build'), { recursive: true })
    const result = run()
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /missing.*manifest.json/)
    assert.ok(!existsSync(target))
})

test('packaging rejects unsafe asset paths', (t) => {
    const { target, write, run } = fixture(t)
    write('public/build/admin/manifest.json', '{"entry":{"file":"../../.env"}}')
    const result = run()
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /Invalid asset path/)
    assert.ok(!existsSync(target))
})

test('packaging rejects symlinks into local files', (t) => {
    const { root, run } = fixture(t)
    symlinkSync(join(root, '.env.example'), join(root, 'public/linked-env'))
    const result = run()
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /must not be symlinks/)
})

test('packaging refuses to overwrite an existing destination', (t) => {
    const { target, run } = fixture(t)
    mkdirSync(target)
    writeFileSync(join(target, 'keep.txt'), 'keep')
    const result = run()
    assert.notEqual(result.status, 0)
    assert.equal(readFileSync(join(target, 'keep.txt'), 'utf8'), 'keep')
})

test('packaging requires a stable semantic version', (t) => {
    const { target, run } = fixture(t)
    for (const version of ['v1.0.0', '1.0', '1.0.0-beta', '../1.0.0', '01.0.0']) {
        const result = run(version)
        assert.notEqual(result.status, 0)
        assert.match(result.stderr, /Usage:/)
    }
    assert.ok(!existsSync(target))
})

test('packaging rejects destinations inside the source files', (t) => {
    const { root } = fixture(t)
    const target = join(root, 'public/build/admin/release')
    const result = spawnSync(process.execPath, [script, '0.1.0', target], { cwd: root, encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /outside the checkout/)
    assert.ok(!existsSync(target))
})
