import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { globSync } from 'glob'
import { test } from 'node:test'
import { assertMemberAssets } from '../scripts/assert-member-assets.mjs'

const read = (path) => readFileSync(path, 'utf8')
const withoutComments = (source) => source.replace(/\/\*[\s\S]*?\*\/|<!--[\s\S]*?-->|^[\t ]*\/\/[^\r\n]*/gm, '')

test('the installation dependency graph does not include jQuery', () => {
    const manifest = JSON.parse(read('package.json'))
    for (const group of ['dependencies', 'devDependencies', 'optionalDependencies']) {
        assert.equal(manifest[group]?.jquery, undefined)
        assert.equal(manifest[group]?.['jquery-ui'], undefined)
    }
    const lock = JSON.parse(read('package-lock.json'))
    assert.deepEqual(Object.keys(lock.packages).filter((path) => /(?:^|\/)node_modules\/(?:jquery|jquery-ui)(?:\/|$)/.test(path)), [])
})

test('first-party public and React sources do not call the retired DOM library', () => {
    const paths = globSync([
        'resources/js/**/*.{js,ts,tsx}',
        'platform/**/resources/js/**/*.{js,ts,tsx,vue}',
        'platform/**/resources/views/**/*.blade.php',
        'platform/themes/*/assets/**/*.js',
        'platform/themes/*/config.php',
    ]).filter((path) => !path.includes('.test.'))
    assert.ok(paths.length > 100)
    for (const path of paths) {
        assert.doesNotMatch(withoutComments(read(path)), /\bjQuery\s*[.(]|(?<![\w$])\$\s*(?:\(|\.(?:ajax|ajaxSetup|get|post|extend|fn|validator)\b)/, path)
    }
})

test('published distributions cannot reintroduce the jQuery runtime or hard-dependent plugins', () => {
    assert.deepEqual(globSync(['platform/**/public/**/*jquery*.{js,css}', 'platform/**/public/**/*jquery*/**/*.{js,css}', 'public/vendor/core/**/*jquery*.{js,css}', 'public/vendor/core/**/*jquery*/**/*.{js,css}', 'public/themes/**/*jquery*.{js,css}', 'public/themes/**/*jquery*/**/*.{js,css}'], { nocase: true, nodir: true }), [])
    for (const root of ['platform/core/base/public/libraries', 'public/vendor/core/core/base/libraries']) {
        assert.deepEqual(globSync(`${root}/{datatables,select2,morris,toastr,fancybox,spectrum,bootstrap3-editable}/**/*.js`), [])
    }
    for (const path of globSync(['public/build/admin/assets/*.js', 'public/themes/*/build/assets/*.js', 'platform/**/public/js/**/*.js'])) {
        // Minifiers can legitimately name a local function `$`; check the actual library signature instead.
        assert.doesNotMatch(read(path), /jQuery (?:JavaScript Library|v\d)|jquery\.org\/license/, path)
    }
    assert.doesNotMatch(read('platform/core/base/config/assets.php'), /libraries\/(?:jquery|datatables|select2|morris|toastr|fancybox|spectrum|bootstrap3-editable)[/.\-]/)
})

test('the distributed member UI includes its complete entry and dependency graph', () => {
    assertMemberAssets(process.cwd(), JSON.parse(read('public/build/admin/manifest.json')))
})
