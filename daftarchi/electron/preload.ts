import { contextBridge, ipcRenderer } from 'electron'
import { IPC, type AppInfo, type SettingsMap } from '../shared/ipc'

const api = {
  ping: () => ipcRenderer.invoke(IPC.ping) as Promise<string>,
  getAppInfo: () => ipcRenderer.invoke(IPC.appInfo) as Promise<AppInfo>,
  getSettings: () => ipcRenderer.invoke(IPC.settingsGetAll) as Promise<SettingsMap>,
  setSetting: (key: string, value: string) =>
    ipcRenderer.invoke(IPC.settingsSet, key, value) as Promise<SettingsMap>,
  setSettings: (entries: SettingsMap) =>
    ipcRenderer.invoke(IPC.settingsSetMany, entries) as Promise<SettingsMap>,
}

contextBridge.exposeInMainWorld('daftarchi', api)
