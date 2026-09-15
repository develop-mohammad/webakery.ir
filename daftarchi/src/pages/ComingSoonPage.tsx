import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'

export function ComingSoonPage({
  title,
  body,
}: {
  title: string
  body: string
}) {
  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-2">
          <CardTitle className="text-base">{title}</CardTitle>
          <Badge variant="soon">به‌زودی</Badge>
        </div>
        <CardDescription>{body}</CardDescription>
      </CardHeader>
      <CardContent className="text-sm text-muted-foreground">
        این بخش بعد از اتمام فاز ۱ فعال می‌شود. تا آن زمان از منوی اصلی برای کار روزمره استفاده کنید.
      </CardContent>
    </Card>
  )
}
