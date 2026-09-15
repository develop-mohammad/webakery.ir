/// <reference types="vite/client" />
import type { DaftarchiApi } from '../../shared/ipc'

declare global {
  interface Window {
    daftarchi?: DaftarchiApi
  }
}

export {}
