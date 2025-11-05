import React, { useState, useEffect, useMemo } from "react";
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import { useToast } from '@/Components/ui/Toast';
import EmployeeSelector from './components/EmployeeSelector';
import PositionShiftCell from './components/PositionShiftCell';
import ShiftTypeDialog from './components/ShiftTypeDialog';
import axios from "axios";
import dayjs from "dayjs";
import 'dayjs/locale/de';
import isBetween from 'dayjs/plugin/isBetween';
import weekOfYear from 'dayjs/plugin/weekOfYear';
import { 
  ChevronLeft, 
  ChevronRight, 
  Download,
  Calendar as CalendarIcon,
  Users,
  ChefHat,
  UtensilsCrossed
} from 'lucide-react';

dayjs.extend(isBetween);
dayjs.extend(weekOfYear);
dayjs.locale('de');

export default function ShiftPlannerPositions() {
  const [weekStart, setWeekStart] = useState(dayjs().startOf("week"));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState('kitchen');
  const [employeeSelectorOpen, setEmployeeSelectorOpen] = useState(false);
  const [selectedShift, setSelectedShift] = useState(null);
  const [selectedPosition, setSelectedPosition] = useState(null);
  const toast = useToast();

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
      toast.error('Fehler beim Laden der Schichten');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [weekStart]);

  const positions = useMemo(() => {
    if (viewMode === 'kitchen') {
      return [
        { id: 'chef', name: 'Küchenchef', icon: '👨‍🍳' },
        { id: 'sous_chef', name: 'Sous Chef', icon: '👨‍🍳' },
        { id: 'cook', name: 'Koch', icon: '🍳' },
        { id: 'prep_cook', name: 'Vorbereitungskoch', icon: '🔪' },
        { id: 'dishwasher', name: 'Spüler', icon: '🧽' },
      ];
    }
    
    if (viewMode === 'service') {
      return [
        { id: 'station_1', name: 'Station 1', icon: '1️⃣' },
        { id: 'station_2', name: 'Station 2', icon: '2️⃣' },
        { id: 'station_3', name: 'Station 3', icon: '3️⃣' },
        { id: 'bar', name: 'Bar', icon: '🍸' },
        { id: 'reception', name: 'Empfang', icon: '🎫' },
      ];
    }
    
    return [];
  }, [viewMode]);

  const days = Array.from({ length: 7 }, (_, i) => weekStart.add(i, "day"));

  const getShiftsForPositionAndDate = (positionId, date) => {
    if (!data?.shifts) return [];
    const dateStr = date.format("YYYY-MM-DD");
    return data.shifts.filter(s => 
      s.position_id === positionId && s.shift_date === dateStr
    );
  };

  const handleAssignEmployee = async (shift) => {
    setSelectedShift(shift);
    setSelectedPosition(positions.find(p => p.id === shift.position_id));
    setEmployeeSelectorOpen(true);
  };

  const handleEmployeeSelected = async (employee) => {
    try {
      await axios.put(`/api/shifts/${selectedShift.id}`, {
        employee_id: employee.id,
      });
      toast.success(`${employee.first_name} ${employee.last_name} zugewiesen`);
      setEmployeeSelectorOpen(false);
      setSelectedShift(null);
      setSelectedPosition(null);
      loadData();
    } catch (error) {
      console.error('Error assigning employee:', error);
      toast.error('Fehler beim Zuweisen des Mitarbeiters');
    }
  };

  const handleRemoveEmployee = async (shift) => {
    if (!confirm('Mitarbeiter-Zuweisung wirklich entfernen?')) return;
    
    try {
      await axios.put(`/api/shifts/${shift.id}`, {
        employee_id: null,
      });
      toast.success('Zuweisung entfernt');
      loadData();
    } catch (error) {
      console.error('Error removing employee:', error);
      toast.error('Fehler beim Entfernen der Zuweisung');
    }
  };

  const handleAddShift = async (positionId, date) => {
    setSelectedPosition(positions.find(p => p.id === positionId));
    // Open shift type dialog
    // For now, we'll just show a toast
    toast.info('Schicht-Erstellung in Entwicklung');
  };

  const goToPreviousWeek = () => setWeekStart(weekStart.subtract(7, "day"));
  const goToNextWeek = () => setWeekStart(weekStart.add(7, "day"));
  const goToCurrentWeek = () => setWeekStart(dayjs().startOf("week"));

  const weekEnd = weekStart.add(6, "day");
  const isCurrentWeek = dayjs().isBetween(weekStart, weekEnd, 'day', '[]');

  const isWeekend = (date) => date.day() === 0 || date.day() === 6;
  const isToday = (date) => date.isSame(dayjs(), 'day');

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="heading-2">Dienstplan - Positionen</h1>
            <p className="text-small mt-1">
              {weekStart.format('DD. MMMM')} - {weekEnd.format('DD. MMMM YYYY')}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" icon={<Download className="w-4 h-4" />}>
              PDF Export
            </Button>
          </div>
        </div>

        {/* View Mode Toggle */}
        <Card glass>
          <CardBody className="p-2">
            <div className="flex items-center gap-1 bg-neutral-100 p-1 rounded-lg">
              <button 
                onClick={() => setViewMode('kitchen')}
                className={`flex-1 px-4 py-2.5 rounded-md transition-all flex items-center justify-center gap-2 font-medium ${
                  viewMode === 'kitchen' 
                    ? 'bg-white shadow-sm text-primary-600' 
                    : 'text-neutral-600 hover:text-neutral-900'
                }`}
              >
                <ChefHat className="w-4 h-4" />
                <span>Küche</span>
              </button>
              
              <button 
                onClick={() => setViewMode('service')}
                className={`flex-1 px-4 py-2.5 rounded-md transition-all flex items-center justify-center gap-2 font-medium ${
                  viewMode === 'service' 
                    ? 'bg-white shadow-sm text-primary-600' 
                    : 'text-neutral-600 hover:text-neutral-900'
                }`}
              >
                <UtensilsCrossed className="w-4 h-4" />
                <span>Service</span>
              </button>
            </div>
          </CardBody>
        </Card>

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

              <div className="flex items-center gap-2">
                <span className="text-sm text-neutral-600">KW {weekStart.week()}</span>
                <Badge variant="primary">{weekStart.year()}</Badge>
              </div>
            </div>
          </CardBody>
        </Card>

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
            ) : (
              <div className="overflow-x-auto custom-scrollbar">
                <table className="w-full border-collapse">
                  <thead className="bg-neutral-50 sticky top-0 z-10">
                    <tr>
                      <th className="px-6 py-4 text-left font-semibold text-neutral-900 border-b-2 border-neutral-200 min-w-[200px]">
                        {viewMode === 'kitchen' ? 'Position' : 'Bereich'}
                      </th>
                      {days.map((d, i) => (
                        <th 
                          key={i} 
                          className={`px-4 py-4 text-center font-semibold border-b-2 border-neutral-200 min-w-[180px] ${
                            isWeekend(d) ? 'bg-neutral-100' : ''
                          } ${isToday(d) ? 'bg-primary-50 border-primary-300' : ''}`}
                        >
                          <div className="flex flex-col items-center gap-1">
                            <span className="text-xs text-neutral-500 uppercase">
                              {d.format("dd")}
                            </span>
                            <span className={`text-lg ${isToday(d) ? 'text-primary-600 font-bold' : 'text-neutral-900'}`}>
                              {d.format("DD")}
                            </span>
                            <span className="text-xs text-neutral-500">
                              {d.format("MMM")}
                            </span>
                          </div>
                        </th>
                      ))}
                    </tr>
                  </thead>

                  <tbody>
                    {positions.map((position) => (
                      <tr 
                        key={position.id}
                        className="border-b border-neutral-200 hover:bg-neutral-50 transition-colors"
                      >
                        <td className="px-6 py-4 border-r border-neutral-200">
                          <div className="flex items-center gap-3">
                            <span className="text-2xl">{position.icon}</span>
                            <div>
                              <p className="font-semibold text-neutral-900">{position.name}</p>
                              <p className="text-xs text-neutral-500">
                                {viewMode === 'kitchen' ? 'Küche' : 'Service'}
                              </p>
                            </div>
                          </div>
                        </td>

                        {days.map((d, i) => {
                          const shifts = getShiftsForPositionAndDate(position.id, d);

                          return (
                            <td 
                              key={i} 
                              className={`px-2 py-2 align-top ${
                                isWeekend(d) ? 'bg-neutral-50' : ''
                              } ${isToday(d) ? 'bg-primary-50/50' : ''}`}
                            >
                              <PositionShiftCell
                                shifts={shifts}
                                onAssignEmployee={handleAssignEmployee}
                                onRemoveEmployee={handleRemoveEmployee}
                                onAddShift={() => handleAddShift(position.id, d)}
                              />
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
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
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded border-2 border-dashed border-warning-300 bg-warning-50"></div>
                <span className="text-sm text-neutral-700">Vakant</span>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Employee Selector Modal */}
      {employeeSelectorOpen && (
        <EmployeeSelector
          employees={data?.employees}
          position={selectedPosition?.name}
          onSelect={handleEmployeeSelected}
          onClose={() => {
            setEmployeeSelectorOpen(false);
            setSelectedShift(null);
            setSelectedPosition(null);
          }}
        />
      )}
    </TimeTrackingLayout>
  );
}