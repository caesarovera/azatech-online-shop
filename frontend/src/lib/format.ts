const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 })
const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

export const formatCurrency = (value: string | number): string => currency.format(Number(value))
export const formatDateTime = (value: string | null | undefined): string => (value ? dateTime.format(new Date(value)) : '-')

/** Hitung dalam sen (integer) supaya tidak ada error floating point, sama seperti backend. */
export function toCents(value: string | number): number {
  return Math.round(Number(value) * 100)
}

export function isoDate(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}
