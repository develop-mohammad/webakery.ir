import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import type { SettingsMap } from '../../../shared/ipc'
import { api } from '@/lib/ipc'

type Theme = 'light' | 'dark'

type SettingsContextValue = {
  settings: SettingsMap
  loading: boolean
  theme: Theme
  setTheme: (theme: Theme) => Promise<void>
  updateSettings: (entries: SettingsMap) => Promise<void>
  refresh: () => Promise<void>
}

const SettingsContext = createContext<SettingsContextValue | null>(null)

function applyTheme(theme: Theme) {
  document.documentElement.classList.toggle('dark', theme === 'dark')
  localStorage.setItem('daftarchi-theme', theme)
}

export function SettingsProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<SettingsMap>({})
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    const next = await api().getSettings()
    setSettings(next)
    const theme = next.theme === 'dark' ? 'dark' : 'light'
    applyTheme(theme)
  }, [])

  useEffect(() => {
    const cached = localStorage.getItem('daftarchi-theme') === 'dark' ? 'dark' : 'light'
    applyTheme(cached)
    refresh().finally(() => setLoading(false))
  }, [refresh])

  const theme: Theme = settings.theme === 'dark' ? 'dark' : 'light'

  const setTheme = useCallback(async (next: Theme) => {
    applyTheme(next)
    const updated = await api().setSetting('theme', next)
    setSettings(updated)
  }, [])

  const updateSettings = useCallback(async (entries: SettingsMap) => {
    const updated = await api().setSettings(entries)
    setSettings(updated)
  }, [])

  const value = useMemo(
    () => ({ settings, loading, theme, setTheme, updateSettings, refresh }),
    [settings, loading, theme, setTheme, updateSettings, refresh],
  )

  return <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>
}

export function useSettings(): SettingsContextValue {
  const ctx = useContext(SettingsContext)
  if (!ctx) throw new Error('useSettings باید داخل SettingsProvider باشد')
  return ctx
}
