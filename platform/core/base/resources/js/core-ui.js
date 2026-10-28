import { bootstrap } from '@tabler/core'
import setupProgress from './base/progress'

globalThis.bootstrap = bootstrap

setupProgress({
    showSpinner: true,
})
