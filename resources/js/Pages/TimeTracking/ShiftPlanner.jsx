import React, { useState, useEffect, useMemo } from "react";
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import { useToast } from '@/Components/ui/Toast';
import axios from "axios";
import dayjs from "dayjs";
import 'dayjs/locale/de';
import isBetween from 'dayjs/plugin/isBetween';
import weekOfYear from 'dayjs/plugin/weekOfYear';
import ShiftGridMUI from "./components/ShiftGridMUI";
import ShiftCell from "./components/ShiftCell";
import ShiftModal from "./components/ShiftModal";
import { 
  ChevronLeft, 
  ChevronRight, 
  Copy, 
  Download,
  Plus,
  Calendar as CalendarIcon,
  Filter,
  Save,
  Users,
  ChefHat,
  UtensilsCrossed,
  AlertCircle,
  X,
  Trash2
} from 'lucide-react';

dayjs.extend(isBetween);
dayjs.extend(weekOfYear);
dayjs.locale('de');

export default function ShiftPlanner() {
  const [weekStart, setWeekStart] = useState(dayjs().startOf("week"));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filterDepartment, setFilterDepartment] = useState('all');
  const [closedDays, setClosedDays] = useState([]);
  const [viewMode, setViewMode] = useState('shifts'); // 'shifts' only
  const [shiftModalOpen, setShiftModalOpen] = useState(false);
  const [selectedShift, setSelectedShift] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);
  const [copyWeekModalOpen, setCopyWeekModalOpen] = useState(false);
  const [saveTemplateModalOpen, setSaveTemplateModalOpen] = useState(false);
  const [loadTemplateModalOpen, setLoadTemplateModalOpen] = useState(false);
  const [templates, setTemplates] = useState([]);
  const [templateName, setTemplateName] = useState('');
  const [targetWeekStart, setTargetWeekStart] = useState('');
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
      console.log('API Response:', res.data);
      setData(res.data);
      setClosedDays(res.data.closed_days || []);
      
      if (!res.data.employees || res.data.employees.length === 0) {
        toast.error('Keine Mitarbeiter gefunden. Bitte erstellen Sie zuerst Mitarbeiter in den Einstellungen.');
      }
    } catch (error) {
      console.error('Error loading shifts:', error);
      toast.error('Fehler beim Laden der Schichten: ' + (error.response?.data?.message || error.message));
    } finally {
      setLoading(false);
    }
  };

  // Berechne Statistiken
  const stats = useMemo(() => {
    if (!data) return { totalShifts: 0, totalHours: 0, openShifts: 0, conflicts: 0 };

    const totalShifts = data.shifts?.length || 0;
    const totalHours = data.shifts?.reduce((sum, s) => sum + (s.planned_hours || 8), 0) || 0;
    
    // Berechne offene Schichten (Tage ohne Schicht für aktive Mitarbeiter)
    const days = 7;
    const activeEmployees = data.employees?.filter(e => e.active).length || 0;
    const possibleShifts = activeEmployees * days;
    const openShifts = Math.max(0, possibleShifts - totalShifts);
    
    // Berechne Konflikte (Mitarbeiter mit mehreren Schichten am selben Tag)
    const conflicts = data.shifts?.reduce((count, shift) => {
      const sameDay = data.shifts.filter(s => 
        s.employee_id === shift.employee_id && 
        s.shift_date === shift.shift_date &&
        s.id !== shift.id
      );
      return count + (sameDay.length > 0 ? 1 : 0);
    }, 0) || 0;

    return { totalShifts, totalHours, openShifts, conflicts: Math.floor(conflicts / 2) };
  }, [data]);

  // Gruppiere Zeilen basierend auf viewMode
  const groupedRows = useMemo(() => {
    if (!data || !data.employees) return [];
    
    switch(viewMode) {
      case 'employees':
        // Standard: Alle Mitarbeiter als flache Liste
        return data.employees;
        
      case 'kitchen':
        // Gruppiere nach Küchen-Positionen
        const kitchenPositions = [
          { id: 'chef', name: 'Küchenchef', icon: '👨‍🍳', keywords: ['chef', 'küchenchef'] },
          { id: 'sous_chef', name: 'Sous Chef', icon: '👨‍🍳', keywords: ['sous', 'stellvertretend'] },
          { id: 'cook', name: 'Koch', icon: '🍳', keywords: ['koch', 'cook'] },
          { id: 'prep_cook', name: 'Vorbereitungskoch', icon: '🔪', keywords: ['vorbereitung', 'prep'] },
          { id: 'dishwasher', name: 'Spüler', icon: '🧽', keywords: ['spül', 'dish'] },
          { id: 'other_kitchen', name: 'Sonstige Küche', icon: '🍽️', keywords: [] },
        ];
        
        return kitchenPositions.map(pos => ({
          ...pos,
          type: 'position',
          employees: data.employees.filter(e => {
            const position = (e.position || '').toLowerCase();
            if (pos.id === 'other_kitchen') {
              // Alle die in keine andere Kategorie passen
              return !kitchenPositions.slice(0, -1).some(p => 
                p.keywords.some(kw => position.includes(kw))
              );
            }
            return pos.keywords.some(kw => position.includes(kw));
          })
        })).filter(pos => pos.employees.length > 0); // Nur Positionen mit Mitarbeitern
        
      case 'service':
        // Gruppiere nach Service-Bereichen
        const serviceAreas = [
          { id: 'station_1', name: 'Station 1', icon: '1️⃣', keywords: ['station 1', 'bereich 1'] },
          { id: 'station_2', name: 'Station 2', icon: '2️⃣', keywords: ['station 2', 'bereich 2'] },
          { id: 'station_3', name: 'Station 3', icon: '3️⃣', keywords: ['station 3', 'bereich 3'] },
          { id: 'bar', name: 'Bar', icon: '🍸', keywords: ['bar', 'barkeeper', 'bartender'] },
          { id: 'reception', name: 'Empfang', icon: '🎫', keywords: ['empfang', 'host', 'reception'] },
          { id: 'service', name: 'Service Allgemein', icon: '🍽️', keywords: ['service', 'kellner', 'waiter'] },
          { id: 'other_service', name: 'Sonstige Service', icon: '👔', keywords: [] },
        ];
        
        return serviceAreas.map(area => ({
          ...area,
          type: 'area',
          employees: data.employees.filter(e => {
            const position = (e.position || '').toLowerCase();
            if (area.id === 'other_service') {
              // Alle die in keine andere Kategorie passen
              return !serviceAreas.slice(0, -1).some(a => 
                a.keywords.some(kw => position.includes(kw))
              );
            }
            return area.keywords.some(kw => position.includes(kw));
          })
        })).filter(area => area.employees.length > 0); // Nur Bereiche mit Mitarbeitern
        
      default:
        return data.employees;
    }
  }, [data, viewMode]);

  // Filtere Mitarbeiter nach Abteilung
  const filteredData = useMemo(() => {
    if (!data || filterDepartment === 'all') return data;
    
    // Hier würde die echte Filterlogik kommen, wenn Abteilungen in den Daten vorhanden sind
    // Für jetzt geben wir alle Daten zurück
    return data;
  }, [data, filterDepartment]);

  useEffect(() => {
    loadData();
  }, [weekStart]);

  const goToPreviousWeek = () => setWeekStart(weekStart.subtract(7, "day"));
  const goToNextWeek = () => setWeekStart(weekStart.add(7, "day"));
  const goToCurrentWeek = () => setWeekStart(dayjs().startOf("week"));

  const weekEnd = weekStart.add(6, "day");
  const isCurrentWeek = dayjs().isBetween(weekStart, weekEnd, 'day', '[]');

  const handleAddShift = (date) => {
    setSelectedDate(date);
    setSelectedShift(null);
    setShiftModalOpen(true);
  };

  const handleEditShift = (shift) => {
    setSelectedShift(shift);
    setSelectedDate(shift.shift_date);
    setShiftModalOpen(true);
  };

  const handleDeleteShift = async (shift) => {
    if (!confirm('Schicht wirklich löschen?')) return;
    
    try {
      await axios.delete(`/api/shifts/${shift.id}`);
      toast.success('Schicht gelöscht');
      loadData();
    } catch (error) {
      toast.error('Fehler beim Löschen');
    }
  };

  const handleSaveShift = async (formData) => {
    try {
      if (selectedShift && selectedShift.id) {
        await axios.put(`/api/shifts/${selectedShift.id}`, formData);
        toast.success('Schicht aktualisiert');
      } else {
        await axios.post('/api/shifts', formData);
        toast.success('Schicht erstellt');
      }
      setShiftModalOpen(false);
      setSelectedShift(null);
      setSelectedDate(null);
      loadData();
    } catch (error) {
      console.error('Error saving shift:', error);
      toast.error('Fehler beim Speichern');
    }
  };

  const handleCopyWeek = async () => {
    if (!targetWeekStart) {
      toast.error('Bitte wählen Sie eine Zielwoche');
      return;
    }

    try {
      const res = await axios.post('/api/shifts/copy-week', {
        source_start: weekStart.format('YYYY-MM-DD'),
        target_start: targetWeekStart,
      });
      toast.success(res.data.message);
      setCopyWeekModalOpen(false);
      setTargetWeekStart('');
    } catch (error) {
      toast.error('Fehler beim Kopieren der Woche');
    }
  };

  const handleSaveTemplate = async () => {
    if (!templateName) {
      toast.error('Bitte geben Sie einen Namen ein');
      return;
    }

    try {
      const res = await axios.post('/api/shifts/save-template', {
        name: templateName,
        week_start: weekStart.format('YYYY-MM-DD'),
      });
      toast.success(res.data.message);
      setSaveTemplateModalOpen(false);
      setTemplateName('');
      loadTemplates();
    } catch (error) {
      toast.error('Fehler beim Speichern der Vorlage');
    }
  };

  const loadTemplates = async () => {
    try {
      const res = await axios.get('/api/shifts/templates');
      setTemplates(res.data);
    } catch (error) {
      console.error('Error loading templates:', error);
    }
  };

  const handleLoadTemplate = async (templateId) => {
    try {
      const res = await axios.post(`/api/shifts/templates/${templateId}/load`, {
        week_start: weekStart.format('YYYY-MM-DD'),
      });
      toast.success(res.data.message);
      setLoadTemplateModalOpen(false);
      loadData();
    } catch (error) {
      toast.error('Fehler beim Laden der Vorlage');
    }
  };

  const handleDeleteTemplate = async (templateId) => {
    if (!confirm('Vorlage wirklich löschen?')) return;

    try {
      await axios.delete(`/api/shifts/templates/${templateId}`);
      toast.success('Vorlage gelöscht');
      loadTemplates();
    } catch (error) {
      toast.error('Fehler beim Löschen der Vorlage');
    }
  };

  const handleAddShiftGlobal = () => {
    setSelectedDate(weekStart.format('YYYY-MM-DD'));
    setSelectedShift(null);
    setShiftModalOpen(true);
  };

  const handleExportPDF = async () => {
    try {
      toast.info('Export wird vorbereitet...');
      
      const response = await axios.post('/api/shifts/export-pdf', {
        week_start: weekStart.format('YYYY-MM-DD'),
      }, {
        responseType: 'blob',
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `dienstplan_${weekStart.format('YYYY-MM-DD')}.html`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success('Dienstplan exportiert! Öffnen Sie die Datei und drucken Sie als PDF.');
    } catch (error) {
      console.error('Error exporting:', error);
      toast.error('Fehler beim Export');
    }
  };

  useEffect(() => {
    loadTemplates();
  }, []);

  const getShiftsForEmployeeAndDate = (employeeId, date) => {
    if (!data?.shifts) return [];
    const dateStr = dayjs(date).format("YYYY-MM-DD");
    return data.shifts.filter(s => 
      s.employee_id === employeeId && s.shift_date === dateStr
    );
  };

  const getShiftsForDate = (date) => {
    if (!data?.shifts) return [];
    const dateStr = dayjs(date).format("YYYY-MM-DD");
    return data.shifts.filter(s => s.shift_date === dateStr);
  };

  // Filtere geschlossene Tage aus
  const days = Array.from({ length: 7 }, (_, i) => weekStart.add(i, "day"))
    .filter(day => !closedDays.includes(day.day())); // day() gibt 0-6 zurück (0=Sonntag)

  const overloadedEmployees = useMemo(() => {
    if (!data?.employees) return [];
    return data.employees.filter(emp => {
      if (!emp.max_monthly_hours) return false;
      const planned = emp.monthly_planned_hours || 0;
      return planned > emp.max_monthly_hours;
    });
  }, [data]);

  const getShiftsForTypeAndDate = (shiftTypeId, date) => {
    if (!data?.shifts) return [];
    const dateStr = dayjs(date).format("YYYY-MM-DD");
    return data.shifts.filter(s => 
      s.shift_type_id === shiftTypeId && s.shift_date === dateStr
    );
  };

  // Debug: Log data to console
  useEffect(() => {
    if (data) {
      console.log('ShiftPlanner Data:', {
        employees: data.employees?.length || 0,
        shifts: data.shifts?.length || 0,
        shift_types: data.shift_types?.length || 0,
        departments: data.departments?.length || 0,
      });
    }
  }, [data]);

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
            <Button 
              variant="outline" 
              size="sm" 
              icon={<Copy className="w-4 h-4" />}
              onClick={() => setCopyWeekModalOpen(true)}
            >
              Woche kopieren
            </Button>
            <Button 
              variant="outline" 
              size="sm" 
              icon={<Download className="w-4 h-4" />}
              onClick={handleExportPDF}
            >
              PDF Export
            </Button>
            <Button 
              variant="outline" 
              size="sm" 
              icon={<Save className="w-4 h-4" />}
              onClick={() => setSaveTemplateModalOpen(true)}
            >
              Als Vorlage
            </Button>
            <Button 
              variant="outline" 
              size="sm" 
              icon={<Download className="w-4 h-4" />}
              onClick={() => {
                loadTemplates();
                setLoadTemplateModalOpen(true);
              }}
            >
              Vorlage laden
            </Button>
            <Button 
              variant="primary" 
              size="sm" 
              icon={<Plus className="w-4 h-4" />}
              onClick={handleAddShiftGlobal}
            >
              Schicht hinzufügen
            </Button>
          </div>
        </div>

        {/* Warnings */}
        {overloadedEmployees.length > 0 && (
          <div className="p-4 bg-error-50 border border-error-200 rounded-lg flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-error-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold text-error-900">Stundenlimit überschritten</p>
              <p className="text-sm text-error-700 mt-1">
                Folgende Mitarbeiter haben ihr monatliches Stundenlimit überschritten:{' '}
                {overloadedEmployees.map(emp => `${emp.first_name} ${emp.last_name}`).join(', ')}
              </p>
            </div>
          </div>
        )}

        {/* View Mode Toggle */}
        <Card glass>
          <CardBody className="p-2">
            <div className="flex items-center gap-1 bg-neutral-100 p-1 rounded-lg">
              <button 
                onClick={() => setViewMode('shifts')}
                className={`flex-1 px-4 py-2.5 rounded-md transition-all flex items-center justify-center gap-2 font-medium ${
                  viewMode === 'shifts' 
                    ? 'bg-white shadow-sm text-primary-600' 
                    : 'text-neutral-600 hover:text-neutral-900'
                }`}
              >
                <CalendarIcon className="w-4 h-4" />
                <span>Nach Schichttypen</span>
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
                    {stats.totalShifts}
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
                    {stats.totalHours}h
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
                  <p className="text-2xl font-bold text-warning-600">{stats.openShifts}</p>
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
                  <p className="text-2xl font-bold text-error-600">{stats.conflicts}</p>
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
            ) : viewMode === 'shifts' ? (
              data?.shift_types && data.shift_types.length > 0 ? (
                <div className="overflow-x-auto custom-scrollbar">
                  <table className="w-full border-collapse">
                    <thead className="bg-neutral-50 sticky top-0 z-10">
                      <tr>
                        <th className="px-6 py-4 text-left font-semibold text-neutral-900 border-b-2 border-neutral-200 min-w-[200px]">
                          Schichttyp
                        </th>
                        {days.map((d, i) => {
                          const isWeekend = d.day() === 0 || d.day() === 6;
                          const isToday = d.isSame(dayjs(), 'day');
                          return (
                            <th 
                              key={i} 
                              className={`px-4 py-4 text-center font-semibold border-b-2 border-neutral-200 min-w-[180px] ${
                                isWeekend ? 'bg-neutral-100' : ''
                              } ${isToday ? 'bg-primary-50 border-primary-300' : ''}`}
                            >
                              <div className="flex flex-col items-center gap-1">
                                <span className="text-xs text-neutral-500 uppercase">
                                  {d.format("dd")}
                                </span>
                                <span className={`text-lg ${isToday ? 'text-primary-600 font-bold' : 'text-neutral-900'}`}>
                                  {d.format("DD")}
                                </span>
                                <span className="text-xs text-neutral-500">
                                  {d.format("MMM")}
                                </span>
                              </div>
                            </th>
                          );
                        })}
                      </tr>
                    </thead>

                    <tbody>
                      {data.shift_types.filter(st => st.active).map((shiftType) => {
                        const getShiftColor = () => {
                          const name = shiftType.name.toLowerCase();
                          if (name.includes('früh')) return 'bg-primary-100 text-primary-900';
                          if (name.includes('spät')) return 'bg-accent-100 text-accent-900';
                          if (name.includes('nacht')) return 'bg-secondary-100 text-secondary-900';
                          return 'bg-success-100 text-success-900';
                        };

                        return (
                          <tr 
                            key={shiftType.id}
                            className="border-b border-neutral-200 hover:bg-neutral-50 transition-colors"
                          >
                            <td className="px-6 py-4 border-r border-neutral-200">
                              <div className="flex items-center gap-3">
                                <div className={`w-10 h-10 rounded-lg ${getShiftColor()} flex items-center justify-center font-semibold`}>
                                  {shiftType.name.charAt(0)}
                                </div>
                                <div>
                                  <p className="font-semibold text-neutral-900">{shiftType.name}</p>
                                  <p className="text-xs text-neutral-500">
                                    {shiftType.default_start?.substring(0, 5)} - {shiftType.default_end?.substring(0, 5)}
                                  </p>
                                  {shiftType.departments && shiftType.departments.length > 0 && (
                                    <div className="flex flex-wrap gap-1 mt-1">
                                      {shiftType.departments.map(dept => (
                                        <Badge key={dept.id} variant="primary" className="text-xs">
                                          {dept.name}
                                        </Badge>
                                      ))}
                                    </div>
                                  )}
                                </div>
                              </div>
                            </td>

                            {days.map((d, i) => {
                              const shifts = getShiftsForTypeAndDate(shiftType.id, d);
                              const isWeekend = d.day() === 0 || d.day() === 6;
                              const isToday = d.isSame(dayjs(), 'day');

                              return (
                                <td 
                                  key={i} 
                                  className={`px-2 py-2 align-top ${
                                    isWeekend ? 'bg-neutral-50' : ''
                                  } ${isToday ? 'bg-primary-50/50' : ''}`}
                                >
                                  <div className="min-h-[100px] p-2 space-y-1">
                                    {shifts.map((shift) => (
                                      <div
                                        key={shift.id}
                                        className={`p-2 rounded-lg border-2 cursor-pointer hover:shadow-md transition-all ${
                                          shift.employee 
                                            ? getShiftColor() + ' border-transparent'
                                            : 'bg-neutral-50 border-dashed border-neutral-300'
                                        }`}
                                        onClick={() => handleEditShift(shift)}
                                      >
                                        <div className="flex items-start justify-between gap-1">
                                          <div className="flex-1 min-w-0">
                                            {shift.employee ? (
                                              <>
                                                <p className="text-xs font-semibold truncate flex items-center gap-1">
                                                  <Users className="w-3 h-3 flex-shrink-0" />
                                                  {shift.employee.first_name} {shift.employee.last_name[0]}.
                                                </p>
                                                {shift.department && (
                                                  <p className="text-xs text-neutral-600 truncate mt-0.5">
                                                    {shift.department.name}
                                                  </p>
                                                )}
                                              </>
                                            ) : (
                                              <p className="text-xs text-neutral-500 italic">Nicht besetzt</p>
                                            )}
                                            <p className="text-xs text-neutral-600 mt-0.5">
                                              {shift.start_time?.substring(0, 5)} - {shift.end_time?.substring(0, 5)}
                                            </p>
                                          </div>
                                          <button
                                            onClick={(e) => {
                                              e.stopPropagation();
                                              handleDeleteShift(shift);
                                            }}
                                            className="p-1 hover:bg-error-100 rounded transition-colors"
                                          >
                                            <X className="w-3 h-3 text-error-600" />
                                          </button>
                                        </div>
                                      </div>
                                    ))}
                                    
                                    <button
                                      onClick={() => {
                                        setSelectedDate(d.format('YYYY-MM-DD'));
                                        setSelectedShift({ shift_type_id: shiftType.id });
                                        setShiftModalOpen(true);
                                      }}
                                      className="w-full p-2 border-2 border-dashed border-neutral-300 rounded-lg hover:border-primary-400 hover:bg-primary-50 transition-all flex items-center justify-center gap-1 text-neutral-600 hover:text-primary-600"
                                    >
                                      <Plus className="w-4 h-4" />
                                      <span className="text-xs font-medium">Schicht</span>
                                    </button>
                                  </div>
                                </td>
                              );
                            })}
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-20">
                  <CalendarIcon className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                  <p className="text-neutral-600 mb-4">Keine Schichttypen gefunden</p>
                  <p className="text-sm text-neutral-500 mb-4">
                    Bitte erstellen Sie zuerst Schichttypen in den Einstellungen
                  </p>
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => window.location.href = '/timetracking/settings'}
                  >
                    Zu den Einstellungen
                  </Button>
                </div>
              )
            ) : viewMode === 'grid' ? (
              data?.employees && data.employees.length > 0 ? (
                <div className="overflow-x-auto custom-scrollbar">
                  <table className="w-full border-collapse">
                    <thead className="bg-neutral-50 sticky top-0 z-10">
                      <tr>
                        <th className="px-6 py-4 text-left font-semibold text-neutral-900 border-b-2 border-neutral-200 min-w-[200px]">
                          Mitarbeiter
                        </th>
                        {days.map((d, i) => {
                          const isWeekend = d.day() === 0 || d.day() === 6;
                          const isToday = d.isSame(dayjs(), 'day');
                          return (
                            <th 
                              key={i} 
                              className={`px-4 py-4 text-center font-semibold border-b-2 border-neutral-200 min-w-[180px] ${
                                isWeekend ? 'bg-neutral-100' : ''
                              } ${isToday ? 'bg-primary-50 border-primary-300' : ''}`}
                            >
                              <div className="flex flex-col items-center gap-1">
                                <span className="text-xs text-neutral-500 uppercase">
                                  {d.format("dd")}
                                </span>
                                <span className={`text-lg ${isToday ? 'text-primary-600 font-bold' : 'text-neutral-900'}`}>
                                  {d.format("DD")}
                                </span>
                                <span className="text-xs text-neutral-500">
                                  {d.format("MMM")}
                                </span>
                              </div>
                            </th>
                          );
                        })}
                      </tr>
                    </thead>

                    <tbody>
                      {data.employees.map((employee) => {
                      const hourInfo = employee.max_monthly_hours ? {
                        planned: employee.monthly_planned_hours || 0,
                        max: employee.max_monthly_hours,
                        remaining: employee.max_monthly_hours - (employee.monthly_planned_hours || 0),
                        percentage: ((employee.monthly_planned_hours || 0) / employee.max_monthly_hours) * 100,
                      } : null;

                      return (
                        <tr 
                          key={employee.id}
                          className="border-b border-neutral-200 hover:bg-neutral-50 transition-colors"
                        >
                          <td className="px-6 py-4 border-r border-neutral-200">
                            <div className="flex items-center gap-3">
                              <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                                <Users className="w-5 h-5 text-primary-600" />
                              </div>
                              <div>
                                <p className="font-semibold text-neutral-900">
                                  {employee.first_name} {employee.last_name}
                                </p>
                                {hourInfo && (
                                  <div className="flex items-center gap-2 mt-1">
                                    <div className="flex-1 bg-neutral-200 rounded-full h-1.5 w-24">
                                      <div 
                                        className={`h-1.5 rounded-full ${
                                          hourInfo.remaining < 0 
                                            ? 'bg-error-500' 
                                            : hourInfo.remaining < hourInfo.max * 0.2 
                                            ? 'bg-warning-500'
                                            : 'bg-success-500'
                                        }`}
                                        style={{ width: `${Math.min(hourInfo.percentage, 100)}%` }}
                                      />
                                    </div>
                                    <span className={`text-xs font-medium ${
                                      hourInfo.remaining < 0 ? 'text-error-600' : 'text-neutral-600'
                                    }`}>
                                      {hourInfo.planned.toFixed(0)}/{hourInfo.max}h
                                    </span>
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>

                          {days.map((d, i) => {
                            const shifts = getShiftsForEmployeeAndDate(employee.id, d);
                            const isWeekend = d.day() === 0 || d.day() === 6;
                            const isToday = d.isSame(dayjs(), 'day');

                            return (
                              <td 
                                key={i} 
                                className={`px-2 py-2 align-top ${
                                  isWeekend ? 'bg-neutral-50' : ''
                                } ${isToday ? 'bg-primary-50/50' : ''}`}
                              >
                                <ShiftCell
                                  shifts={shifts}
                                  date={d.format('YYYY-MM-DD')}
                                  onAddShift={handleAddShift}
                                  onEditShift={handleEditShift}
                                  onDeleteShift={handleDeleteShift}
                                />
                              </td>
                            );
                          })}
                        </tr>
                      );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-20">
                  <Users className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                  <p className="text-neutral-600 mb-4">Keine Mitarbeiter gefunden</p>
                  <p className="text-sm text-neutral-500 mb-4">
                    Bitte erstellen Sie zuerst Mitarbeiter in den Einstellungen
                  </p>
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => window.location.href = '/timetracking/settings'}
                  >
                    Zu den Einstellungen
                  </Button>
                </div>
              )
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
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded border-2 border-dashed border-neutral-300 bg-neutral-50"></div>
                <span className="text-sm text-neutral-700">Nicht besetzt</span>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Shift Modal */}
      <ShiftModal
        isOpen={shiftModalOpen}
        onClose={() => {
          setShiftModalOpen(false);
          setSelectedShift(null);
          setSelectedDate(null);
        }}
        onSave={handleSaveShift}
        shift={selectedShift}
        date={selectedDate}
        shiftTypes={data?.shift_types || []}
        employees={data?.employees || []}
        departments={data?.departments || []}
      />

      {/* Copy Week Modal */}
      <Modal
        isOpen={copyWeekModalOpen}
        onClose={() => setCopyWeekModalOpen(false)}
        title="Woche kopieren"
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={() => setCopyWeekModalOpen(false)}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleCopyWeek}>
              Kopieren
            </Button>
          </div>
        }
      >
        <div className="space-y-4">
          <div className="p-4 bg-primary-50 rounded-lg">
            <p className="text-sm text-primary-900">
              <strong>Quellwoche:</strong> {weekStart.format('DD.MM.YYYY')} - {weekEnd.format('DD.MM.YYYY')}
            </p>
          </div>
          <Input
            type="date"
            label="Zielwoche (Montag)"
            value={targetWeekStart}
            onChange={(e) => setTargetWeekStart(e.target.value)}
            required
          />
          <p className="text-sm text-neutral-600">
            Alle Schichten der aktuellen Woche werden in die Zielwoche kopiert.
          </p>
        </div>
      </Modal>

      {/* Save Template Modal */}
      <Modal
        isOpen={saveTemplateModalOpen}
        onClose={() => setSaveTemplateModalOpen(false)}
        title="Als Vorlage speichern"
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={() => setSaveTemplateModalOpen(false)}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleSaveTemplate}>
              Speichern
            </Button>
          </div>
        }
      >
        <div className="space-y-4">
          <div className="p-4 bg-primary-50 rounded-lg">
            <p className="text-sm text-primary-900">
              <strong>Aktuelle Woche:</strong> {weekStart.format('DD.MM.YYYY')} - {weekEnd.format('DD.MM.YYYY')}
            </p>
            <p className="text-sm text-primary-700 mt-1">
              {stats.totalShifts} Schichten werden gespeichert
            </p>
          </div>
          <Input
            label="Vorlagenname"
            value={templateName}
            onChange={(e) => setTemplateName(e.target.value)}
            placeholder="z.B. Standard-Woche, Sommerwoche"
            required
          />
        </div>
      </Modal>

      {/* Load Template Modal */}
      <Modal
        isOpen={loadTemplateModalOpen}
        onClose={() => setLoadTemplateModalOpen(false)}
        title="Vorlage laden"
        size="md"
      >
        <div className="space-y-4">
          <div className="p-4 bg-primary-50 rounded-lg">
            <p className="text-sm text-primary-900">
              <strong>Zielwoche:</strong> {weekStart.format('DD.MM.YYYY')} - {weekEnd.format('DD.MM.YYYY')}
            </p>
          </div>
          {templates.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-neutral-600">Keine Vorlagen vorhanden</p>
              <p className="text-sm text-neutral-500 mt-2">
                Speichern Sie zuerst eine Woche als Vorlage
              </p>
            </div>
          ) : (
            <div className="space-y-2">
              {templates.map((template) => (
                <div
                  key={template.id}
                  className="p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-semibold text-neutral-900">{template.name}</p>
                      <p className="text-sm text-neutral-600">
                        {template.template_data?.length || 0} Schichten
                      </p>
                      <p className="text-xs text-neutral-500 mt-1">
                        Erstellt: {dayjs(template.created_at).format('DD.MM.YYYY HH:mm')}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => handleLoadTemplate(template.id)}
                      >
                        Laden
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        icon={<Trash2 className="w-4 h-4" />}
                        onClick={() => handleDeleteTemplate(template.id)}
                        className="text-error-600"
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </Modal>
    </TimeTrackingLayout>
  );
}