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
  TrendingUp,
  DollarSign,
  CheckCircle,
  Circle,
  AlertTriangle,
  FileText
} from 'lucide-react';

dayjs.locale('de');

export default function DailyOverview() {
  const [date, setDate] = useState(dayjs().format('YYYY-MM-DD'));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('all'); // 'all' or 'cash'
  const [processingPaid, setProcessingPaid] = useState({});
  const [showExpensesModal, setShowExpensesModal] = useState(false);
  const [expenses, setExpenses] = useState({
    purchases: [], // {amount: '', recipient: ''}
    advances: [], // {amount: '', recipient: ''}
    other: [], // {amount: '', reason: ''}
  });
  const toast = useToast();

  useEffect(() => {
    loadData();
  }, [date]);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get(`/api/daily-overview?date=${date}`);
      setData(res.data);
    } catch (error) {
      console.error('Error loading daily overview:', error);
      setError('Fehler beim Laden der Daten');
      toast.error('Fehler beim Laden der Tagesübersicht');
      setData(null);
    } finally {
      setLoading(false);
    }
  };

  const togglePaidStatus = async (entryId, currentStatus) => {
    setProcessingPaid(prev => ({ ...prev, [entryId]: true }));
    try {
      const endpoint = currentStatus 
        ? `/api/daily-overview/entries/${entryId}/unmark-paid`
        : `/api/daily-overview/entries/${entryId}/mark-paid`;
      
      await axios.post(endpoint);
      toast.success(currentStatus ? 'Auszahlung zurückgesetzt' : 'Als ausgezahlt markiert');
      loadData();
    } catch (error) {
      console.error('Error toggling paid status:', error);
      toast.error('Fehler beim Aktualisieren des Status');
    } finally {
      setProcessingPaid(prev => ({ ...prev, [entryId]: false }));
    }
  };

  const openExpensesModal = () => {
    setShowExpensesModal(true);
  };

  const addExpense = (type) => {
    setExpenses(prev => ({
      ...prev,
      [type]: [...prev[type], type === 'other' ? { amount: '', reason: '' } : { amount: '', recipient: '' }]
    }));
  };

  const removeExpense = (type, index) => {
    setExpenses(prev => ({
      ...prev,
      [type]: prev[type].filter((_, i) => i !== index)
    }));
  };

  const updateExpense = (type, index, field, value) => {
    setExpenses(prev => ({
      ...prev,
      [type]: prev[type].map((item, i) => i === index ? { ...item, [field]: value } : item)
    }));
  };

  const generateShiftEndReport = async () => {
    try {
      const response = await axios.post('/api/daily-overview/shift-end-report', {
        date,
        reset_paid_status: false,
        expenses: expenses
      }, {
        responseType: 'blob'
      });

      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `Schichtende_${date}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      
      toast.success('Schichtende-Report erstellt');
      setShowExpensesModal(false);
      setExpenses({ purchases: [], advances: [], other: [] }); // Reset
      loadData(); // Reload to show reset status
    } catch (error) {
      console.error('Error generating report:', error);
      toast.error(error.response?.data?.error || 'Fehler beim Erstellen des Reports');
    }
  };

  const recalculateWages = async () => {
    try {
      const response = await axios.post('/api/daily-overview/recalculate-wages', { date });
      toast.success(response.data.message);
      loadData(); // Reload to show updated values
    } catch (error) {
      console.error('Error recalculating wages:', error);
      toast.error('Fehler beim Neuberechnen der Löhne');
    }
  };

  const goToPreviousDay = () => setDate(dayjs(date).subtract(1, 'day').format('YYYY-MM-DD'));
  const goToNextDay = () => setDate(dayjs(date).add(1, 'day').format('YYYY-MM-DD'));
  const goToToday = () => setDate(dayjs().format('YYYY-MM-DD'));

  const isToday = dayjs(date).isSame(dayjs(), 'day');

  const displayEntries = activeTab === 'all' 
    ? (data?.all_entries || [])
    : (data?.cash_payment_entries || []);

  const totalHours = displayEntries.reduce((sum, e) => sum + (parseFloat(e.total_hours) || 0), 0);
  const totalBreak = displayEntries.reduce((sum, e) => sum + (parseInt(e.break_minutes) || 0), 0);

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

              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={recalculateWages}
                  icon={<TrendingUp className="w-4 h-4" />}
                >
                  Löhne neu berechnen
                </Button>
                <Input
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  className="w-auto"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Tabs */}
        <div className="flex gap-2 border-b border-neutral-200">
          <button
            onClick={() => setActiveTab('all')}
            className={`px-4 py-2 font-medium transition-colors ${
              activeTab === 'all'
                ? 'text-primary-600 border-b-2 border-primary-600'
                : 'text-neutral-600 hover:text-neutral-900'
            }`}
          >
            Alle Mitarbeiter
            {data && <span className="ml-2 text-sm">({data.all_entries?.length || 0})</span>}
          </button>
          <button
            onClick={() => setActiveTab('cash')}
            className={`px-4 py-2 font-medium transition-colors ${
              activeTab === 'cash'
                ? 'text-primary-600 border-b-2 border-primary-600'
                : 'text-neutral-600 hover:text-neutral-900'
            }`}
          >
            <DollarSign className="w-4 h-4 inline mr-1" />
            Barauszahlung
            {data && <span className="ml-2 text-sm">({data.cash_payment_entries?.length || 0})</span>}
          </button>
        </div>

        {/* Summary Stats */}
        {data && displayEntries.length > 0 && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <Card>
              <CardBody className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-tiny text-neutral-500 mb-1">Gesamte Arbeitszeit</p>
                    <p className="text-3xl font-bold text-primary-600">
                      {(totalHours || 0).toFixed(1)}h
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
                      {totalBreak} Min
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
                    <p className="text-tiny text-neutral-500 mb-1">Mitarbeiter</p>
                    <p className="text-3xl font-bold text-success-600">
                      {displayEntries.length}
                    </p>
                  </div>
                  <div className="w-14 h-14 rounded-xl bg-success-100 flex items-center justify-center">
                    <TrendingUp className="w-7 h-7 text-success-600" />
                  </div>
                </div>
              </CardBody>
            </Card>

            {activeTab === 'cash' && (
              <Card>
                <CardBody className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Barauszahlung</p>
                      <p className="text-3xl font-bold text-green-600">
                        €{(parseFloat(data.total_cash_amount) || 0).toFixed(2)}
                      </p>
                    </div>
                    <div className="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center">
                      <DollarSign className="w-7 h-7 text-green-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>
            )}
          </div>
        )}

        {/* Shift End Report Button (only on cash tab) */}
        {activeTab === 'cash' && data && data.cash_payment_entries?.length > 0 && (
          <Card className={data.unpaid_count > 0 ? 'border-orange-300 bg-orange-50' : 'border-green-300 bg-green-50'}>
            <CardBody className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  {data.unpaid_count > 0 ? (
                    <>
                      <AlertTriangle className="w-5 h-5 text-orange-600" />
                      <div>
                        <p className="font-medium text-orange-900">
                          {data.unpaid_count} Mitarbeiter noch nicht ausgezahlt
                        </p>
                        <p className="text-sm text-orange-700">
                          Markieren Sie alle Mitarbeiter als ausgezahlt, bevor Sie den Report erstellen
                        </p>
                      </div>
                    </>
                  ) : (
                    <>
                      <CheckCircle className="w-5 h-5 text-green-600" />
                      <div>
                        <p className="font-medium text-green-900">
                          Alle Mitarbeiter ausgezahlt
                        </p>
                        <p className="text-sm text-green-700">
                          Bereit für Schichtende-Report
                        </p>
                      </div>
                    </>
                  )}
                </div>
                <Button
                  variant={data.unpaid_count > 0 ? "outline" : "primary"}
                  onClick={openExpensesModal}
                  icon={<FileText className="w-4 h-4" />}
                >
                  Schichtende
                </Button>
              </div>
            </CardBody>
          </Card>
        )}

        {/* Entries List */}
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
              <p className="text-neutral-600">Lade Daten...</p>
            </div>
          </div>
        ) : displayEntries.length === 0 || error ? (
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
            {displayEntries.map((entry) => (
              <Card key={entry.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-lg">
                        {entry.employee?.first_name?.charAt(0) || 'M'}
                      </div>
                      <div>
                        <CardTitle>{entry.employee?.full_name || 'Unbekannt'}</CardTitle>
                        <CardDescription>
                          {dayjs(entry.clock_in).format('HH:mm')} - {dayjs(entry.clock_out).format('HH:mm')}
                          {entry.employee?.departments?.[0] && (
                            <span className="ml-2 text-primary-600">• {entry.employee.departments[0].name}</span>
                          )}
                        </CardDescription>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {entry.employee?.cash_payment && (
                        <Badge variant={entry.paid_out_at ? "success" : "warning"}>
                          <DollarSign className="w-3 h-3 mr-1" />
                          {entry.paid_out_at ? 'Ausgezahlt' : 'Barauszahlung'}
                        </Badge>
                      )}
                      <Badge variant="success">Abgeschlossen</Badge>
                    </div>
                  </div>
                </CardHeader>
                <CardBody>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="p-4 bg-primary-50 rounded-xl border border-primary-200">
                      <div className="flex items-center gap-2 mb-2">
                        <Clock className="w-4 h-4 text-primary-600" />
                        <p className="text-sm font-medium text-primary-900">Arbeitszeit</p>
                      </div>
                      <p className="text-2xl font-bold text-primary-600">{(parseFloat(entry.total_hours) || 0).toFixed(2)} Std</p>
                    </div>

                    <div className="p-4 bg-accent-50 rounded-xl border border-accent-200">
                      <div className="flex items-center gap-2 mb-2">
                        <Coffee className="w-4 h-4 text-accent-600" />
                        <p className="text-sm font-medium text-accent-900">Pausen</p>
                      </div>
                      <p className="text-2xl font-bold text-accent-600">{parseInt(entry.break_minutes) || 0} Min</p>
                    </div>

                    <div className="p-4 bg-green-50 rounded-xl border border-green-200">
                      <div className="flex items-center gap-2 mb-2">
                        <DollarSign className="w-4 h-4 text-green-600" />
                        <p className="text-sm font-medium text-green-900">Lohn</p>
                      </div>
                      <p className="text-2xl font-bold text-green-600">€{(parseFloat(entry.gross_wage) || 0).toFixed(2)}</p>
                    </div>

                    <div className="p-4 bg-secondary-50 rounded-xl border border-secondary-200">
                      <div className="flex items-center gap-2 mb-2">
                        <TrendingUp className="w-4 h-4 text-secondary-600" />
                        <p className="text-sm font-medium text-secondary-900">Stundenlohn</p>
                      </div>
                      <p className="text-lg font-semibold text-secondary-600">
                        €{(parseFloat(entry.override_hourly_rate) || parseFloat(entry.employee?.hourly_rate) || 0).toFixed(2)}
                      </p>
                    </div>

                    {entry.employee?.cash_payment && (
                      <div className="p-4 bg-white rounded-xl border border-neutral-200 flex items-center justify-center">
                        <Button
                          variant={entry.paid_out_at ? "outline" : "primary"}
                          size="sm"
                          onClick={() => togglePaidStatus(entry.id, entry.paid_out_at)}
                          disabled={processingPaid[entry.id]}
                          icon={entry.paid_out_at ? <CheckCircle className="w-4 h-4" /> : <Circle className="w-4 h-4" />}
                        >
                          {entry.paid_out_at ? 'Ausgezahlt' : 'Auszahlen'}
                        </Button>
                      </div>
                    )}
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Expenses Modal */}
      {showExpensesModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-neutral-200">
              <h2 className="text-2xl font-bold text-neutral-900">Schichtende - Barausgaben</h2>
              <p className="text-sm text-neutral-600 mt-1">Erfassen Sie zusätzliche Barausgaben für diesen Tag</p>
            </div>

            <div className="p-6 space-y-6">
              {/* Einkäufe */}
              <div>
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-lg font-semibold text-neutral-900">Einkäufe</h3>
                  <Button size="sm" variant="outline" onClick={() => addExpense('purchases')}>
                    + Hinzufügen
                  </Button>
                </div>
                {expenses.purchases.map((purchase, index) => (
                  <div key={index} className="flex gap-2 mb-2">
                    <Input
                      type="number"
                      step="0.01"
                      placeholder="Betrag (€)"
                      value={purchase.amount}
                      onChange={(e) => updateExpense('purchases', index, 'amount', e.target.value)}
                      className="w-32"
                    />
                    <Input
                      placeholder="An: (z.B. REWE)"
                      value={purchase.recipient}
                      onChange={(e) => updateExpense('purchases', index, 'recipient', e.target.value)}
                      className="flex-1"
                    />
                    <Button size="sm" variant="outline" onClick={() => removeExpense('purchases', index)}>
                      ✕
                    </Button>
                  </div>
                ))}
              </div>

              {/* Auslagen */}
              <div>
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-lg font-semibold text-neutral-900">Auslagen</h3>
                  <Button size="sm" variant="outline" onClick={() => addExpense('advances')}>
                    + Hinzufügen
                  </Button>
                </div>
                {expenses.advances.map((advance, index) => (
                  <div key={index} className="flex gap-2 mb-2">
                    <Input
                      type="number"
                      step="0.01"
                      placeholder="Betrag (€)"
                      value={advance.amount}
                      onChange={(e) => updateExpense('advances', index, 'amount', e.target.value)}
                      className="w-32"
                    />
                    <Input
                      placeholder="An: (z.B. Max Mustermann)"
                      value={advance.recipient}
                      onChange={(e) => updateExpense('advances', index, 'recipient', e.target.value)}
                      className="flex-1"
                    />
                    <Button size="sm" variant="outline" onClick={() => removeExpense('advances', index)}>
                      ✕
                    </Button>
                  </div>
                ))}
              </div>

              {/* Sonstige Entnahmen */}
              <div>
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-lg font-semibold text-neutral-900">Sonstige Entnahmen</h3>
                  <Button size="sm" variant="outline" onClick={() => addExpense('other')}>
                    + Hinzufügen
                  </Button>
                </div>
                {expenses.other.map((other, index) => (
                  <div key={index} className="flex gap-2 mb-2">
                    <Input
                      type="number"
                      step="0.01"
                      placeholder="Betrag (€)"
                      value={other.amount}
                      onChange={(e) => updateExpense('other', index, 'amount', e.target.value)}
                      className="w-32"
                    />
                    <Input
                      placeholder="Grund: (z.B. Reparatur)"
                      value={other.reason}
                      onChange={(e) => updateExpense('other', index, 'reason', e.target.value)}
                      className="flex-1"
                    />
                    <Button size="sm" variant="outline" onClick={() => removeExpense('other', index)}>
                      ✕
                    </Button>
                  </div>
                ))}
              </div>
            </div>

            <div className="p-6 border-t border-neutral-200 flex justify-end gap-3">
              <Button variant="outline" onClick={() => setShowExpensesModal(false)}>
                Abbrechen
              </Button>
              <Button variant="primary" onClick={generateShiftEndReport} icon={<FileText className="w-4 h-4" />}>
                PDF erstellen
              </Button>
            </div>
          </div>
        </div>
      )}
    </TimeTrackingLayout>
  );
}