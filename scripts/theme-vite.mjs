import { readdirSync, readFileSync, existsSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import { resolve } from 'node:path'

const [action, selectedTheme] = process.argv.slice(2)

if (!['dev', 'build'].includes(action) || (action === 'dev' && !selectedTheme)) {
    console.error('Usage: npm run dev:theme -- <theme> or npm run build:theme -- [theme]')
    process.exit(1)
}

const themes = selectedTheme ? [selectedTheme] : readdirSync('platform/themes', { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .sort()

for (const theme of themes) {
    if (!/^[a-z0-9_-]+$/i.test(theme)) {
        console.error(`Invalid theme name: ${theme}`)
        process.exit(1)
    }

    const metadataPath = `platform/themes/${theme}/theme.json`
    if (!existsSync(metadataPath)) {
        if (selectedTheme) {
            console.error(`Theme not found: ${theme}`)
            process.exit(1)
        }
        continue
    }

    const metadata = JSON.parse(readFileSync(metadataPath, 'utf8'))
    if (!metadata.vite) {
        if (selectedTheme) {
            console.error(`Theme ${theme} does not declare Vite entrypoints`)
            process.exit(1)
        }
        continue
    }

    const args = [resolve('node_modules/vite/bin/vite.js')]
    if (action === 'build') args.push('build')
    args.push('--config', 'vite.theme.config.mjs')
    const result = spawnSync(process.execPath, args, {
        stdio: 'inherit',
        env: { ...process.env, KERN_THEME: theme },
    })
    if (result.error) throw result.error
    if (result.status !== 0) process.exit(result.status ?? 1)
}
