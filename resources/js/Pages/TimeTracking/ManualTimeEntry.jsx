import React, { useState, useEffect } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import { useToast } from '@/Components/ui/Toast';
import { 
  Clock, 
  LogIn, 
  LogOut, 
  User, 
  Euro,
  Calendar,
  Timer,
  Coffee,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';

dayjs.locale('de');

export default function ManualTimeEntry() {
  const [employees, setEmployees] = useState([]);
  const [openEntries, setOpenEntries] = useState([]);
  const [todayEntries, setTodayEntries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [clockInModalOpen, setClockInModalOpen] = useState(false);
  const [clockOutModalOpen, setClockOutModalOpen] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [selectedEntry, setSelectedEntry] = useState(null);
  const [clockInData, setClockInData] = useState({
    employee_id: '',
    clock_in: dayjs().format('YYYY-MM-DD HH:mm:ss'),
  });
  const [clockOutData, setClockOutData] = useState({
    clock_out: dayjs().format('YYYY-MM-DD HH:mm:ss'),
    break_minutes: '',
  });
  const [errors, setErrors] = useState({});
  const toast = useToast();

  useEffect(() => {
    loadData();
    const interval = setInterval(loadData, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [employeesRes, openRes, todayRes] = await Promise.all([
        axios.get('/api/employees'),
        axios.get('/api/manual-time/open-entries'),
        axios.get('/api/manual-time/today-entries'),
      ]);
      setEmployees(employeesRes.data.filter(e => e.active));
      setOpenEntries(openRes.data);
      setTodayEntries(todayRes.data);
    } catch (error) {
      console.error('Error loading data:', error);
      toast.error('Fehler beim Laden der Daten');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenClockInModal = () => {
    setClockInData({
      employee_id: '',
      clock_in: dayjs().format('YYYY-MM-DD HH:mm:ss'),
    });
    setErrors({});
    setClockInModalOpen(true);
  };

  const handleOpenClockOutModal = (entry) => {
    setSelectedEntry(entry);
    setSelectedEmployee(entry.employee);
    setClockOutData({
      clock_out: dayjs().format('YYYY-MM-DD HH:mm:ss'),
      break_minutes: '',
    });
    setErrors({});
    setClockOutModalOpen(true);
  };

  const handleClockIn = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      const res = await axios.post('/api/manual-time/clock-in', clockInData);
      toast.success(res.data.message);
      setClockInModalOpen(false);
      loadData();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else if (error.response?.data?.error) {
        toast.error(error.response.data.error);
      } else {
        toast.error('Fehler beim Einstempeln');
      }
    }
  };

  const handleClockOut = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      const res = await axios.post('/api/manual-time/clock-out', {
        employee_id: selectedEntry.employee_id,
        ...clockOutData,
      });
      
      const calc = res.data.calculation;
      toast.success(
        <div>
          <p className="font-semibold">{res.data.message}</p>
          <p className="text-sm mt-1">
            Arbeitszeit: {calc.work_hours}h | Lohn: {calc.gross_wage.toFixed(2)}€
          </p>
        </div>
      );
      
      setClockOutModalOpen(false);
      setSelectedEntry(null);
      setSelectedEmployee(null);
      loadData();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else if (error.response?.data?.error) {
        toast.error(error.response.data.error);
      } else {
        toast.error('Fehler beim Ausstempeln');
      }
    }
  };

  const calculateDuration = (clockIn) => {
    const start = dayjs(clockIn);
    const now = dayjs();
    const minutes = now.diff(start, 'minute');
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
              <Clock className="w-6 h-6 text-primary-600" />
            </div>
            <div>
              <h1 className="heading-2">Manuelle Zeiterfassung</h1>
              <p className="text-small mt-1">
                Ein- und Ausstempeln mit automatischer Lohnberechnung
              </p>
            </div>
          </div>
          <Button
            variant="primary"
            size="md"
            icon={<LogIn className="w-5 h-5" />}
            onClick={handleOpenClockInModal}
          >
            Mitarbeiter einstempeln
          </Button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Aktuell eingestempelt</p>
                  <p className="text-3xl font-bold text-success-600">{openEntries.length}</p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-success-100 flex items-center justify-center">
                  <CheckCircle2 className="w-6 h-6 text-success-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Heute eingetragen</p>
                  <p className="text-3xl font-bold text-primary-600">{todayEntries.length}</p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                  <Calendar className="w-6 h-6 text-primary-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Aktive Mitarbeiter</p>
                  <p className="text-3xl font-bold text-secondary-600">{employees.length}</p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-secondary-100 flex items-center justify-center">
                  <User className="w-6 h-6 text-secondary-600" />
                </div>
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Open Entries */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Timer className="w-5 h-5 text-success-600" />
              Aktuell eingestempelte Mitarbeiter
            </CardTitle>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
              </div>
            ) : openEntries.length === 0 ? (
              <div className="text-center py-12">
                <AlertCircle className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                <p className="text-neutral-600">Keine eingestempelten Mitarbeiter</p>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {openEntries.map((entry) => (
                  <div
                    key={entry.id}
                    className="p-4 border-2 border-success-200 bg-success-50 rounded-xl"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-success-600 flex items-center justify-center text-white font-semibold">
                          {entry.employee.first_name[0]}{entry.employee.last_name[0]}
                        </div>
                        <div>
                          <p className="font-semibold text-neutral-900">
                            {entry.employee.first_name} {entry.employee.last_name}
                          </p>
                          <Badge 
                            variant={entry.employee.employment_type === 'permanent' ? 'primary' : 'secondary'}
                            className="text-xs mt-1"
                          >
                            {entry.employee.employment_type === 'permanent' ? 'Festangestellt' : 'Aushilfe'}
                          </Badge>
                        </div>
                      </div>
                    </div>
                    <div className="space-y-2 mb-3">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Eingestempelt:</span>
                        <span className="font-medium text-neutral-900">
                          {dayjs(entry.clock_in).format('HH:mm')} Uhr
                        </span>
                      </div>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Dauer:</span>
                        <span className="font-bold text-success-600">
                          {calculateDuration(entry.clock_in)}
                        </span>
                      </div>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Stundenlohn:</span>
                        <span className="font-medium text-neutral-900">
                          {entry.employee.hourly_rate ? `${entry.employee.hourly_rate}€/h` : '-'}
                        </span>
                      </div>
                    </div>
                    <Button
                      variant="error"
                      size="sm"
                      icon={<LogOut className="w-4 h-4" />}
                      onClick={() => handleOpenClockOutModal(entry)}
                      className="w-full"
                    >
                      Ausstempeln
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>

        {/* Today's Completed Entries */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="w-5 h-5 text-primary-600" />
              Heutige Einträge
            </CardTitle>
          </CardHeader>
          <CardBody>
            {todayEntries.filter(e => e.clock_out).length === 0 ? (
              <div className="text-center py-8">
                <p className="text-neutral-600">Noch keine abgeschlossenen Einträge heute</p>
              </div>
            ) : (
              <div className="overflow-x-auto custom-scrollbar">
                <table className="w-full">
                  <thead className="bg-neutral-50 border-b-2 border-neutral-200">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Mitarbeiter
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Einstempeln
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Ausstempeln
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Pause
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Arbeitszeit
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Lohn
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-200">
                    {todayEntries.filter(e => e.clock_out).map((entry) => (
                      <tr key={entry.id} className="hover:bg-neutral-50">
                        <td className="px-4 py-3">
                          <div>
                            <p className="font-medium text-neutral-900">
                              {entry.employee.first_name} {entry.employee.last_name}
                            </p>
                            <Badge 
                              variant={entry.employee.employment_type === 'permanent' ? 'primary' : 'secondary'}
                              className="text-xs mt-1"
                            >
                              {entry.employee.employment_type === 'permanent' ? 'Festangestellt' : 'Aushilfe'}
                            </Badge>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {dayjs(entry.clock_in).format('HH:mm')}
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {dayjs(entry.clock_out).format('HH:mm')}
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {entry.break_minutes} min
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-primary-600">
                            {entry.total_hours}h
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-bold text-success-600">
                            {entry.gross_wage ? `${Number(entry.gross_wage).toFixed(2)}€` : '-'}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Clock In Modal */}
      <Modal
        isOpen={clockInModalOpen}
        onClose={() => setClockInModalOpen(false)}
        title="Mitarbeiter einstempeln"
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={() => setClockInModalOpen(false)}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleClockIn}>
              Einstempeln
            </Button>
          </div>
        }
      >
        <form onSubmit={handleClockIn} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Mitarbeiter auswählen
            </label>
            <select
              className="input"
              value={clockInData.employee_id}
              onChange={(e) => setClockInData({ ...clockInData, employee_id: e.target.value })}
              required
            >
              <option value="">Bitte wählen...</option>
              {employees.map((emp) => (
                <option key={emp.id} value={emp.id}>
                  {emp.first_name} {emp.last_name} ({emp.employment_type === 'permanent' ? 'Festangestellt' : 'Aushilfe'})
                </option>
              ))}
            </select>
            {errors.employee_id && (
              <p className="mt-1.5 text-sm text-error-500">{errors.employee_id[0]}</p>
            )}
          </div>
          <Input
            type="datetime-local"
            label="Einstempel-Zeit"
            value={dayjs(clockInData.clock_in).format('YYYY-MM-DDTHH:mm')}
            onChange={(e) => setClockInData({ 
              ...clockInData, 
              clock_in: dayjs(e.target.value).format('YYYY-MM-DD HH:mm:ss')
            })}
            error={errors.clock_in?.[0]}
            required
          />
        </form>
      </Modal>

      {/* Clock Out Modal */}
      <Modal
        isOpen={clockOutModalOpen}
        onClose={() => {
          setClockOutModalOpen(false);
          setSelectedEntry(null);
          setSelectedEmployee(null);
        }}
        title="Mitarbeiter ausstempeln"
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button 
              variant="ghost" 
              onClick={() => {
                setClockOutModalOpen(false);
                setSelectedEntry(null);
                setSelectedEmployee(null);
              }}
            >
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleClockOut}>
              Ausstempeln
            </Button>
          </div>
        }
      >
        {selectedEmployee && (
          <form onSubmit={handleClockOut} className="space-y-4">
            <div className="p-4 bg-neutral-50 rounded-lg">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-12 h-12 rounded-full bg-primary-600 flex items-center justify-center text-white font-semibold text-lg">
                  {selectedEmployee.first_name[0]}{selectedEmployee.last_name[0]}
                </div>
                <div>
                  <p className="font-semibold text-neutral-900">
                    {selectedEmployee.first_name} {selectedEmployee.last_name}
                  </p>
                  <Badge 
                    variant={selectedEmployee.employment_type === 'permanent' ? 'primary' : 'secondary'}
                    className="text-xs mt-1"
                  >
                    {selectedEmployee.employment_type === 'permanent' ? 'Festangestellt' : 'Aushilfe'}
                  </Badge>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <span className="text-neutral-600">Eingestempelt:</span>
                  <p className="font-medium text-neutral-900">
                    {dayjs(selectedEntry?.clock_in).format('HH:mm')} Uhr
                  </p>
                </div>
                <div>
                  <span className="text-neutral-600">Stundenlohn:</span>
                  <p className="font-medium text-neutral-900">
                    {selectedEmployee.hourly_rate ? `${selectedEmployee.hourly_rate}€/h` : 'Nicht festgelegt'}
                  </p>
                </div>
              </div>
            </div>
            
            <Input
              type="datetime-local"
              label="Ausstempel-Zeit"
              value={dayjs(clockOutData.clock_out).format('YYYY-MM-DDTHH:mm')}
              onChange={(e) => setClockOutData({ 
                ...clockOutData, 
                clock_out: dayjs(e.target.value).format('YYYY-MM-DD HH:mm:ss')
              })}
              error={errors.clock_out?.[0]}
              required
            />
            
            <Input
              type="number"
              label="Pausenzeit (Minuten)"
              value={clockOutData.break_minutes}
              onChange={(e) => setClockOutData({ ...clockOutData, break_minutes: e.target.value })}
              error={errors.break_minutes?.[0]}
              placeholder="Leer lassen für automatische Berechnung"
              icon={<Coffee className="w-4 h-4" />}
            />
            
            <div className="p-3 bg-primary-50 rounded-lg border border-primary-200">
              <p className="text-xs text-primary-700 mb-1">Hinweis:</p>
              <p className="text-sm text-primary-900">
                Wenn keine Pausenzeit angegeben wird, wird sie automatisch nach gesetzlichen Vorgaben berechnet (30 Min bei über 6h, 45 Min bei über 9h).
              </p>
            </div>
          </form>
        )}
      </Modal>
    </TimeTrackingLayout>
  );
}