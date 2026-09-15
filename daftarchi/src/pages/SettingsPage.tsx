import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useSettings } from '@/components/layout/SettingsProvider'
import { api } from '@/lib/ipc'

export function SettingsPage() {
  const { settings, updateSettings } = useSettings()
  const [shopName, setShopName] = useState(settings.shop_name ?? '')
  const [shopPhone, setShopPhone] = useState(settings.shop_phone ?? '')
  const [shopAddress, setShopAddress] = useState(settings.shop_address ?? '')
  const [dbPath, setDbPath] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    setShopName(settings.shop_name ?? '')
    setShopPhone(settings.shop_phone ?? '')
    setShopAddress(settings.shop_address ?? '')
  }, [settings])

  useEffect(() => {
    api()
      .getAppInfo()
      .then((info) => setDbPath(info.dbPath))
      .catch(() => setDbPath(''))
  }, [])

  async function onSave(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    try {
      await updateSettings({
        shop_name: shopName.trim() || 'دفترچی',
        shop_phone: shopPhone.trim(),
        shop_address: shopAddress.trim(),
      })
      toast.success('تنظیمات ذخیره شد')
    } catch {
      toast.error('ذخیره تنظیمات انجام نشد')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={onSave} className="mx-auto max-w-xl space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>مشخصات فروشگاه</CardTitle>
          <CardDescription>این اطلاعات روی فاکتور چاپی نمایش داده می‌شود.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="shop_name">نام فروشگاه</Label>
            <Input
              id="shop_name"
              value={shopName}
              onChange={(e) => setShopName(e.target.value)}
              placeholder="مثلاً فروشگاه گلستان"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop_phone">تلفن</Label>
            <Input
              id="shop_phone"
              dir="ltr"
              className="text-right"
              value={shopPhone}
              onChange={(e) => setShopPhone(e.target.value)}
              placeholder="0912xxxxxxx"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop_address">آدرس</Label>
            <Input
              id="shop_address"
              value={shopAddress}
              onChange={(e) => setShopAddress(e.target.value)}
              placeholder="شهر، خیابان، پلاک"
            />
          </div>
          <Button type="submit" disabled={saving}>
            {saving ? 'در حال ذخیره…' : 'ذخیره'}
          </Button>
        </CardContent>
      </Card>
      {dbPath ? (
        <p className="px-1 text-[11px] text-muted-foreground" dir="ltr">
          دیتابیس: {dbPath}
        </p>
      ) : null}
    </form>
  )
}
