import React, { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import { useToast } from '@/Components/ui/Toast';
import { Plus, Edit2, Trash2, Building2, Users } from 'lucide-react';
import axios from 'axios';

export default function DepartmentsSettings() {
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingDepartment, setEditingDepartment] = useState(null);
  const [formData, setFormData] = useState({ name: '', description: '' });
  const [errors, setErrors] = useState({});
  const toast = useToast();

  useEffect(() => {
    loadDepartments();
  }, []);

  const loadDepartments = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/departments');
      setDepartments(res.data);
    } catch (error) {
      console.error('Error loading departments:', error);
      toast.error('Fehler beim Laden der Stationen');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (department = null) => {
    if (department) {
      setEditingDepartment(department);
      setFormData({ name: department.name, description: department.description || '' });
    } else {
      setEditingDepartment(null);
      setFormData({ name: '', description: '' });
    }
    setErrors({});
    setModalOpen(true);
  };

  const handleCloseModal = () => {
    setModalOpen(false);
    setEditingDepartment(null);
    setFormData({ name: '', description: '' });
    setErrors({});
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      if (editingDepartment) {
        await axios.put(`/api/departments/${editingDepartment.id}`, formData);
        toast.success('Station erfolgreich aktualisiert');
      } else {
        await axios.post('/api/departments', formData);
        toast.success('Station erfolgreich erstellt');
      }
      handleCloseModal();
      loadDepartments();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast.error('Fehler beim Speichern der Station');
      }
    }
  };

  const handleDelete = async (department) => {
    if (!confirm(`Möchten Sie die Station "${department.name}" wirklich löschen?`)) {
      return;
    }

    try {
      await axios.delete(`/api/departments/${department.id}`);
      toast.success('Station erfolgreich gelöscht');
      loadDepartments();
    } catch (error) {
      toast.error('Fehler beim Löschen der Station');
    }
  };

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Stationen verwalten</CardTitle>
          <Button
            variant="primary"
            size="sm"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => handleOpenModal()}
          >
            Station hinzufügen
          </Button>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
                <p className="text-neutral-600">Lade Stationen...</p>
              </div>
            </div>
          ) : departments.length === 0 ? (
            <div className="text-center py-12">
              <Building2 className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
              <p className="text-neutral-600 mb-4">Keine Stationen vorhanden</p>
              <Button
                variant="outline"
                size="sm"
                icon={<Plus className="w-4 h-4" />}
                onClick={() => handleOpenModal()}
              >
                Erste Station erstellen
              </Button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {departments.map((department) => (
                <div
                  key={department.id}
                  className="p-4 border border-neutral-200 rounded-xl hover:shadow-md transition-all"
                >
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <Building2 className="w-5 h-5 text-primary-600" />
                      </div>
                      <div>
                        <h4 className="font-semibold text-neutral-900">{department.name}</h4>
                        <div className="flex items-center gap-1 text-xs text-neutral-500 mt-1">
                          <Users className="w-3 h-3" />
                          <span>{department.employees_count || 0} Mitarbeiter</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  {department.description && (
                    <p className="text-sm text-neutral-600 mb-3">{department.description}</p>
                  )}
                  <div className="flex items-center gap-2 pt-3 border-t border-neutral-100">
                    <Button
                      variant="ghost"
                      size="sm"
                      icon={<Edit2 className="w-4 h-4" />}
                      onClick={() => handleOpenModal(department)}
                    >
                      Bearbeiten
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      icon={<Trash2 className="w-4 h-4" />}
                      onClick={() => handleDelete(department)}
                      className="text-error-600 hover:text-error-700 hover:bg-error-50"
                    >
                      Löschen
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={handleCloseModal}
        title={editingDepartment ? 'Station bearbeiten' : 'Neue Station'}
        size="md"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={handleCloseModal}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleSubmit}>
              {editingDepartment ? 'Speichern' : 'Erstellen'}
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
            placeholder="z.B. Küche, Service, Bar"
            required
          />
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Beschreibung
            </label>
            <textarea
              className={`input ${errors.description ? 'border-error-500' : ''}`}
              rows="3"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Optionale Beschreibung der Station"
            />
            {errors.description && (
              <p className="mt-1.5 text-sm text-error-500">{errors.description[0]}</p>
            )}
          </div>
        </form>
      </Modal>
    </>
  );
}