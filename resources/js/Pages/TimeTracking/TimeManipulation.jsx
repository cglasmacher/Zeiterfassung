import React, { useState, useEffect } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import { useToast } from '@/Components/ui/Toast';
import { 
  Edit3, 
  Trash2, 
  Split, 
  ChevronLeft, 
  ChevronRight,
  Calendar,
  User,
  Clock,
  Euro,
  History,
  AlertTriangle,
  Plus,
  Minus
} from 'lucide-react';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';

dayjs.locale('de');

export default function TimeManipulation() {
  const [entries, setEntries] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedYear, setSelectedYear] = useState(dayjs().year());
  const [selectedMonth, setSelectedMonth] = useState(dayjs().month() + 1);
  const [selectedEmployee, setSelectedEmployee] = useState('');
  
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [splitModalOpen, setSplitModalOpen] = useState(false);
  const [auditModalOpen, setAuditModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  
  const [selectedEntry, setSelectedEntry] = useState(null);
  const [editFormData, setEditFormData] = useState({});
  const [splitFormData, setSplitFormData] = useState({ splits: [] });
  const [auditLog, setAuditLog] = useState([]);
  const [deleteReason, setDeleteReason] = useState('');
  
  const [errors, setErrors] = useState({});
  const toast = useToast();

  useEffect(() => {
    loadEmployees();
  }, []);

  useEffect(() => {
    loadEntries();
  }, [selectedYear, selectedMonth, selectedEmployee]);

  const loadEmployees = async () => {
    try {
      const res = await axios.get('/api/employees');
      setEmployees(res.data.filter(e => e.active));
    } catch (error) {
      console.error('Error loading employees:', error);
    }
  };

  const loadEntries = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/time-manipulation/month-entries', {
        params: {
          year: selectedYear,
          month: selectedMonth,
          employee_id: selectedEmployee || undefined,
        },
      });
      setEntries(res.data.entries);
    } catch (error) {
      console.error('Error loading entries:', error);
      toast.error('Fehler beim Laden der Einträge');
    } finally {
      setLoading(false);
    }
  };

  const handlePreviousMonth = () => {
    const newDate = dayjs(`${selectedYear}-${selectedMonth}-01`).subtract(1, 'month');
    setSelectedYear(newDate.year());
    setSelectedMonth(newDate.month() + 1);
  };

  const handleNextMonth = () => {
    const newDate = dayjs(`${selectedYear}-${selectedMonth}-01`).add(1, 'month');
    setSelectedYear(newDate.year());
    setSelectedMonth(newDate.month() + 1);
  };

  const handleOpenEditModal = (entry) => {
    setSelectedEntry(entry);
    setEditFormData({
      clock_in: dayjs(entry.clock_in).format('YYYY-MM-DDTHH:mm'),
      clock_out: dayjs(entry.clock_out).format('YYYY-MM-DDTHH:mm'),
      break_minutes: entry.break_minutes,
      override_hourly_rate: entry.override_hourly_rate || '',
      admin_note: entry.admin_note || '',
    });
    setErrors({});
    setEditModalOpen(true);
  };

  const handleOpenSplitModal = (entry) => {
    setSelectedEntry(entry);
    const duration = dayjs(entry.clock_out).diff(dayjs(entry.clock_in), 'minute');
    const halfDuration = Math.floor(duration / 2);
    
    setSplitFormData({
      splits: [
        {
          clock_in: dayjs(entry.clock_in).format('YYYY-MM-DDTHH:mm'),
          clock_out: dayjs(entry.clock_in).add(halfDuration, 'minute').format('YYYY-MM-DDTHH:mm'),
          break_minutes: Math.floor(entry.break_minutes / 2),
          admin_note: '',
        },
        {
          clock_in: dayjs(entry.clock_in).add(halfDuration, 'minute').format('YYYY-MM-DDTHH:mm'),
          clock_out: dayjs(entry.clock_out).format('YYYY-MM-DDTHH:mm'),
          break_minutes: Math.ceil(entry.break_minutes / 2),
          admin_note: '',
        },
      ],
    });
    setErrors({});
    setSplitModalOpen(true);
  };

  const handleOpenAuditModal = async (entry) => {
    setSelectedEntry(entry);
    try {
      const res = await axios.get(`/api/time-manipulation/entries/${entry.id}/audit`);
      setAuditLog(res.data);
      setAuditModalOpen(true);
    } catch (error) {
      toast.error('Fehler beim Laden des Änderungsprotokolls');
    }
  };

  const handleOpenDeleteModal = (entry) => {
    setSelectedEntry(entry);
    setDeleteReason('');
    setDeleteModalOpen(true);
  };

  const handleUpdateEntry = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      await axios.put(`/api/time-manipulation/entries/${selectedEntry.id}`, {
        clock_in: dayjs(editFormData.clock_in).format('YYYY-MM-DD HH:mm:ss'),
        clock_out: dayjs(editFormData.clock_out).format('YYYY-MM-DD HH:mm:ss'),
        break_minutes: editFormData.break_minutes,
        override_hourly_rate: editFormData.override_hourly_rate || null,
        admin_note: editFormData.admin_note,
      });
      toast.success('Eintrag erfolgreich aktualisiert');
      setEditModalOpen(false);
      loadEntries();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast.error('Fehler beim Aktualisieren');
      }
    }
  };

  const handleSplitEntry = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      const formattedSplits = splitFormData.splits.map(split => ({
        clock_in: dayjs(split.clock_in).format('YYYY-MM-DD HH:mm:ss'),
        clock_out: dayjs(split.clock_out).format('YYYY-MM-DD HH:mm:ss'),
        break_minutes: split.break_minutes,
        admin_note: split.admin_note,
      }));

      await axios.post(`/api/time-manipulation/entries/${selectedEntry.id}/split`, {
        splits: formattedSplits,
      });
      toast.success('Eintrag erfolgreich aufgeteilt');
      setSplitModalOpen(false);
      loadEntries();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast.error('Fehler beim Aufteilen');
      }
    }
  };

  const handleDeleteEntry = async () => {
    try {
      await axios.delete(`/api/time-manipulation/entries/${selectedEntry.id}`, {
        data: { reason: deleteReason },
      });
      toast.success('Eintrag erfolgreich gelöscht');
      setDeleteModalOpen(false);
      loadEntries();
    } catch (error) {
      toast.error('Fehler beim Löschen');
    }
  };

  const addSplit = () => {
    const lastSplit = splitFormData.splits[splitFormData.splits.length - 1];
    setSplitFormData({
      splits: [
        ...splitFormData.splits,
        {
          clock_in: lastSplit.clock_out,
          clock_out: dayjs(lastSplit.clock_out).add(1, 'hour').format('YYYY-MM-DDTHH:mm'),
          break_minutes: 0,
          admin_note: '',
        },
      ],
    });
  };

  const removeSplit = (index) => {
    if (splitFormData.splits.length <= 2) {
      toast.error('Mindestens 2 Teile erforderlich');
      return;
    }
    setSplitFormData({
      splits: splitFormData.splits.filter((_, i) => i !== index),
    });
  };

  const updateSplit = (index, field, value) => {
    const newSplits = [...splitFormData.splits];
    newSplits[index][field] = value;
    setSplitFormData({ splits: newSplits });
  };

  const calculatePreview = (clockIn, clockOut, breakMinutes, hourlyRate) => {
    const start = dayjs(clockIn);
    const end = dayjs(clockOut);
    const totalMinutes = end.diff(start, 'minute');
    const workMinutes = totalMinutes - breakMinutes;
    const workHours = workMinutes / 60;
    const wage = workHours * (hourlyRate || 0);
    
    return {
      totalMinutes,
      workMinutes,
      workHours: workHours.toFixed(2),
      wage: wage.toFixed(2),
    };
  };

  const totalStats = entries.reduce(
    (acc, entry) => ({
      totalHours: acc.totalHours + (Number(entry.total_hours) || 0),
      totalWage: acc.totalWage + (Number(entry.gross_wage) || 0),
    }),
    { totalHours: 0, totalWage: 0 }
  );

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-warning-100 flex items-center justify-center">
              <Edit3 className="w-6 h-6 text-warning-600" />
            </div>
            <div>
              <h1 className="heading-2">Stundenmanipulation</h1>
              <p className="text-small mt-1">
                Zeiteinträge bearbeiten, aufteilen und verwalten
              </p>
            </div>
          </div>
        </div>

        {/* Filters */}
        <Card glass>
          <CardBody className="p-4">
            <div className="flex flex-wrap items-center gap-4">
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handlePreviousMonth}
                  icon={<ChevronLeft className="w-4 h-4" />}
                />
                <div className="px-4 py-2 bg-white rounded-lg border border-neutral-200 min-w-[180px] text-center">
                  <span className="font-semibold text-neutral-900">
                    {dayjs(`${selectedYear}-${selectedMonth}-01`).format('MMMM YYYY')}
                  </span>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleNextMonth}
                  icon={<ChevronRight className="w-4 h-4" />}
                />
              </div>

              <div className="flex items-center gap-2">
                <User className="w-4 h-4 text-neutral-500" />
                <select
                  className="input py-2 text-sm"
                  value={selectedEmployee}
                  onChange={(e) => setSelectedEmployee(e.target.value)}
                >
                  <option value="">Alle Mitarbeiter</option>
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>
                      {emp.first_name} {emp.last_name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="ml-auto flex items-center gap-4 px-4 py-2 bg-primary-50 rounded-lg border border-primary-200">
                <div className="text-center">
                  <p className="text-xs text-primary-700">Gesamt Stunden</p>
                  <p className="text-lg font-bold text-primary-900">
                    {totalStats.totalHours.toFixed(2)}h
                  </p>
                </div>
                <div className="w-px h-8 bg-primary-200"></div>
                <div className="text-center">
                  <p className="text-xs text-primary-700">Gesamt Lohn</p>
                  <p className="text-lg font-bold text-primary-900">
                    {totalStats.totalWage.toFixed(2)}€
                  </p>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Entries Table */}
        <Card>
          <CardHeader>
            <CardTitle>Zeiteinträge</CardTitle>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
              </div>
            ) : entries.length === 0 ? (
              <div className="text-center py-12">
                <Calendar className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                <p className="text-neutral-600">Keine Einträge für diesen Zeitraum</p>
              </div>
            ) : (
              <div className="overflow-x-auto custom-scrollbar">
                <table className="w-full">
                  <thead className="bg-neutral-50 border-b-2 border-neutral-200">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Datum
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Mitarbeiter
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Zeiten
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Pause
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Arbeitszeit
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Stundenlohn
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Lohn
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                        Notiz
                      </th>
                      <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-700 uppercase">
                        Aktionen
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-200">
                    {entries.map((entry) => (
                      <tr key={entry.id} className="hover:bg-neutral-50">
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {dayjs(entry.clock_in).format('DD.MM.YYYY')}
                        </td>
                        <td className="px-4 py-3">
                          <div>
                            <p className="font-medium text-neutral-900">
                              {entry.employee.first_name} {entry.employee.last_name}
                            </p>
                            <Badge 
                              variant={entry.employee.employment_type === 'permanent' ? 'primary' : 'secondary'}
                              className="text-xs mt-1"
                            >
                              {entry.employee.employment_type === 'permanent' ? 'Fest' : 'Aushilfe'}
                            </Badge>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          <div>
                            <p>{dayjs(entry.clock_in).format('HH:mm')} - {dayjs(entry.clock_out).format('HH:mm')}</p>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {entry.break_minutes} min
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-semibold text-primary-600">
                            {entry.total_hours}h
                          </span>
                        </td>
                        <td className="px-4 py-3 text-sm text-neutral-900">
                          {entry.override_hourly_rate ? (
                            <span className="text-warning-600 font-medium">
                              {entry.override_hourly_rate}€/h*
                            </span>
                          ) : (
                            <span>{entry.employee.hourly_rate || '-'}€/h</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <span className="font-bold text-success-600">
                            {Number(entry.gross_wage || 0).toFixed(2)}€
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          {entry.admin_note ? (
                            <span className="text-xs text-neutral-600 italic">
                              {entry.admin_note.substring(0, 30)}...
                            </span>
                          ) : (
                            <span className="text-xs text-neutral-400">-</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="ghost"
                              size="sm"
                              icon={<Edit3 className="w-4 h-4" />}
                              onClick={() => handleOpenEditModal(entry)}
                            />
                            <Button
                              variant="ghost"
                              size="sm"
                              icon={<Split className="w-4 h-4" />}
                              onClick={() => handleOpenSplitModal(entry)}
                            />
                            <Button
                              variant="ghost"
                              size="sm"
                              icon={<History className="w-4 h-4" />}
                              onClick={() => handleOpenAuditModal(entry)}
                            />
                            <Button
                              variant="ghost"
                              size="sm"
                              icon={<Trash2 className="w-4 h-4" />}
                              onClick={() => handleOpenDeleteModal(entry)}
                              className="text-error-600 hover:text-error-700 hover:bg-error-50"
                            />
                          </div>
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

      {/* Edit Modal */}
      <Modal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        title="Eintrag bearbeiten"
        size="lg"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={() => setEditModalOpen(false)}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleUpdateEntry}>
              Speichern
            </Button>
          </div>
        }
      >
        {selectedEntry && (
          <form onSubmit={handleUpdateEntry} className="space-y-4">
            <div className="p-4 bg-neutral-50 rounded-lg">
              <p className="font-semibold text-neutral-900">
                {selectedEntry.employee.first_name} {selectedEntry.employee.last_name}
              </p>
              <p className="text-sm text-neutral-600">
                {dayjs(selectedEntry.clock_in).format('DD.MM.YYYY')}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <Input
                type="datetime-local"
                label="Einstempeln"
                value={editFormData.clock_in}
                onChange={(e) => setEditFormData({ ...editFormData, clock_in: e.target.value })}
                error={errors.clock_in?.[0]}
                required
              />
              <Input
                type="datetime-local"
                label="Ausstempeln"
                value={editFormData.clock_out}
                onChange={(e) => setEditFormData({ ...editFormData, clock_out: e.target.value })}
                error={errors.clock_out?.[0]}
                required
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <Input
                type="number"
                label="Pausenzeit (Minuten)"
                value={editFormData.break_minutes}
                onChange={(e) => setEditFormData({ ...editFormData, break_minutes: e.target.value })}
                error={errors.break_minutes?.[0]}
                required
              />
              <Input
                type="number"
                step="0.01"
                label="Stundenlohn überschreiben (€)"
                value={editFormData.override_hourly_rate}
                onChange={(e) => setEditFormData({ ...editFormData, override_hourly_rate: e.target.value })}
                error={errors.override_hourly_rate?.[0]}
                placeholder={`Standard: ${selectedEntry.employee.hourly_rate}€/h`}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                Admin-Notiz
              </label>
              <textarea
                className="input"
                rows="3"
                value={editFormData.admin_note}
                onChange={(e) => setEditFormData({ ...editFormData, admin_note: e.target.value })}
                placeholder="Optionale Notiz zur Änderung"
              />
            </div>

            <div className="p-4 bg-primary-50 rounded-lg border border-primary-200">
              <p className="text-sm font-medium text-primary-900 mb-2">Vorschau:</p>
              {(() => {
                const preview = calculatePreview(
                  editFormData.clock_in,
                  editFormData.clock_out,
                  Number(editFormData.break_minutes),
                  Number(editFormData.override_hourly_rate || selectedEntry.employee.hourly_rate)
                );
                return (
                  <div className="grid grid-cols-3 gap-3 text-sm">
                    <div>
                      <span className="text-primary-700">Arbeitszeit:</span>
                      <p className="font-bold text-primary-900">{preview.workHours}h</p>
                    </div>
                    <div>
                      <span className="text-primary-700">Stundenlohn:</span>
                      <p className="font-bold text-primary-900">
                        {(editFormData.override_hourly_rate || selectedEntry.employee.hourly_rate)}€/h
                      </p>
                    </div>
                    <div>
                      <span className="text-primary-700">Lohn:</span>
                      <p className="font-bold text-success-600">{preview.wage}€</p>
                    </div>
                  </div>
                );
              })()}
            </div>
          </form>
        )}
      </Modal>

      {/* Split Modal */}
      <Modal
        isOpen={splitModalOpen}
        onClose={() => setSplitModalOpen(false)}
        title="Eintrag aufteilen"
        size="xl"
        footer={
          <div className="flex items-center justify-between w-full">
            <Button
              variant="outline"
              size="sm"
              icon={<Plus className="w-4 h-4" />}
              onClick={addSplit}
            >
              Teil hinzufügen
            </Button>
            <div className="flex items-center gap-3">
              <Button variant="ghost" onClick={() => setSplitModalOpen(false)}>
                Abbrechen
              </Button>
              <Button variant="primary" onClick={handleSplitEntry}>
                Aufteilen
              </Button>
            </div>
          </div>
        }
      >
        {selectedEntry && (
          <form onSubmit={handleSplitEntry} className="space-y-4">
            <div className="p-4 bg-warning-50 rounded-lg border border-warning-200 flex items-start gap-3">
              <AlertTriangle className="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold text-warning-900">Achtung</p>
                <p className="text-sm text-warning-700">
                  Der ursprüngliche Eintrag wird in {splitFormData.splits.length} Teile aufgeteilt. 
                  Diese Aktion kann nicht rückgängig gemacht werden.
                </p>
              </div>
            </div>

            <div className="space-y-4">
              {splitFormData.splits.map((split, index) => (
                <div key={index} className="p-4 border-2 border-neutral-200 rounded-lg">
                  <div className="flex items-center justify-between mb-3">
                    <h4 className="font-semibold text-neutral-900">Teil {index + 1}</h4>
                    {splitFormData.splits.length > 2 && (
                      <Button
                        variant="ghost"
                        size="sm"
                        icon={<Minus className="w-4 h-4" />}
                        onClick={() => removeSplit(index)}
                        className="text-error-600"
                      />
                    )}
                  </div>
                  <div className="grid grid-cols-3 gap-3">
                    <Input
                      type="datetime-local"
                      label="Von"
                      value={split.clock_in}
                      onChange={(e) => updateSplit(index, 'clock_in', e.target.value)}
                      required
                    />
                    <Input
                      type="datetime-local"
                      label="Bis"
                      value={split.clock_out}
                      onChange={(e) => updateSplit(index, 'clock_out', e.target.value)}
                      required
                    />
                    <Input
                      type="number"
                      label="Pause (Min)"
                      value={split.break_minutes}
                      onChange={(e) => updateSplit(index, 'break_minutes', e.target.value)}
                      required
                    />
                  </div>
                  <Input
                    label="Notiz (optional)"
                    value={split.admin_note}
                    onChange={(e) => updateSplit(index, 'admin_note', e.target.value)}
                    placeholder="z.B. Vormittag, Nachmittag"
                    className="mt-3"
                  />
                </div>
              ))}
            </div>
          </form>
        )}
      </Modal>

      {/* Audit Log Modal */}
      <Modal
        isOpen={auditModalOpen}
        onClose={() => setAuditModalOpen(false)}
        title="Änderungsprotokoll"
        size="lg"
      >
        {auditLog.length === 0 ? (
          <p className="text-center py-8 text-neutral-600">Keine Änderungen protokolliert</p>
        ) : (
          <div className="space-y-3">
            {auditLog.map((audit) => (
              <div key={audit.id} className="p-4 border border-neutral-200 rounded-lg">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <Badge variant="primary" className="text-xs">
                      {audit.action === 'created' && 'Erstellt'}
                      {audit.action === 'updated' && 'Bearbeitet'}
                      {audit.action === 'split' && 'Aufgeteilt'}
                      {audit.action === 'deleted' && 'Gelöscht'}
                    </Badge>
                    <p className="text-xs text-neutral-500 mt-1">
                      {dayjs(audit.created_at).format('DD.MM.YYYY HH:mm')} Uhr
                    </p>
                  </div>
                  <p className="text-sm text-neutral-600">
                    {audit.user?.name || 'System'}
                  </p>
                </div>
                {audit.note && (
                  <p className="text-sm text-neutral-700 mt-2">{audit.note}</p>
                )}
              </div>
            ))}
          </div>
        )}
      </Modal>

      {/* Delete Modal */}
      <Modal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        title="Eintrag löschen"
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={() => setDeleteModalOpen(false)}>
              Abbrechen
            </Button>
            <Button variant="error" onClick={handleDeleteEntry}>
              Löschen
            </Button>
          </div>
        }
      >
        {selectedEntry && (
          <div className="space-y-4">
            <div className="p-4 bg-error-50 rounded-lg border border-error-200 flex items-start gap-3">
              <AlertTriangle className="w-5 h-5 text-error-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold text-error-900">Warnung</p>
                <p className="text-sm text-error-700">
                  Dieser Eintrag wird unwiderruflich gelöscht. Die Aktion wird im Änderungsprotokoll festgehalten.
                </p>
              </div>
            </div>

            <div className="p-4 bg-neutral-50 rounded-lg">
              <p className="font-semibold text-neutral-900">
                {selectedEntry.employee.first_name} {selectedEntry.employee.last_name}
              </p>
              <p className="text-sm text-neutral-600">
                {dayjs(selectedEntry.clock_in).format('DD.MM.YYYY HH:mm')} - {dayjs(selectedEntry.clock_out).format('HH:mm')}
              </p>
              <p className="text-sm text-neutral-600 mt-1">
                Arbeitszeit: {selectedEntry.total_hours}h | Lohn: {Number(selectedEntry.gross_wage).toFixed(2)}€
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                Grund für Löschung (optional)
              </label>
              <textarea
                className="input"
                rows="3"
                value={deleteReason}
                onChange={(e) => setDeleteReason(e.target.value)}
                placeholder="Warum wird dieser Eintrag gelöscht?"
              />
            </div>
          </div>
        )}
      </Modal>
    </TimeTrackingLayout>
  );
}