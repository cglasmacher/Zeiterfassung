import React, { useState, useEffect } from "react";
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import axios from "axios";
import dayjs from "dayjs";
import 'dayjs/locale/de';
import ShiftGridMUI from "./components/ShiftGridMUI";
import { 
  ChevronLeft, 
  ChevronRight, 
  Copy, 
  Download,
  Plus,
  Calendar as CalendarIcon,
  Filter,
  Save
} from 'lucide-react';

dayjs.locale('de');

export default function ShiftPlanner() {
  const [weekStart, setWeekStart] = useState(dayjs().startOf("week"));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filterDepartment, setFilterDepartment] = useState('all');

  const loadData = async () => {
    setLoading(true);
    try {
      const res = await axios.get("/api/shifts", {
        params: {
          start: weekStart.format("YYYY-MM-DD"),
          end: weekStart.add(6, "day").format("YYYY-MM-DD"),
        },
      });
      setData(res.data);
    } catch (error) {
      console.error('Error loading shifts:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [weekStart]);

  const goToPreviousWeek = () => setWeekStart(weekStart.subtract(7, "day"));
  const goToNextWeek = () => setWeekStart(weekStart.add(7, "day"));
  const goToCurrentWeek = () => setWeekStart(dayjs().startOf("week"));

  const weekEnd = weekStart.add(6, "day");
  const isCurrentWeek = dayjs().isBetween(weekStart, weekEnd, 'day', '[]');

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header with Actions */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="heading-2">Dienstplan</h1>
            <p className="text-small mt-1">
              {weekStart.format('DD. MMMM')} - {weekEnd.format('DD. MMMM YYYY')}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" icon={<Copy className="w-4 h-4" />}>
              Woche kopieren
            </Button>
            <Button variant="outline" size="sm" icon={<Download className="w-4 h-4" />}>
              PDF Export
            </Button>
            <Button variant="outline" size="sm" icon={<Save className="w-4 h-4" />}>
              Als Vorlage
            </Button>
            <Button variant="primary" size="sm" icon={<Plus className="w-4 h-4" />}>
              Schicht hinzufügen
            </Button>
          </div>
        </div>

        {/* Week Navigation */}
        <Card glass>
          <CardBody className="p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToPreviousWeek}
                  icon={<ChevronLeft className="w-4 h-4" />}
                >
                  Vorherige
                </Button>
                
                <Button
                  variant={isCurrentWeek ? "primary" : "outline"}
                  size="sm"
                  onClick={goToCurrentWeek}
                  icon={<CalendarIcon className="w-4 h-4" />}
                >
                  Diese Woche
                </Button>
                
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={goToNextWeek}
                >
                  Nächste
                  <ChevronRight className="w-4 h-4" />
                </Button>
              </div>

              <div className="flex items-center gap-3">
                <div className="flex items-center gap-2">
                  <Filter className="w-4 h-4 text-neutral-500" />
                  <select 
                    className="input py-1.5 text-sm"
                    value={filterDepartment}
                    onChange={(e) => setFilterDepartment(e.target.value)}
                  >
                    <option value="all">Alle Abteilungen</option>
                    <option value="kitchen">Küche</option>
                    <option value="service">Service</option>
                    <option value="bar">Bar</option>
                  </select>
                </div>

                <div className="flex items-center gap-2 pl-3 border-l border-neutral-200">
                  <span className="text-sm text-neutral-600">KW {weekStart.week()}</span>
                  <Badge variant="primary">{weekStart.year()}</Badge>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Stats Overview */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Geplante Schichten</p>
                  <p className="text-2xl font-bold text-neutral-900">
                    {data?.shifts?.length || 0}
                  </p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                  <CalendarIcon className="w-6 h-6 text-primary-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Gesamtstunden</p>
                  <p className="text-2xl font-bold text-neutral-900">
                    {data?.shifts?.reduce((sum, s) => sum + (s.planned_hours || 8), 0) || 0}h
                  </p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-success-100 flex items-center justify-center">
                  <CalendarIcon className="w-6 h-6 text-success-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Offene Schichten</p>
                  <p className="text-2xl font-bold text-warning-600">3</p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-warning-100 flex items-center justify-center">
                  <CalendarIcon className="w-6 h-6 text-warning-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Konflikte</p>
                  <p className="text-2xl font-bold text-error-600">0</p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-error-100 flex items-center justify-center">
                  <CalendarIcon className="w-6 h-6 text-error-600" />
                </div>
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Shift Grid */}
        <Card>
          <CardBody className="p-0">
            {loading ? (
              <div className="flex items-center justify-center py-20">
                <div className="text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
                  <p className="text-neutral-600">Lade Dienstplan...</p>
                </div>
              </div>
            ) : data ? (
              <ShiftGridMUI {...data} reload={loadData} weekStart={weekStart} />
            ) : (
              <div className="text-center py-20">
                <p className="text-neutral-600">Keine Daten verfügbar</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Legend */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Legende</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="flex flex-wrap gap-4">
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded bg-primary-500"></div>
                <span className="text-sm text-neutral-700">Frühschicht</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded bg-accent-500"></div>
                <span className="text-sm text-neutral-700">Spätschicht</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded bg-secondary-500"></div>
                <span className="text-sm text-neutral-700">Nachtschicht</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded bg-success-500"></div>
                <span className="text-sm text-neutral-700">Sonderschicht</span>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>
    </TimeTrackingLayout>
  );
}