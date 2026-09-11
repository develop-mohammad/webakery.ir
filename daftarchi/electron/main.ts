import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { app, BrowserWindow, shell } from 'electron'
import { backupDatabase, getAppPaths } from './backup'
import { closeDatabase, openDatabase } from './database/db'
import { registerIpc } from './ipc/index'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const APP_ROOT = path.join(__dirname, '..')
process.env.APP_ROOT = APP_ROOT

let mainWindow: BrowserWindow | null = null

function createWindow(): void {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 960,
    minHeight: 640,
    show: false,
    backgroundColor: '#f4faf9',
    autoHideMenuBar: true,
    title: 'دفترچی',
    webPreferences: {
      preload: fs.existsSync(path.join(__dirname, 'preload.mjs'))
        ? path.join(__dirname, 'preload.mjs')
        : path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  })

  mainWindow.on('ready-to-show', () => {
    mainWindow?.show()
  })

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url)
    return { action: 'deny' }
  })

  if (process.env.VITE_DEV_SERVER_URL) {
    mainWindow.loadURL(process.env.VITE_DEV_SERVER_URL)
  } else {
    mainWindow.loadFile(path.join(APP_ROOT, 'dist', 'index.html'))
  }
}

app.whenReady().then(() => {
  const paths = getAppPaths()
  backupDatabase(paths.dbPath, paths.backups)
  openDatabase(paths.dbPath)
  registerIpc()
  createWindow()

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit()
})

app.on('before-quit', () => {
  closeDatabase()
})
