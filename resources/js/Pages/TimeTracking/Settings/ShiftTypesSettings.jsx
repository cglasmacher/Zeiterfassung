import React, { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { useToast } from '@/Components/ui/Toast';
import { Plus, Edit2, Trash2, Clock, Sun, Moon, Sunset, CheckCircle2, XCircle } from 'lucide-react';
import axios from 'axios';

export default function ShiftTypesSettings() {
  const [shiftTypes, setShiftTypes] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingShiftType, setEditingShiftType] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    default_start: '08:00',
    default_end: '16:00',
    default_break_minutes: 30,
    active: true,
    department_ids: [],
  });
  const [errors, setErrors] = useState({});
  const toast = useToast();

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [shiftTypesRes, departmentsRes] = await Promise.all([
        axios.get('/api/shift-types'),
        axios.get('/api/departments'),
      ]);
      setShiftTypes(shiftTypesRes.data);
      setDepartments(departmentsRes.data);
    } catch (error) {
      console.error('Error loading data:', error);
      toast.error('Fehler beim Laden der Daten');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (shiftType = null) => {
    if (shiftType) {
      setEditingShiftType(shiftType);
      setFormData({
        name: shiftType.name,
        default_start: shiftType.default_start,
        default_end: shiftType.default_end,
        default_break_minutes: shiftType.default_break_minutes,
        active: shiftType.active,
        department_ids: shiftType.departments?.map((d) => d.id) || [],
      });
    } else {
      setEditingShiftType(null);
      setFormData({
        name: '',
        default_start: '08:00',
        default_end: '16:00',
        default_break_minutes: 30,
        active: true,
        department_ids: [],
      });
    }
    setErrors({});
    setModalOpen(true);
  };

  const handleCloseModal = () => {
    setModalOpen(false);
    setEditingShiftType(null);
    setFormData({
      name: '',
      default_start: '08:00',
      default_end: '16:00',
      default_break_minutes: 30,
      active: true,
      department_ids: [],
    });
    setErrors({});
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      if (editingShiftType) {
        await axios.put(`/api/shift-types/${editingShiftType.id}`, formData);
        toast.success('Schichttyp erfolgreich aktualisiert');
      } else {
        await axios.post('/api/shift-types', formData);
        toast.success('Schichttyp erfolgreich erstellt');
      }
      handleCloseModal();
      loadData();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast.error('Fehler beim Speichern des Schichttyps');
      }
    }
  };

  const handleDelete = async (shiftType) => {
    if (!confirm(`Möchten Sie den Schichttyp "${shiftType.name}" wirklich löschen?`)) {
      return;
    }

    try {
      await axios.delete(`/api/shift-types/${shiftType.id}`);
      toast.success('Schichttyp erfolgreich gelöscht');
      loadData();
    } catch (error) {
      toast.error('Fehler beim Löschen des Schichttyps');
    }
  };

  const getShiftIcon = (name) => {
    const lowerName = name.toLowerCase();
    if (lowerName.includes('früh')) return Sun;
    if (lowerName.includes('spät')) return Sunset;
    if (lowerName.includes('nacht')) return Moon;
    return Clock;
  };

  const calculateHours = (start, end, breakMinutes) => {
    const [startHour, startMin] = start.split(':').map(Number);
    const [endHour, endMin] = end.split(':').map(Number);
    const startMinutes = startHour * 60 + startMin;
    const endMinutes = endHour * 60 + endMin;
    const totalMinutes = endMinutes - startMinutes - breakMinutes;
    return (totalMinutes / 60).toFixed(1);
  };

  const handleDepartmentToggle = (departmentId) => {
    setFormData((prev) => ({
      ...prev,
      department_ids: prev.department_ids.includes(departmentId)
        ? prev.department_ids.filter((id) => id !== departmentId)
        : [...prev.department_ids, departmentId],
    }));
  };

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Schichttypen verwalten</CardTitle>
          <Button
            variant="primary"
            size="sm"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => handleOpenModal()}
          >
            Schichttyp hinzufügen
          </Button>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
                <p className="text-neutral-600">Lade Schichttypen...</p>
              </div>
            </div>
          ) : shiftTypes.length === 0 ? (
            <div className="text-center py-12">
              <Clock className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
              <p className="text-neutral-600 mb-4">Keine Schichttypen vorhanden</p>
              <Button
                variant="outline"
                size="sm"
                icon={<Plus className="w-4 h-4" />}
                onClick={() => handleOpenModal()}
              >
                Ersten Schichttyp erstellen
              </Button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {shiftTypes.map((shiftType) => {
                const Icon = getShiftIcon(shiftType.name);
                const hours = calculateHours(
                  shiftType.default_start,
                  shiftType.default_end,
                  shiftType.default_break_minutes
                );

                return (
                  <div
                    key={shiftType.id}
                    className="p-4 border border-neutral-200 rounded-xl hover:shadow-md transition-all"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                          <Icon className="w-5 h-5 text-primary-600" />
                        </div>
                        <div>
                          <h4 className="font-semibold text-neutral-900">{shiftType.name}</h4>
                          <div className="flex items-center gap-2 mt-1">
                            {shiftType.active ? (
                              <Badge variant="success" className="text-xs">
                                <CheckCircle2 className="w-3 h-3" />
                                Aktiv
                              </Badge>
                            ) : (
                              <Badge variant="error" className="text-xs">
                                <XCircle className="w-3 h-3" />
                                Inaktiv
                              </Badge>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="space-y-2 mb-3">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Arbeitszeit:</span>
                        <span className="font-medium text-neutral-900">
                          {shiftType.default_start} - {shiftType.default_end}
                        </span>
                      </div>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Pause:</span>
                        <span className="font-medium text-neutral-900">
                          {shiftType.default_break_minutes} min
                        </span>
                      </div>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-neutral-600">Netto-Stunden:</span>
                        <span className="font-bold text-primary-600">{hours}h</span>
                      </div>
                    </div>
                    {shiftType.departments && shiftType.departments.length > 0 && (
                      <div className="flex flex-wrap gap-1 mb-3">
                        {shiftType.departments.map((dept) => (
                          <Badge key={dept.id} variant="primary" className="text-xs">
                            {dept.name}
                          </Badge>
                        ))}
                      </div>
                    )}
                    <div className="flex items-center gap-2 pt-3 border-t border-neutral-100">
                      <Button
                        variant="ghost"
                        size="sm"
                        icon={<Edit2 className="w-4 h-4" />}
                        onClick={() => handleOpenModal(shiftType)}
                      >
                        Bearbeiten
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        icon={<Trash2 className="w-4 h-4" />}
                        onClick={() => handleDelete(shiftType)}
                        className="text-error-600 hover:text-error-700 hover:bg-error-50"
                      >
                        Löschen
                      </Button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={handleCloseModal}
        title={editingShiftType ? 'Schichttyp bearbeiten' : 'Neuer Schichttyp'}
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={handleCloseModal}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleSubmit}>
              {editingShiftType ? 'Speichern' : 'Erstellen'}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            error={errors.name?.[0]}
            placeholder="z.B. Frühschicht, Spätschicht"
            required
          />
          <div className="grid grid-cols-2 gap-4">
            <Input
              type="time"
              label="Startzeit"
              value={formData.default_start}
              onChange={(e) => setFormData({ ...formData, default_start: e.target.value })}
              error={errors.default_start?.[0]}
              required
            />
            <Input
              type="time"
              label="Endzeit"
              value={formData.default_end}
              onChange={(e) => setFormData({ ...formData, default_end: e.target.value })}
              error={errors.default_end?.[0]}
              required
            />
          </div>
          <Input
            type="number"
            label="Pausenzeit (Minuten)"
            value={formData.default_break_minutes}
            onChange={(e) => setFormData({ ...formData, default_break_minutes: e.target.value })}
            error={errors.default_break_minutes?.[0]}
            min="0"
            required
          />
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="active"
              checked={formData.active}
              onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
              className="w-4 h-4 text-primary-600 border-neutral-300 rounded focus:ring-primary-500"
            />
            <label htmlFor="active" className="text-sm font-medium text-neutral-700">
              Aktiv
            </label>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-2">
              Stationen zuordnen
            </label>
            <div className="grid grid-cols-2 gap-2">
              {departments.map((dept) => (
                <label
                  key={dept.id}
                  className="flex items-center gap-2 p-3 border border-neutral-200 rounded-lg hover:bg-neutral-50 cursor-pointer transition-colors"
                >
                  <input
                    type="checkbox"
                    checked={formData.department_ids.includes(dept.id)}
                    onChange={() => handleDepartmentToggle(dept.id)}
                    className="w-4 h-4 text-primary-600 border-neutral-300 rounded focus:ring-primary-500"
                  />
                  <span className="text-sm text-neutral-900">{dept.name}</span>
                </label>
              ))}
            </div>
            {departments.length === 0 && (
              <p className="text-sm text-neutral-500 italic">
                Keine Stationen verfügbar. Bitte erstellen Sie zuerst Stationen.
              </p>
            )}
          </div>
          <div className="p-4 bg-neutral-50 rounded-lg">
            <p className="text-sm text-neutral-600">
              Netto-Arbeitsstunden:{' '}
              <span className="font-bold text-primary-600">
                {calculateHours(
                  formData.default_start,
                  formData.default_end,
                  formData.default_break_minutes
                )}
                h
              </span>
            </p>
          </div>
        </form>
      </Modal>
    </>
  );
}