import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import axios from 'axios';
import { 
  Plus, 
  Search, 
  Filter,
  Edit,
  Trash2,
  Mail,
  Phone,
  CreditCard,
  CheckCircle,
  XCircle,
  MoreVertical
} from 'lucide-react';

export default function Employees() {
  const [employees, setEmployees] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadEmployees();
  }, []);

  const loadEmployees = async () => {
    setLoading(true);
    try {
      const res = await axios.get('/api/employees');
      setEmployees(res.data);
    } catch (error) {
      console.error('Error loading employees:', error);
    } finally {
      setLoading(false);
    }
  };

  const filteredEmployees = employees.filter(emp => {
    const matchesSearch = 
      emp.first_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      emp.last_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      emp.email?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesStatus = 
      filterStatus === 'all' || 
      (filterStatus === 'active' && emp.active) ||
      (filterStatus === 'inactive' && !emp.active);
    
    return matchesSearch && matchesStatus;
  });

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="heading-2">Mitarbeiter</h1>
            <p className="text-small mt-1">
              {employees.length} Mitarbeiter insgesamt
            </p>
          </div>

          <Button variant="primary" icon={<Plus className="w-4 h-4" />}>
            Mitarbeiter hinzufügen
          </Button>
        </div>

        {/* Filters */}
        <Card glass>
          <CardBody className="p-4">
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="flex-1">
                <Input
                  placeholder="Suche nach Name, E-Mail..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  icon={<Search className="w-4 h-4" />}
                />
              </div>
              
              <div className="flex items-center gap-2">
                <Filter className="w-4 h-4 text-neutral-500" />
                <select
                  className="input py-2"
                  value={filterStatus}
                  onChange={(e) => setFilterStatus(e.target.value)}
                >
                  <option value="all">Alle Status</option>
                  <option value="active">Aktiv</option>
                  <option value="inactive">Inaktiv</option>
                </select>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Aktive Mitarbeiter</p>
                  <p className="text-2xl font-bold text-success-600">
                    {employees.filter(e => e.active).length}
                  </p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-success-100 flex items-center justify-center">
                  <CheckCircle className="w-6 h-6 text-success-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Inaktive Mitarbeiter</p>
                  <p className="text-2xl font-bold text-neutral-600">
                    {employees.filter(e => !e.active).length}
                  </p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-neutral-100 flex items-center justify-center">
                  <XCircle className="w-6 h-6 text-neutral-600" />
                </div>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-tiny text-neutral-500 mb-1">Mit RFID</p>
                  <p className="text-2xl font-bold text-primary-600">
                    {employees.filter(e => e.rfid_tag).length}
                  </p>
                </div>
                <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                  <CreditCard className="w-6 h-6 text-primary-600" />
                </div>
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Employee Grid */}
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
              <p className="text-neutral-600">Lade Mitarbeiter...</p>
            </div>
          </div>
        ) : filteredEmployees.length === 0 ? (
          <Card>
            <CardBody className="p-12 text-center">
              <p className="text-neutral-600">Keine Mitarbeiter gefunden</p>
            </CardBody>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filteredEmployees.map(emp => (
              <Card key={emp.id} className="overflow-hidden group">
                <CardBody className="p-0">
                  {/* Header with Avatar */}
                  <div className="bg-gradient-to-br from-primary-500 to-accent-500 p-6 relative">
                    <div className="absolute top-4 right-4">
                      <button className="p-2 bg-white/20 hover:bg-white/30 rounded-lg transition-colors">
                        <MoreVertical className="w-4 h-4 text-white" />
                      </button>
                    </div>
                    
                    <div className="flex flex-col items-center">
                      <div className="w-20 h-20 rounded-full bg-white flex items-center justify-center text-primary-600 font-bold text-2xl mb-3 shadow-lg">
                        {emp.first_name?.charAt(0)}{emp.last_name?.charAt(0)}
                      </div>
                      <h3 className="text-xl font-bold text-white text-center">
                        {emp.first_name} {emp.last_name}
                      </h3>
                      <p className="text-sm text-white/80 mt-1">
                        {emp.position || 'Mitarbeiter'}
                      </p>
                    </div>
                  </div>

                  {/* Details */}
                  <div className="p-6 space-y-4">
                    {emp.email && (
                      <div className="flex items-center gap-3 text-sm">
                        <Mail className="w-4 h-4 text-neutral-400 flex-shrink-0" />
                        <span className="text-neutral-700 truncate">{emp.email}</span>
                      </div>
                    )}
                    
                    {emp.phone && (
                      <div className="flex items-center gap-3 text-sm">
                        <Phone className="w-4 h-4 text-neutral-400 flex-shrink-0" />
                        <span className="text-neutral-700">{emp.phone}</span>
                      </div>
                    )}
                    
                    {emp.rfid_tag && (
                      <div className="flex items-center gap-3 text-sm">
                        <CreditCard className="w-4 h-4 text-neutral-400 flex-shrink-0" />
                        <span className="text-neutral-700 font-mono">{emp.rfid_tag}</span>
                      </div>
                    )}

                    <div className="flex items-center justify-between pt-2 border-t border-neutral-200">
                      <Badge variant={emp.active ? 'success' : 'neutral'}>
                        {emp.active ? 'Aktiv' : 'Inaktiv'}
                      </Badge>
                      
                      {emp.hourly_rate && (
                        <span className="text-sm font-semibold text-neutral-900">
                          {emp.hourly_rate}€/h
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="px-6 pb-6 flex gap-2">
                    <Button 
                      variant="outline" 
                      size="sm" 
                      className="flex-1"
                      icon={<Edit className="w-3.5 h-3.5" />}
                    >
                      Bearbeiten
                    </Button>
                    <Button 
                      variant="ghost" 
                      size="sm"
                      className="text-error-600 hover:bg-error-50"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </Button>
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