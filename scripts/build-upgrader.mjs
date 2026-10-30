import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'

const [version, directory] = process.argv.slice(2)
if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version ?? '') || !directory) throw new Error('Usage: build-upgrader.mjs X.Y.Z artifacts-directory')
const artifacts = resolve(directory)
const hashes = Object.fromEntries(['kern-core', 'kern-cms'].map(name => {
    const filename = `${name}-${version}.zip`
    return [filename, createHash('sha256').update(readFileSync(`${artifacts}/${filename}`)).digest('hex')]
}))
// Pin the original published archive, including the stock theme/plugin source omitted from the runtime baseline.
const baseline = '62a4ce5f4059cbb3e183dc6b30cf11a91426a64cec8ccdb1fc563b1a0fe6485b'
const template = readFileSync('kern-upgrade.php', 'utf8')
if (template.split('__KERN_UPGRADE_RELEASE__').length !== 2) throw new Error('Expected exactly one upgrader release placeholder')
const metadata = Buffer.from(JSON.stringify({ version, baseline, hashes })).toString('base64')
writeFileSync(`${artifacts}/kern-upgrade.php`, template.replace('__KERN_UPGRADE_RELEASE__', metadata), { mode: 0o644 })
console.log(`Built standalone upgrader for Kern CMS ${version}`)
