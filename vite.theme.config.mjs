import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { cpSync, existsSync, readFileSync, rmSync } from 'node:fs'

const theme = process.env.KERN_THEME
if (!theme || !/^[a-z0-9_-]+$/i.test(theme)) throw new Error('Invalid KERN_THEME')

const source = `platform/themes/${theme}`
const metadata = JSON.parse(readFileSync(`${source}/theme.json`, 'utf8'))
const entries = metadata.vite?.entrypoints
if (!Array.isArray(entries) || !entries.length || entries.some((entry) =>
    typeof entry !== 'string' || !/^assets\/(?!.*(?:^|\/)\.\.?\/)[a-zA-Z0-9_./-]+\.(?:js|ts|jsx|tsx|css|scss|sass)$/.test(entry) || !existsSync(`${source}/${entry}`),
)) throw new Error(`Theme ${theme} has invalid Vite entrypoints`)

const buildDirectory = `themes/${theme}/build`

export default defineConfig(({ command }) => ({
    base: command === 'build' ? './' : undefined,
    plugins: [
        tailwindcss(),
        laravel({
            input: entries.map((entry) => `${source}/${entry}`),
            buildDirectory,
            hotFile: `public/themes/${theme}.hot`,
            refresh: [`${source}/**/*.blade.php`],
        }),
        {
            name: 'package-theme-assets',
            writeBundle() {
                if (command !== 'build') return
                const target = `${source}/public/build`
                rmSync(target, { recursive: true, force: true })
                cpSync(`public/${buildDirectory}`, target, { recursive: true })
            },
        },
    ],
}))
