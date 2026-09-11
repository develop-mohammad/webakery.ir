import type { AppInfo, DaftarchiApi, SettingsMap } from '../../shared/ipc'

const fallback: DaftarchiApi = {
  ping: async () => 'offline',
  getAppInfo: async (): Promise<AppInfo> => ({
    version: '1.0.0',
    dbPath: '',
    backupsDir: '',
    packaged: false,
  }),
  getSettings: async (): Promise<SettingsMap> => ({
    shop_name: 'دفترچی',
    theme: localStorage.getItem('daftarchi-theme') || 'light',
  }),
  setSetting: async (key, value) => {
    if (key === 'theme') localStorage.setItem('daftarchi-theme', value)
    return fallback.getSettings()
  },
  setSettings: async (entries) => {
    if (entries.theme) localStorage.setItem('daftarchi-theme', entries.theme)
    return fallback.getSettings()
  },
}

export function api(): DaftarchiApi {
  return window.daftarchi ?? fallback
}

export type { DaftarchiApi, SettingsMap, AppInfo }
