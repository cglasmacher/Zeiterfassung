import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle, CardDescription } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { useToast } from '@/Components/ui/Toast';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';
import { 
  Calendar, 
  Clock, 
  Coffee,
  ChevronLeft,
  ChevronRight,
  Download,
  TrendingUp
} from 'lucide-react';

dayjs.locale('de');

export default function DailyOverview() {
  const [date, setDate] = useState(dayjs().format('YYYY-MM-DD'));
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const toast = useToast();

  useEffect(() => {
    loadData();
  }, [date]);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get(`/api/summary/daily/1/${date}`);
      setData([res.data]);
    } catch (error) {
      console.error('Error loading daily summary:', error);
      if (error.response?.status === 404) {
        setError('Keine Daten für diesen Tag vorhanden');
        setData([]);
      } else {
        setError('Fehler beim Laden der Daten');
        toast.error('Fehler beim Laden der Tagesübersicht');
      }
      setData([]);
    } finally {
      setLoading(false);
    }
  };

  const goToPreviousDay = () => setDate(dayjs(date).subtract(1, 'day').format('YYYY-MM-DD'));
  const goToNextDay = () => setDate(dayjs(date).add(1, 'day').format('YYYY-MM-DD'));
  const goToToday = () => setDate(dayjs().format('YYYY-MM-DD'));

  const isToday = dayjs(date).isSame(dayjs(), 'day');

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="heading-2">Tagesübersicht</h1>
            <p className="text-small mt-1">
              {dayjs(date).format('dddd, DD. MMMM YYYY')}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" icon={<Download className="w-4 h-4" />}>
              Export
            </Button>
          </div>
        </div>

        {/* Date Navigation */}
        <Card glass>
          <CardBody className="p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToPreviousDay}
                  icon={<ChevronLeft className="w-4 h-4" />}
                >
                  Vorheriger Tag
                </Button>
                
                <Button
                  variant={isToday ? "primary" : "outline"}
                  size="sm"
                  onClick={goToToday}
                  icon={<Calendar className="w-4 h-4" />}
                >
                  Heute
                </Button>
                
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToNextDay}
                >
                  Nächster Tag
                  <ChevronRight className="w-4 h-4" />
                </Button>
              </div>

              <Input
                type="date"
                value={date}
                onChange={(e) => setDate(e.target.value)}
                className="w-auto"
              />
            </div>
          </CardBody>
        </Card>

        {/* Summary Stats */}
        {data.length > 0 && data[0] && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card>
              <CardBody className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-tiny text-neutral-500 mb-1">Gesamte Arbeitszeit</p>
                    <p className="text-3xl font-bold text-primary-600">
                      {data[0].total_hours || 0}h
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
                      {data[0].total_break_minutes || 0} Min
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
                    <p className="text-tiny text-neutral-500 mb-1">Produktivität</p>
                    <p className="text-3xl font-bold text-success-600">
                      {data[0].total_hours ? Math.round((data[0].total_hours / 8) * 100) : 0}%
                    </p>
                  </div>
                  <div className="w-14 h-14 rounded-xl bg-success-100 flex items-center justify-center">
                    <TrendingUp className="w-7 h-7 text-success-600" />
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>
        )}

        {/* Detailed View */}
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
              <p className="text-neutral-600">Lade Daten...</p>
            </div>
          </div>
        ) : data.length === 0 || error ? (
          <Card>
            <CardBody className="p-12 text-center">
              <Calendar className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
              <p className="text-neutral-600">{error || 'Keine Daten für diesen Tag verfügbar'}</p>
              <p className="text-sm text-neutral-500 mt-2">
                Für {dayjs(date).format('DD.MM.YYYY')} wurden noch keine Zeiteinträge erfasst.
              </p>
            </CardBody>
          </Card>
        ) : (
          <div className="space-y-4">
            {data.map((d, i) => (
              <Card key={i}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-lg">
                        {d.employee?.charAt(0) || 'M'}
                      </div>
                      <div>
                        <CardTitle>{d.employee}</CardTitle>
                        <CardDescription>{d.work_date}</CardDescription>
                      </div>
                    </div>
                    <Badge variant="success">Abgeschlossen</Badge>
                  </div>
                </CardHeader>
                <CardBody>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className="p-4 bg-primary-50 rounded-xl border border-primary-200">
                      <div className="flex items-center gap-2 mb-2">
                        <Clock className="w-4 h-4 text-primary-600" />
                        <p className="text-sm font-medium text-primary-900">Arbeitszeit</p>
                      </div>
                      <p className="text-2xl font-bold text-primary-600">{d.total_hours} Std</p>
                    </div>

                    <div className="p-4 bg-accent-50 rounded-xl border border-accent-200">
                      <div className="flex items-center gap-2 mb-2">
                        <Coffee className="w-4 h-4 text-accent-600" />
                        <p className="text-sm font-medium text-accent-900">Pausen</p>
                      </div>
                      <p className="text-2xl font-bold text-accent-600">{d.total_break_minutes} Min</p>
                    </div>

                    <div className="p-4 bg-success-50 rounded-xl border border-success-200">
                      <div className="flex items-center gap-2 mb-2">
                        <TrendingUp className="w-4 h-4 text-success-600" />
                        <p className="text-sm font-medium text-success-900">Effizienz</p>
                      </div>
                      <p className="text-2xl font-bold text-success-600">
                        {d.total_hours ? Math.round((d.total_hours / 8) * 100) : 0}%
                      </p>
                    </div>

                    <div className="p-4 bg-secondary-50 rounded-xl border border-secondary-200">
                      <div className="flex items-center gap-2 mb-2">
                        <Calendar className="w-4 h-4 text-secondary-600" />
                        <p className="text-sm font-medium text-secondary-900">Status</p>
                      </div>
                      <p className="text-lg font-semibold text-secondary-600">Vollzeit</p>
                    </div>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </div>
    </TimeTrackingLayout>
  );
}