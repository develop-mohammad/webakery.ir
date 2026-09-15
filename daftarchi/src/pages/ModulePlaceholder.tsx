import { PackageOpen } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'

export function ModulePlaceholder({
  title,
  detail,
}: {
  title: string
  detail: string
}) {
  return (
    <Card className="border-dashed">
      <CardContent className="flex flex-col items-center gap-2 py-16 text-center">
        <PackageOpen className="size-8 text-primary/70" />
        <h2 className="text-base font-semibold">{title}</h2>
        <p className="max-w-md text-sm text-muted-foreground">{detail}</p>
      </CardContent>
    </Card>
  )
}
