import React, { useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle, CardDescription } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Badge from '@/Components/ui/Badge';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';
import { 
  Download, 
  FileText, 
  CheckCircle,
  Clock,
  Calendar,
  AlertCircle
} from 'lucide-react';

dayjs.locale('de');

export default function Exports() {
  const [month, setMonth] = useState(dayjs().month() + 1);
  const [year, setYear] = useState(dayjs().year());
  const [exporting, setExporting] = useState(false);
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState('');
  const [exportHistory, setExportHistory] = useState([
    { id: 1, filename: 'lexware_2024_09.csv', date: '2024-09-30', status: 'success' },
    { id: 2, filename: 'lexware_2024_08.csv', date: '2024-08-31', status: 'success' },
    { id: 3, filename: 'lexware_2024_07.csv', date: '2024-07-31', status: 'success' },
  ]);

  const handleExport = async () => {
    setExporting(true);
    setMessage('');
    
    try {
      const res = await axios.post('/api/exports/run', { month, year });
      setMessage(`Export erfolgreich erstellt: ${res.data.filename}`);
      setMessageType('success');
      
      // Add to history
      setExportHistory(prev => [{
        id: Date.now(),
        filename: res.data.filename,
        date: dayjs().format('YYYY-MM-DD'),
        status: 'success'
      }, ...prev]);
    } catch (e) {
      setMessage('Fehler beim Export. Bitte versuchen Sie es erneut.');
      setMessageType('error');
    } finally {
      setExporting(false);
    }
  };

  const monthName = dayjs().month(month - 1).format('MMMM');

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div>
          <h1 className="heading-2">Exporte</h1>
          <p className="text-small mt-1">
            Exportieren Sie Zeiterfassungsdaten für Lexware und andere Systeme
          </p>
        </div>

        {/* Export Form */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Download className="w-5 h-5 text-primary-500" />
                  Neuer Export
                </CardTitle>
                <CardDescription>
                  Wählen Sie den Zeitraum für den Export
                </CardDescription>
              </CardHeader>
              <CardBody>
                <div className="space-y-6">
                  {/* Period Selection */}
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-neutral-700 mb-2">
                        Monat
                      </label>
                      <select 
                        className="input"
                        value={month}
                        onChange={(e) => setMonth(parseInt(e.target.value))}
                      >
                        {Array.from({ length: 12 }, (_, i) => (
                          <option key={i + 1} value={i + 1}>
                            {dayjs().month(i).format('MMMM')}
                          </option>
                        ))}
                      </select>
                    </div>
                    
                    <div>
                      <label className="block text-sm font-medium text-neutral-700 mb-2">
                        Jahr
                      </label>
                      <input 
                        type="number" 
                        className="input"
                        value={year}
                        onChange={(e) => setYear(parseInt(e.target.value))}
                      />
                    </div>
                  </div>

                  {/* Export Preview */}
                  <div className="p-4 bg-neutral-50 rounded-xl border border-neutral-200">
                    <div className="flex items-start gap-3">
                      <FileText className="w-5 h-5 text-neutral-500 mt-0.5" />
                      <div className="flex-1">
                        <p className="text-sm font-medium text-neutral-900 mb-1">
                          Export-Vorschau
                        </p>
                        <p className="text-sm text-neutral-600">
                          Dateiname: <span className="font-mono">lexware_{year}_{month.toString().padStart(2, '0')}.csv</span>
                        </p>
                        <p className="text-sm text-neutral-600">
                          Zeitraum: {monthName} {year}
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Message */}
                  {message && (
                    <div className={`p-4 rounded-xl border ${
                      messageType === 'success' 
                        ? 'bg-success-50 border-success-200' 
                        : 'bg-error-50 border-error-200'
                    }`}>
                      <div className="flex items-start gap-3">
                        {messageType === 'success' ? (
                          <CheckCircle className="w-5 h-5 text-success-600 mt-0.5" />
                        ) : (
                          <AlertCircle className="w-5 h-5 text-error-600 mt-0.5" />
                        )}
                        <p className={`text-sm font-medium ${
                          messageType === 'success' ? 'text-success-900' : 'text-error-900'
                        }`}>
                          {message}
                        </p>
                      </div>
                    </div>
                  )}

                  {/* Action Button */}
                  <Button
                    onClick={handleExport}
                    loading={exporting}
                    disabled={exporting}
                    variant="primary"
                    className="w-full"
                    icon={<Download className="w-4 h-4" />}
                  >
                    {exporting ? 'Export läuft...' : 'Lexware-Export starten'}
                  </Button>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* Info Sidebar */}
          <div className="space-y-6">
            <Card className="border-l-4 border-primary-500">
              <CardHeader>
                <CardTitle className="text-base">Export-Informationen</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="space-y-3 text-sm">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-success-500 mt-0.5 flex-shrink-0" />
                    <p className="text-neutral-700">CSV-Format für Lexware</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-success-500 mt-0.5 flex-shrink-0" />
                    <p className="text-neutral-700">Alle Arbeitsstunden inkl. Pausen</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-success-500 mt-0.5 flex-shrink-0" />
                    <p className="text-neutral-700">Automatische Berechnung</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-success-500 mt-0.5 flex-shrink-0" />
                    <p className="text-neutral-700">DSGVO-konform</p>
                  </div>
                </div>
              </CardBody>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Schnellzugriff</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="space-y-2">
                  <Button 
                    variant="outline" 
                    size="sm" 
                    className="w-full justify-start"
                    icon={<Calendar className="w-4 h-4" />}
                  >
                    Aktueller Monat
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    className="w-full justify-start"
                    icon={<Calendar className="w-4 h-4" />}
                  >
                    Letzter Monat
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    className="w-full justify-start"
                    icon={<FileText className="w-4 h-4" />}
                  >
                    Jahresexport
                  </Button>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>

        {/* Export History */}
        <Card>
          <CardHeader>
            <CardTitle>Export-Verlauf</CardTitle>
            <CardDescription>Ihre letzten Exporte</CardDescription>
          </CardHeader>
          <CardBody>
            {exportHistory.length === 0 ? (
              <div className="text-center py-12">
                <FileText className="w-12 h-12 text-neutral-300 mx-auto mb-3" />
                <p className="text-neutral-600">Noch keine Exporte vorhanden</p>
              </div>
            ) : (
              <div className="space-y-3">
                {exportHistory.map((exp) => (
                  <div 
                    key={exp.id}
                    className="flex items-center justify-between p-4 bg-neutral-50 rounded-xl hover:bg-neutral-100 transition-colors"
                  >
                    <div className="flex items-center gap-4">
                      <div className="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <FileText className="w-5 h-5 text-primary-600" />
                      </div>
                      <div>
                        <p className="font-medium text-neutral-900">{exp.filename}</p>
                        <p className="text-sm text-neutral-500">
                          {dayjs(exp.date).format('DD. MMMM YYYY, HH:mm')} Uhr
                        </p>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-3">
                      <Badge variant={exp.status === 'success' ? 'success' : 'error'}>
                        {exp.status === 'success' ? 'Erfolgreich' : 'Fehler'}
                      </Badge>
                      <Button variant="ghost" size="sm" icon={<Download className="w-4 h-4" />}>
                        Download
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </TimeTrackingLayout>
  );
}