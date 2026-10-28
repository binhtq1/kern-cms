import assert from 'node:assert/strict'
import { execFileSync, spawnSync } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, symlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { test } from 'node:test'

function fixture(t) {
    const directory = mkdtempSync(join(tmpdir(), 'kern-core-package-'))
    const root = join(directory, 'source')
    mkdirSync(root)
    execFileSync('git', ['init', '--quiet', root])
    const write = (path, content = '') => {
        mkdirSync(dirname(join(root, path)), { recursive: true })
        writeFileSync(join(root, path), content)
    }
    write('.gitignore', '/public/build\n/platform/themes/*/public/build\n/public/themes/*/build\n')
    write('LICENSE', 'MIT')
    write('composer.json', JSON.stringify({ name: 'binhtq1/kern-cms', type: 'project', require: { 'binhtq1/kern-core': '^0.2.0' }, repositories: [{ type: 'path', url: 'platform' }] }))
    write('composer.lock', JSON.stringify({ packages: [{ name: 'binhtq1/kern-core', version: '0.2.0', dist: { type: 'path', url: 'platform' }, 'transport-options': { symlink: true } }] }))
    write('platform/composer.json', '{"name":"binhtq1/kern-core","type":"library"}')
    write('platform/core/composer.json', '{"name":"kern/platform"}')
    write('platform/core/base/resources/keep.json', '{}')
    write('platform/packages/installer/public/style.css', 'installer')
    write('platform/bin/migrate-to-core.php', '<?php')
    write('platform/themes/example/theme.json', '{"vite":{}}')
    write('platform/plugins/custom/plugin.json', '{"name":"Customer plugin"}')
    for (const path of ['public/build/admin', 'platform/themes/example/public/build', 'public/themes/example/build']) {
        write(`${path}/manifest.json`, '{"entry":{"file":"assets/entry.js"}}')
        write(`${path}/assets/entry.js`, 'runtime')
    }
    write('public/build/admin/admin-registry.json', '{"version":1,"packages":{}}')
    const target = join(directory, 'distribution')
    return { root, target, write }
}

const coreScript = resolve('scripts/prepare-core.mjs')
const starterScript = resolve('scripts/prepare-release.mjs')

test('core archive owns runtime code and admin assets and excludes website customization and secrets', t => {
    const { root, target, write } = fixture(t)
    write('platform/core/.env', 'secret')
    write('platform/packages/installer/.env.local', 'secret')
    write('storage/app/public/upload.jpg', 'customer upload')
    write('public/build/admin/assets/entry.js.map', 'source map')
    const result = spawnSync(process.execPath, [coreScript, '0.2.0', target], { cwd: root, encoding: 'utf8' })
    assert.equal(result.status, 0, result.stderr)
    for (const path of ['composer.json', 'LICENSE', 'core/composer.json', 'packages/installer/public/style.css', 'public/build/admin/manifest.json', 'bin/migrate-to-core.php']) assert.ok(existsSync(join(target, path)), path)
    for (const path of ['core/.env', 'packages/installer/.env.local', 'plugins', 'themes', 'storage', 'public/build/admin/assets/entry.js.map']) assert.ok(!existsSync(join(target, path)), path)
    assert.equal(JSON.parse(readFileSync(join(target, 'core/base/resources/version.json'))).version, '0.2.0')
})

test('starter archive depends on the immutable core commit and retains site-owned themes and plugins', t => {
    const { root, target } = fixture(t)
    const reference = 'a'.repeat(40)
    const result = spawnSync(process.execPath, [starterScript, '0.2.0', target], { cwd: root, encoding: 'utf8', env: { ...process.env, CORE_REFERENCE: reference } })
    assert.equal(result.status, 0, result.stderr)
    for (const path of ['platform/core', 'platform/packages', 'platform/composer.json', 'public/build/admin']) assert.ok(!existsSync(join(target, path)), path)
    for (const path of ['platform/themes/example/theme.json', 'platform/plugins/custom/plugin.json', 'public/themes/example/build/manifest.json']) assert.ok(existsSync(join(target, path)), path)
    const manifest = JSON.parse(readFileSync(join(target, 'composer.json')))
    const lock = JSON.parse(readFileSync(join(target, 'composer.lock')))
    assert.equal(manifest.repositories, undefined)
    assert.equal(manifest.require['binhtq1/kern-core'], '^0.2.0')
    assert.equal(lock.packages[0].dist.type, 'zip')
    assert.equal(lock.packages[0].source.reference, reference)
    assert.equal(lock.packages[0]['transport-options'], undefined)
    assert.match(lock['content-hash'], /^[a-f0-9]{32}$/)
})

test('core packaging rejects symlinks rather than following files outside the checkout', t => {
    const { root, target } = fixture(t)
    symlinkSync(join(root, 'LICENSE'), join(root, 'platform/core/linked-file'))
    const result = spawnSync(process.execPath, [coreScript, '0.2.0', target], { cwd: root, encoding: 'utf8' })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /Symlink/)
})

test('starter packaging requires the exact verified runtime reference', t => {
    const { root, target } = fixture(t)
    const result = spawnSync(process.execPath, [starterScript, '0.2.0', target], { cwd: root, encoding: 'utf8', env: { ...process.env, CORE_REFERENCE: '' } })
    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /CORE_REFERENCE/)
    assert.ok(!existsSync(target))
})

test('aggregate runtime has no unresolved internal Composer dependencies', () => {
    const manifest = JSON.parse(readFileSync('platform/composer.json'))
    assert.equal(manifest.name, 'binhtq1/kern-core')
    assert.equal(manifest.type, 'library')
    assert.equal(manifest.require['inertiajs/inertia-laravel'], '^3.0')
    assert.ok(!Object.keys(manifest.require).some(name => name.startsWith('kern/')))
    assert.ok(manifest.replace['kern/platform'])
    assert.ok(manifest.autoload['psr-4']['Kern\\Base\\'])
    assert.ok(manifest.autoload.files.includes('packages/form-builder/helpers/helpers.php'))
    assert.ok(manifest.extra.laravel.providers.includes('Kern\\Base\\Providers\\CommandServiceProvider'))
})
