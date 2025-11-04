import dayjs from 'dayjs';
import 'dayjs/locale/de';

dayjs.locale('de');

export function formatDate(date, format = 'DD.MM.YYYY') {
  return dayjs(date).format(format);
}

export function formatTime(time) {
  return time?.slice(0, 5) || '--:--';
}

export function formatDuration(hours) {
  const h = Math.floor(hours);
  const m = Math.round((hours - h) * 60);
  return `${h}h ${m}m`;
}

export function formatCurrency(amount, currency = 'EUR') {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency,
  }).format(amount);
}

export function formatNumber(number, decimals = 0) {
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(number);
}

export function formatPercentage(value, decimals = 1) {
  return `${formatNumber(value, decimals)}%`;
}

export function getRelativeTime(date) {
  const now = dayjs();
  const target = dayjs(date);
  const diffMinutes = now.diff(target, 'minute');
  
  if (diffMinutes < 1) return 'gerade eben';
  if (diffMinutes < 60) return `vor ${diffMinutes} Min`;
  
  const diffHours = now.diff(target, 'hour');
  if (diffHours < 24) return `vor ${diffHours} Std`;
  
  const diffDays = now.diff(target, 'day');
  if (diffDays < 7) return `vor ${diffDays} Tag${diffDays > 1 ? 'en' : ''}`;
  
  return formatDate(date);
}

export function getInitials(firstName, lastName) {
  const first = firstName?.charAt(0)?.toUpperCase() || '';
  const last = lastName?.charAt(0)?.toUpperCase() || '';
  return `${first}${last}` || 'MA';
}