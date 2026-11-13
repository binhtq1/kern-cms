import { build } from 'vite'
import vue from '@vitejs/plugin-vue'
import postcss from 'postcss'
import rtlcss from 'rtlcss'
import { existsSync, mkdirSync, readFileSync, realpathSync, watch, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'

const runtime = 'vendor/binhtq1/kern-core'
const managedRuntime = existsSync(runtime) && realpathSync(runtime) !== (existsSync('platform') ? realpathSync('platform') : '')
const sourcePath = path => managedRuntime && /^(core|packages)\//.test(path) ? `${runtime}/${path}` : `platform/${path}`

const packages = [
    ['core/base', {
        css: ['core', 'libraries/select2/select2', 'components/email', 'components/error-pages', 'components/tree-category', 'components/crop-image'],
        js: ['core-ui', 'app', 'core', 'editor', 'tags', 'form/phone-number-field', 'tree-category', 'vue-app', 'repeater-field', 'crop-image'],
    }],
    ['core/table', { css: ['table'], js: ['table', 'filter'] }],
    ['packages/installer', { css: ['style'] }],
    ['packages/revision', { css: ['revision'], js: ['revision'] }],
    ['packages/seo-helper', { css: ['seo-helper'], js: ['seo-helper'] }],
    ['packages/slug', { css: ['slug'], js: ['front-slug'] }],
    ['packages/theme', { css: ['admin-bar', 'guideline'], js: ['toast'] }],
    ['plugins/contact', { css: ['contact-public'], js: ['contact-public'] }],
    ['plugins/cookie-consent', { css: ['cookie-consent'], js: ['cookie-consent'] }],
    ['plugins/custom-field', { css: ['custom-field'], js: ['use-custom-fields'] }],
    ['plugins/gallery', { css: ['gallery', 'object-gallery'], js: ['gallery', 'object-gallery'] }],
    ['plugins/language', { css: ['language-public'], js: ['language-public'] }],
    ['plugins/member', { css: ['dashboard/style', 'dashboard/style-rtl', 'front-auth'], js: ['dashboard/script', 'dashboard/activity-logs'] }],
    ['plugins/social-login', { css: ['social-login'] }],
]

function destinations(packageName, output) {
    return [...(managedRuntime && /^(core|packages)\//.test(packageName) ? [] : [`platform/${packageName}/public/${output}`]), `public/vendor/core/${packageName}/${output}`]
}

let pendingOutput = new Map()

function writeOutput(packageName, output, contents) {
    for (const destination of destinations(packageName, output)) {
        pendingOutput.set(destination, contents)
    }
}

async function compile(packageName, type, source, output) {
    const plugins = [vue()]

    const result = await build({
        configFile: false,
        publicDir: false,
        base: './',
        logLevel: 'error',
        plugins,
        build: {
            write: false,
            minify: 'esbuild',
            cssCodeSplit: false,
            lib: {
                entry: source,
                name: 'KernPublicAsset',
                formats: [type === 'css' ? 'es' : 'iife'],
                fileName: () => 'entry.js',
            },
            rollupOptions: {
                external: ['vue'],
                output: {
                    globals: { vue: 'Vue' },
                    assetFileNames: (asset) => asset.names.some((name) => name.endsWith('.css')) ? 'entry.css' : 'assets/[name]-[hash][extname]',
                },
            },
        },
    })

    const artifacts = (Array.isArray(result) ? result : [result]).flatMap((bundle) => bundle.output)
    const primary = artifacts.find((item) => type === 'css' ? item.type === 'asset' && item.fileName === 'entry.css' : item.type === 'chunk')
    if (!primary) throw new Error(`No ${type} output for ${source}`)
    writeOutput(packageName, output, primary.type === 'chunk' ? primary.code : primary.source)

    for (const item of artifacts) {
        if (item === primary || (type === 'css' && item.type === 'chunk')) continue
        if (type === 'js' && item.type === 'asset' && item.fileName === 'entry.css') {
            writeOutput(packageName, output.replace(/^js\//, 'css/').replace(/\.js$/, '.css'), item.source)
            continue
        }
        if (item.type !== 'asset') throw new Error(`Unexpected output ${item.fileName} for ${source}`)
        writeOutput(packageName, join(dirname(output), item.fileName), item.source)
        if (type === 'js' && artifacts.some((asset) => asset.type === 'asset' && asset.fileName === 'entry.css')) {
            writeOutput(packageName, join(dirname(output).replace(/^js\//, 'css/'), item.fileName), item.source)
        }
    }
    return primary.type === 'asset' ? primary.source.toString() : null
}

async function buildPublicAssets() {
    pendingOutput = new Map()
    for (const [packageName, entries] of packages) {
        for (const name of entries.css ?? []) {
            const cssName = name === 'libraries/select2/select2' ? 'libraries/select2' : name.replace(/^components\//, '')
            const css = await compile(packageName, 'css', sourcePath(`${packageName}/resources/sass/${name}.scss`), `css/${cssName}.css`)
            if (packageName === 'core/base' && ['core', 'libraries/select2/select2'].includes(name)) {
                const rtlPath = name === 'core' ? 'css/core.rtl.css' : 'css/libraries/select2.rtl.css'
                writeOutput(packageName, rtlPath, (await postcss([rtlcss()]).process(css, { from: undefined })).css)
            }
        }
        for (const name of entries.js ?? []) {
            await compile(packageName, 'js', sourcePath(`${packageName}/resources/js/${name}.js`), `js/${name.replace(/^form\//, '')}.js`)
        }
        console.log(`Built ${packageName}`)
    }

    await compile('core/js-validation', 'js', sourcePath('core/js-validation/resources/js/js-validation.js'), 'js/js-validation.js')
    for (const [source, destination] of [
        ['node_modules/vue/dist/vue.global.prod.js', 'libraries/vue.global.min.js'],
    ]) {
        writeOutput('core/base', destination, readFileSync(source))
    }
    for (const [path, contents] of pendingOutput) {
        mkdirSync(dirname(path), { recursive: true })
        writeFileSync(path, contents)
    }
    console.log('Built core/js-validation and shared libraries')
}

await buildPublicAssets()

if (process.argv.includes('--watch')) {
    let timer
    let building = false
    let queued = false
    const rebuild = async () => {
        if (building) { queued = true; return }
        building = true
        try { await buildPublicAssets() } catch (error) { console.error(error) }
        finally {
            building = false
            if (queued) { queued = false; rebuild() }
        }
    }
    for (const directory of [...packages.map(([name]) => sourcePath(`${name}/resources`)), sourcePath('core/js-validation/resources')]) {
        watch(directory, { recursive: true }, (_event, filename) => {
            if (!filename || !/\.(?:js|vue|scss|css)$/.test(filename.toString()) || filename.toString().includes('/admin/')) return
            clearTimeout(timer)
            timer = setTimeout(rebuild, 150)
        })
    }
    console.log('Watching public asset sources')
}
