import { existsSync } from 'node:fs'
import { isAbsolute, join } from 'node:path'

export function assertMemberAssets(root, manifest, directory = 'public/build/admin') {
    if (!existsSync(join(root, 'resources/js/member.tsx')) || !existsSync(join(root, 'platform/plugins/member/resources/js/account/Dashboard.tsx'))) return
    const entry = 'resources/js/member.tsx'
    if (!manifest[entry]?.isEntry) throw new Error('Member React assets are missing. Rebuild the admin/member bundle before packaging.')
    const visited = new Set()
    const visit = (key) => {
        if (visited.has(key)) return
        const chunk = Object.hasOwn(manifest, key) && manifest[key]
        if (!chunk || typeof chunk !== 'object') throw new Error(`Missing member asset graph entry: ${key}`)
        visited.add(key)
        for (const file of [chunk.file, ...(chunk.css ?? []), ...(chunk.assets ?? [])]) {
            if (typeof file !== 'string' || isAbsolute(file) || file.includes('\\') || file.split('/').includes('..') || !existsSync(join(root, directory, file))) throw new Error(`Missing or unsafe member graph asset: ${file}`)
        }
        for (const child of [...(chunk.imports ?? []), ...(chunk.dynamicImports ?? [])]) visit(child)
    }
    visit(entry)
}
