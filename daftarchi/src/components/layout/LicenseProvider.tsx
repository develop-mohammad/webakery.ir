import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api } from '@/lib/ipc'
import type { LicenseSnapshot } from '../../../shared/license'

type LicenseContextValue = {
  license: LicenseSnapshot | null
  refresh: () => Promise<void>
}

const LicenseContext = createContext<LicenseContextValue | null>(null)

export function LicenseProvider({ children }: { children: ReactNode }) {
  const [license, setLicense] = useState<LicenseSnapshot | null>(null)

  const refresh = useCallback(async () => {
    try {
      setLicense(await api().licenseStatus())
    } catch {
      setLicense(null)
    }
  }, [])

  useEffect(() => {
    refresh().catch(() => undefined)
  }, [refresh])

  const value = useMemo(() => ({ license, refresh }), [license, refresh])
  return <LicenseContext.Provider value={value}>{children}</LicenseContext.Provider>
}

export function useLicense(): LicenseContextValue {
  const ctx = useContext(LicenseContext)
  if (!ctx) throw new Error('useLicense باید داخل LicenseProvider باشد')
  return ctx
}
