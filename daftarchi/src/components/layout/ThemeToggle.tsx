import { Moon, Sun } from 'lucide-react'
import { Switch } from '@/components/ui/switch'
import { useSettings } from './SettingsProvider'

export function ThemeToggle() {
  const { theme, setTheme } = useSettings()
  const dark = theme === 'dark'

  return (
    <label className="flex items-center gap-2 text-xs text-muted-foreground">
      <Sun className="size-3.5" />
      <Switch
        checked={dark}
        onCheckedChange={(on) => setTheme(on ? 'dark' : 'light')}
        aria-label="تم تیره"
      />
      <Moon className="size-3.5" />
      <span>{dark ? 'تیره' : 'روشن'}</span>
    </label>
  )
}
