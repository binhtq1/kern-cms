import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { chmodSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve } from 'node:path'
import { test } from 'node:test'

const builder = resolve('scripts/build-upgrader.mjs')
const publisher = resolve('scripts/publish-release.sh')
const hash = contents => createHash('sha256').update(contents).digest('hex')

function fixture(t) {
    const directory = mkdtempSync(join(tmpdir(), 'kern-upgrader-package-'))
    t.after(() => rmSync(directory, { recursive: true, force: true }))
    writeFileSync(join(directory, 'kern-cms-0.3.1.zip'), 'verified starter')
    writeFileSync(join(directory, 'kern-core-0.3.1.zip'), 'verified runtime')
    return directory
}

test('the standalone release asset pins both target archives and the immutable original archive reproducibly', t => {
    const directory = fixture(t)
    const build = () => spawnSync(process.execPath, [builder, '0.3.1', directory], { encoding: 'utf8' })
    let result = build()
    assert.equal(result.status, 0, result.stderr)
    const script = readFileSync(join(directory, 'kern-upgrade.php'), 'utf8')
    const metadata = JSON.parse(Buffer.from(script.match(/private const RELEASE = '([^']+)'/)[1], 'base64').toString())
    assert.equal(metadata.version, '0.3.1')
    assert.equal(metadata.baseline, '62a4ce5f4059cbb3e183dc6b30cf11a91426a64cec8ccdb1fc563b1a0fe6485b')
    assert.deepEqual(metadata.hashes, { 'kern-core-0.3.1.zip': hash('verified runtime'), 'kern-cms-0.3.1.zip': hash('verified starter') })
    assert.ok(!script.includes('__KERN_UPGRADE_RELEASE__'))
    result = build()
    assert.equal(result.status, 0, result.stderr)
    assert.equal(readFileSync(join(directory, 'kern-upgrade.php'), 'utf8'), script)
})

test('invalid release versions or missing archives do not overwrite an existing asset', t => {
    const directory = fixture(t)
    const target = join(directory, 'kern-upgrade.php')
    writeFileSync(target, 'keep existing asset')
    for (const version of ['../0.3.1', '0.3', 'v0.3.1', '0.3.1-beta', '0.3.2']) {
        const result = spawnSync(process.execPath, [builder, version, directory], { encoding: 'utf8' })
        assert.notEqual(result.status, 0)
        assert.equal(readFileSync(target, 'utf8'), 'keep existing asset')
    }
})

test('publishing attaches the one-file upgrader to the starter release and keeps the runtime release separate', t => {
    const directory = fixture(t)
    const bin = join(directory, 'bin')
    mkdirSync(bin)
    const executable = `#!/usr/bin/env node
import { appendFileSync } from 'node:fs'
import { basename } from 'node:path'
const args = process.argv.slice(2)
if (basename(process.argv[1]) === 'git') {
    if (args[0] === 'rev-parse') process.stdout.write('expected-reference\\n')
    if (args.includes('ls-remote')) process.stdout.write('expected-reference\\trefs/tags/v0.3.1\\n')
} else {
    if (args[0] === 'release' && args[1] === 'view') process.exit(1)
    if (args[0] === 'release' && args[1] === 'create') appendFileSync(process.env.KERN_UPGRADER_TRACE, JSON.stringify(args) + '\\n')
}
`
    for (const name of ['git', 'gh']) {
        const path = join(bin, name)
        writeFileSync(path, executable)
        chmodSync(path, 0o755)
    }
    const trace = join(directory, 'trace.jsonl')
    for (const name of ['kern-core', 'kern-cms']) {
        const result = spawnSync('bash', [publisher, name, '0.3.1', directory], { encoding: 'utf8', env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, KERN_UPGRADER_TRACE: trace } })
        assert.equal(result.status, 0, result.stderr)
    }
    const [core, starter] = readFileSync(trace, 'utf8').trim().split('\n').map(line => JSON.parse(line))
    assert.ok(!core.includes(join(directory, 'kern-upgrade.php')))
    assert.ok(starter.includes(join(directory, 'kern-upgrade.php')))
    assert.ok(starter.includes(join(directory, 'SHA256SUMS')))
    assert.ok(starter.some(value => value.includes('php kern-upgrade.php --to=0.3.1 --check')))
})
