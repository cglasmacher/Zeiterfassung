import React, { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { useToast } from '@/Components/ui/Toast';
import { Plus, Edit2, Trash2, User, Mail, Phone, CreditCard, CheckCircle2, XCircle } from 'lucide-react';
import axios from 'axios';

export default function EmployeesSettings() {
  const [employees, setEmployees] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState(null);
  const [formData, setFormData] = useState({
    employee_number: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    employment_type: 'permanent',
    hourly_rate: '',
    max_monthly_hours: '',
    rfid_tag: '',
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
      const [employeesRes, departmentsRes] = await Promise.all([
        axios.get('/api/employees'),
        axios.get('/api/departments'),
      ]);
      setEmployees(employeesRes.data);
      setDepartments(departmentsRes.data);
    } catch (error) {
      console.error('Error loading data:', error);
      toast.error('Fehler beim Laden der Daten');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (employee = null) => {
    if (employee) {
      setEditingEmployee(employee);
      setFormData({
        employee_number: employee.employee_number || '',
        first_name: employee.first_name,
        last_name: employee.last_name,
        email: employee.email || '',
        phone: employee.phone || '',
        position: employee.position || '',
        employment_type: employee.employment_type || 'permanent',
        hourly_rate: employee.hourly_rate || '',
        max_monthly_hours: employee.max_monthly_hours || '',
        rfid_tag: employee.rfid_tag || '',
        active: employee.active,
        department_ids: employee.departments?.map((d) => d.id) || [],
      });
    } else {
      setEditingEmployee(null);
      setFormData({
        employee_number: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        position: '',
        employment_type: 'permanent',
        hourly_rate: '',
        max_monthly_hours: '',
        rfid_tag: '',
        active: true,
        department_ids: [],
      });
    }
    setErrors({});
    setModalOpen(true);
  };

  const handleCloseModal = () => {
    setModalOpen(false);
    setEditingEmployee(null);
    setFormData({
      employee_number: '',
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      position: '',
      employment_type: 'permanent',
      hourly_rate: '',
      max_monthly_hours: '',
      rfid_tag: '',
      active: true,
      department_ids: [],
    });
    setErrors({});
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});

    try {
      if (editingEmployee) {
        await axios.put(`/api/employees/${editingEmployee.id}`, formData);
        toast.success('Mitarbeiter erfolgreich aktualisiert');
      } else {
        await axios.post('/api/employees', formData);
        toast.success('Mitarbeiter erfolgreich erstellt');
      }
      handleCloseModal();
      loadData();
    } catch (error) {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      } else {
        toast.error('Fehler beim Speichern des Mitarbeiters');
      }
    }
  };

  const handleDelete = async (employee) => {
    if (
      !confirm(
        `Möchten Sie den Mitarbeiter "${employee.first_name} ${employee.last_name}" wirklich löschen?`
      )
    ) {
      return;
    }

    try {
      await axios.delete(`/api/employees/${employee.id}`);
      toast.success('Mitarbeiter erfolgreich gelöscht');
      loadData();
    } catch (error) {
      toast.error('Fehler beim Löschen des Mitarbeiters');
    }
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
          <CardTitle>Mitarbeiter verwalten</CardTitle>
          <Button
            variant="primary"
            size="sm"
            icon={<Plus className="w-4 h-4" />}
            onClick={() => handleOpenModal()}
          >
            Mitarbeiter hinzufügen
          </Button>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
                <p className="text-neutral-600">Lade Mitarbeiter...</p>
              </div>
            </div>
          ) : employees.length === 0 ? (
            <div className="text-center py-12">
              <User className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
              <p className="text-neutral-600 mb-4">Keine Mitarbeiter vorhanden</p>
              <Button
                variant="outline"
                size="sm"
                icon={<Plus className="w-4 h-4" />}
                onClick={() => handleOpenModal()}
              >
                Ersten Mitarbeiter erstellen
              </Button>
            </div>
          ) : (
            <div className="overflow-x-auto custom-scrollbar">
              <table className="w-full">
                <thead className="bg-neutral-50 border-b-2 border-neutral-200">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Name
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Position
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Kontakt
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Stationen
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Stundenlohn
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                      Status
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-semibold text-neutral-700 uppercase">
                      Aktionen
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-200">
                  {employees.map((employee) => (
                    <tr key={employee.id} className="hover:bg-neutral-50 transition-colors">
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                            <User className="w-5 h-5 text-primary-600" />
                          </div>
                          <div>
                            <p className="font-medium text-neutral-900">
                              {employee.first_name} {employee.last_name}
                            </p>
                            {employee.employee_number && (
                              <p className="text-xs text-neutral-500">
                                Nr. {employee.employee_number}
                              </p>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <p className="text-sm text-neutral-900">
                            {employee.position || '-'}
                          </p>
                          <Badge 
                            variant={employee.employment_type === 'permanent' ? 'primary' : 'secondary'} 
                            className="text-xs mt-1"
                          >
                            {employee.employment_type === 'permanent' ? 'Festangestellt' : 'Aushilfe'}
                          </Badge>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          {employee.email && (
                            <p className="text-sm text-neutral-600 flex items-center gap-1">
                              <Mail className="w-3 h-3" />
                              {employee.email}
                            </p>
                          )}
                          {employee.phone && (
                            <p className="text-sm text-neutral-600 flex items-center gap-1">
                              <Phone className="w-3 h-3" />
                              {employee.phone}
                            </p>
                          )}
                          {!employee.email && !employee.phone && (
                            <span className="text-sm text-neutral-400">-</span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-wrap gap-1">
                          {employee.departments?.length > 0 ? (
                            employee.departments.map((dept) => (
                              <Badge key={dept.id} variant="primary" className="text-xs">
                                {dept.name}
                              </Badge>
                            ))
                          ) : (
                            <span className="text-sm text-neutral-400">-</span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm font-medium text-neutral-900">
                          {employee.hourly_rate ? `${employee.hourly_rate} €/h` : '-'}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        {employee.active ? (
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
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center justify-end gap-2">
                          <Button
                            variant="ghost"
                            size="sm"
                            icon={<Edit2 className="w-4 h-4" />}
                            onClick={() => handleOpenModal(employee)}
                          />
                          <Button
                            variant="ghost"
                            size="sm"
                            icon={<Trash2 className="w-4 h-4" />}
                            onClick={() => handleDelete(employee)}
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

      {/* Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={handleCloseModal}
        title={editingEmployee ? 'Mitarbeiter bearbeiten' : 'Neuer Mitarbeiter'}
        size="lg"
        footer={
          <div className="flex items-center justify-end gap-3">
            <Button variant="ghost" onClick={handleCloseModal}>
              Abbrechen
            </Button>
            <Button variant="primary" onClick={handleSubmit}>
              {editingEmployee ? 'Speichern' : 'Erstellen'}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Mitarbeiternummer"
            value={formData.employee_number}
            onChange={(e) => setFormData({ ...formData, employee_number: e.target.value })}
            error={errors.employee_number?.[0]}
            placeholder="z.B. MA001"
          />
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Vorname"
              value={formData.first_name}
              onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
              error={errors.first_name?.[0]}
              placeholder="Max"
              required
            />
            <Input
              label="Nachname"
              value={formData.last_name}
              onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
              error={errors.last_name?.[0]}
              placeholder="Mustermann"
              required
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Input
              type="email"
              label="E-Mail"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              error={errors.email?.[0]}
              placeholder="max@beispiel.de"
              icon={<Mail className="w-4 h-4" />}
            />
            <Input
              type="tel"
              label="Telefon"
              value={formData.phone}
              onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
              error={errors.phone?.[0]}
              placeholder="+49 123 456789"
              icon={<Phone className="w-4 h-4" />}
            />
          </div>
          <Input
            label="Position"
            value={formData.position}
            onChange={(e) => setFormData({ ...formData, position: e.target.value })}
            error={errors.position?.[0]}
            placeholder="z.B. Koch, Kellner"
          />
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Beschäftigungsart
            </label>
            <select
              className="input"
              value={formData.employment_type}
              onChange={(e) => setFormData({ ...formData, employment_type: e.target.value })}
            >
              <option value="permanent">Festangestellt</option>
              <option value="temporary">Aushilfe / Barlöhner</option>
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Input
              type="number"
              step="0.01"
              label="Stundenlohn (€)"
              value={formData.hourly_rate}
              onChange={(e) => setFormData({ ...formData, hourly_rate: e.target.value })}
              error={errors.hourly_rate?.[0]}
              placeholder="15.00"
            />
            <Input
              type="number"
              step="0.5"
              label="Max. Stunden pro Monat"
              value={formData.max_monthly_hours}
              onChange={(e) => setFormData({ ...formData, max_monthly_hours: e.target.value })}
              error={errors.max_monthly_hours?.[0]}
              placeholder="160"
            />
          </div>
          <Input
            label="RFID Tag"
            value={formData.rfid_tag}
            onChange={(e) => setFormData({ ...formData, rfid_tag: e.target.value })}
            error={errors.rfid_tag?.[0]}
            placeholder="Optional: NFC/RFID Chip-ID"
            icon={<CreditCard className="w-4 h-4" />}
          />
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-2">
              Stationen
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
        </form>
      </Modal>
    </>
  );
}