import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
  type ChartData,
  type ChartOptions,
} from 'chart.js'
import { Bar, Line } from 'react-chartjs-2'
import type { ComparisonSeries, PeakHour } from '../../../shared/models'
import { formatToman } from '@/lib/money'
import { toFaDigits } from '@/lib/jalali'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler)

const CURRENT = '#0F8A86'
const PREVIOUS = '#E8A317'
const PEAK = '#E07A3D'

export function ComparisonChart({ series }: { series: ComparisonSeries }) {
  const data: ChartData<'line'> = {
    labels: series.points.map((p) => toFaDigits(p.label)),
    datasets: [
      {
        label: series.current_label,
        data: series.points.map((p) => p.current),
        borderColor: CURRENT,
        backgroundColor: 'rgba(15,138,134,0.12)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        borderWidth: 2,
      },
      {
        label: series.previous_label,
        data: series.points.map((p) => p.previous),
        borderColor: PREVIOUS,
        backgroundColor: 'transparent',
        borderDash: [5, 4],
        tension: 0.3,
        pointRadius: 3,
        borderWidth: 2,
      },
    ],
  }
  const options: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top', rtl: true, labels: { font: { family: 'Vazirmatn' } } },
      tooltip: {
        rtl: true,
        titleFont: { family: 'Vazirmatn' },
        bodyFont: { family: 'Vazirmatn' },
        callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${formatToman(Number(ctx.raw) || 0)}`,
        },
      },
    },
    scales: {
      x: { ticks: { font: { family: 'Vazirmatn', size: 10 }, maxRotation: 0 } },
      y: {
        ticks: {
          font: { family: 'Vazirmatn', size: 10 },
          callback: (v) => toFaDigits(Number(v).toLocaleString('en-US')),
        },
      },
    },
  }
  return (
    <div className="h-64">
      <Line data={data} options={options} />
    </div>
  )
}

export function PeakHoursChart({ hours }: { hours: PeakHour[] }) {
  const max = Math.max(...hours.map((h) => h.total), 0)
  const data: ChartData<'bar'> = {
    labels: hours.map((h) => toFaDigits(String(h.hour))),
    datasets: [
      {
        label: 'فروش',
        data: hours.map((h) => h.total),
        backgroundColor: hours.map((h) => (h.total === max && max > 0 ? PEAK : CURRENT)),
        borderRadius: 4,
      },
    ],
  }
  const options: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        rtl: true,
        titleFont: { family: 'Vazirmatn' },
        bodyFont: { family: 'Vazirmatn' },
        callbacks: {
          title: (items) => `ساعت ${toFaDigits(items[0]?.label ?? '')}`,
          label: (ctx) => formatToman(Number(ctx.raw) || 0),
        },
      },
    },
    scales: {
      x: { ticks: { font: { family: 'Vazirmatn', size: 10 } } },
      y: {
        ticks: {
          font: { family: 'Vazirmatn', size: 10 },
          callback: (v) => toFaDigits(Number(v).toLocaleString('en-US')),
        },
      },
    },
  }
  const peak = hours.reduce((best, h) => (h.total > best.total ? h : best), hours[0] ?? { hour: 0, total: 0, count: 0, label: '' })
  return (
    <div>
      <div className="h-56">
        <Bar data={data} options={options} />
      </div>
      {max > 0 ? (
        <p className="mt-2 text-center text-xs text-muted-foreground">
          پیک خرید: ساعت {toFaDigits(peak.hour)} — {formatToman(peak.total)}
        </p>
      ) : (
        <p className="mt-2 text-center text-xs text-muted-foreground">در این بازه فروشی ثبت نشده</p>
      )}
    </div>
  )
}
