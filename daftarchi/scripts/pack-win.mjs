import { copyFileSync, existsSync, mkdirSync, unlinkSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import os from 'node:os'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const electronVersion = require('electron/package.json').version
const sqliteDir = path.join('node_modules', 'better-sqlite3')
const releaseDir = path.join(sqliteDir, 'build', 'Release')
const nativeFile = path.join(releaseDir, 'better_sqlite3.node')
const linuxBackup = path.join(os.tmpdir(), 'daftarchi-better_sqlite3.linux.node')
const wantNsis = process.argv.includes('--nsis')

function run(cmd, args, cwd) {
  const result = spawnSync(cmd, args, { stdio: 'inherit', cwd, shell: false })
  if (result.status !== 0) process.exit(result.status ?? 1)
}

mkdirSync(releaseDir, { recursive: true })
if (existsSync(nativeFile)) copyFileSync(nativeFile, linuxBackup)

try {
  run(
    'npx',
    ['prebuild-install', '-r', 'electron', '-t', electronVersion, '-a', 'x64', '--platform', 'win32'],
    sqliteDir,
  )

  run('npx', ['vite', 'build'], process.cwd())

  const targets = wantNsis ? ['zip', 'nsis'] : ['zip']
  run('npx', ['electron-builder', '--win', ...targets, '--x64'], process.cwd())
} finally {
  if (existsSync(linuxBackup)) {
    copyFileSync(linuxBackup, nativeFile)
    unlinkSync(linuxBackup)
  }
}
