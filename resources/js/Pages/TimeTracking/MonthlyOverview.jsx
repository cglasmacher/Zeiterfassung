import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle, CardDescription } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';
import { 
  Calendar, 
  Clock, 
  Coffee,
  TrendingUp,
  Download,
  ChevronLeft,
  ChevronRight,
  DollarSign,
  CalendarDays
} from 'lucide-react';

dayjs.locale('de');

export default function MonthlyOverview() {
  const [year, setYear] = useState(2025);
  const [month, setMonth] = useState(10);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, [year, month]);

  const loadData = async () => {
    setLoading(true);
    try {
      const res = await axios.get(`/api/summary/monthly/1/${year}/${month}`);
      setSummary(res.data);
    } catch (error) {
      console.error('Error loading monthly summary:', error);
      setSummary(null);
    } finally {
      setLoading(false);
    }
  };

  const goToPreviousMonth = () => {
    if (month === 1) {
      setMonth(12);
      setYear(year - 1);
    } else {
      setMonth(month - 1);
    }
  };

  const goToNextMonth = () => {
    if (month === 12) {
      setMonth(1);
      setYear(year + 1);
    } else {
      setMonth(month + 1);
    }
  };

  const goToCurrentMonth = () => {
    setYear(dayjs().year());
    setMonth(dayjs().month() + 1);
  };

  const isCurrentMonth = year === dayjs().year() && month === dayjs().month() + 1;
  const monthName = dayjs().month(month - 1).format('MMMM');

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="heading-2">Monatsübersicht</h1>
            <p className="text-small mt-1">
              {monthName} {year}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" icon={<Download className="w-4 h-4" />}>
              PDF Export
            </Button>
          </div>
        </div>

        {/* Month Navigation */}
        <Card glass>
          <CardBody className="p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToPreviousMonth}
                  icon={<ChevronLeft className="w-4 h-4" />}
                >
                  Vorheriger
                </Button>
                
                <Button
                  variant={isCurrentMonth ? "primary" : "outline"}
                  size="sm"
                  onClick={goToCurrentMonth}
                  icon={<Calendar className="w-4 h-4" />}
                >
                  Aktueller Monat
                </Button>
                
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToNextMonth}
                >
                  Nächster
                  <ChevronRight className="w-4 h-4" />
                </Button>
              </div>

              <div className="flex items-center gap-2">
                <select 
                  className="input py-2 text-sm"
                  value={month}
                  onChange={(e) => setMonth(parseInt(e.target.value))}
                >
                  {Array.from({ length: 12 }, (_, i) => (
                    <option key={i + 1} value={i + 1}>
                      {dayjs().month(i).format('MMMM')}
                    </option>
                  ))}
                </select>
                
                <input 
                  type="number" 
                  className="input py-2 text-sm w-24"
                  value={year}
                  onChange={(e) => setYear(parseInt(e.target.value))}
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Summary Stats */}
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
              <p className="text-neutral-600">Lade Monatsdaten...</p>
            </div>
          </div>
        ) : summary ? (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <Card>
                <CardBody className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Gesamtstunden</p>
                      <p className="text-3xl font-bold text-primary-600">
                        {summary.total_hours || 0}h
                      </p>
                    </div>
                    <div className="w-14 h-14 rounded-xl bg-primary-100 flex items-center justify-center">
                      <Clock className="w-7 h-7 text-primary-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Pausenzeit</p>
                      <p className="text-3xl font-bold text-accent-600">
                        {summary.total_break_minutes || 0} Min
                      </p>
                    </div>
                    <div className="w-14 h-14 rounded-xl bg-accent-100 flex items-center justify-center">
                      <Coffee className="w-7 h-7 text-accent-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Arbeitstage</p>
                      <p className="text-3xl font-bold text-success-600">
                        {summary.working_days || 0}
                      </p>
                    </div>
                    <div className="w-14 h-14 rounded-xl bg-success-100 flex items-center justify-center">
                      <CalendarDays className="w-7 h-7 text-success-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Ø Stunden/Tag</p>
                      <p className="text-3xl font-bold text-secondary-600">
                        {summary.working_days > 0 
                          ? (Number(summary.total_hours || 0) / summary.working_days).toFixed(1)
                          : 0}h
                      </p>
                    </div>
                    <div className="w-14 h-14 rounded-xl bg-secondary-100 flex items-center justify-center">
                      <TrendingUp className="w-7 h-7 text-secondary-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>
            </div>

            {/* Detailed Summary */}
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-lg">
                      {summary.employee?.charAt(0) || 'M'}
                    </div>
                    <div>
                      <CardTitle>{summary.employee}</CardTitle>
                      <CardDescription>{summary.month_label}</CardDescription>
                    </div>
                  </div>
                  <Badge variant="primary">Monatsbericht</Badge>
                </div>
              </CardHeader>
              <CardBody>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Work Summary */}
                  <div className="space-y-4">
                    <h4 className="font-semibold text-neutral-900 flex items-center gap-2">
                      <Clock className="w-5 h-5 text-primary-500" />
                      Arbeitszeitübersicht
                    </h4>
                    
                    <div className="space-y-3">
                      <div className="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                        <span className="text-sm text-neutral-600">Gesamte Arbeitszeit</span>
                        <span className="font-semibold text-neutral-900">{summary.total_hours}h</span>
                      </div>
                      
                      <div className="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                        <span className="text-sm text-neutral-600">Pausenzeit</span>
                        <span className="font-semibold text-neutral-900">{summary.total_break_minutes} Min</span>
                      </div>
                      
                      <div className="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                        <span className="text-sm text-neutral-600">Arbeitstage</span>
                        <span className="font-semibold text-neutral-900">{summary.working_days} Tage</span>
                      </div>
                      
                      <div className="flex items-center justify-between p-3 bg-primary-50 rounded-lg border border-primary-200">
                        <span className="text-sm font-medium text-primary-900">Durchschnitt/Tag</span>
                        <span className="font-bold text-primary-600">
                          {summary.working_days > 0 
                            ? (Number(summary.total_hours || 0) / summary.working_days).toFixed(1)
                            : 0}h
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Additional Info */}
                  <div className="space-y-4">
                    <h4 className="font-semibold text-neutral-900 flex items-center gap-2">
                      <TrendingUp className="w-5 h-5 text-success-500" />
                      Leistungsmetriken
                    </h4>
                    
                    <div className="space-y-3">
                      <div className="p-4 bg-success-50 rounded-xl border border-success-200">
                        <p className="text-sm text-success-700 mb-1">Produktivität</p>
                        <p className="text-2xl font-bold text-success-600">
                          {summary.total_hours > 0 ? Math.round((summary.total_hours / (summary.working_days * 8)) * 100) : 0}%
                        </p>
                      </div>
                      
                      <div className="p-4 bg-accent-50 rounded-xl border border-accent-200">
                        <p className="text-sm text-accent-700 mb-1">Pausenquote</p>
                        <p className="text-2xl font-bold text-accent-600">
                          {summary.total_hours > 0 
                            ? Math.round((summary.total_break_minutes / (summary.total_hours * 60)) * 100)
                            : 0}%
                        </p>
                      </div>
                      
                      <div className="p-4 bg-secondary-50 rounded-xl border border-secondary-200">
                        <p className="text-sm text-secondary-700 mb-1">Anwesenheit</p>
                        <p className="text-2xl font-bold text-secondary-600">
                          {summary.working_days} / {dayjs(`${year}-${month}-01`).daysInMonth()} Tage
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </CardBody>
            </Card>
          </>
        ) : (
          <Card>
            <CardBody className="p-12 text-center">
              <Calendar className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
              <p className="text-neutral-600">Keine Daten für diesen Monat verfügbar</p>
            </CardBody>
          </Card>
        )}
      </div>
    </TimeTrackingLayout>
  );
}