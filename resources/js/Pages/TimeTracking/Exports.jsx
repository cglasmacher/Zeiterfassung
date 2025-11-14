import React, { useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { useToast } from '@/Components/ui/Toast';
import { Download, FileText, Users, Euro, Clock, AlertCircle } from 'lucide-react';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/de';

dayjs.locale('de');

export default function Exports() {
  const [startDate, setStartDate] = useState(dayjs().startOf('month').format('YYYY-MM-DD'));
  const [endDate, setEndDate] = useState(dayjs().endOf('month').format('YYYY-MM-DD'));
  const [preview, setPreview] = useState(null);
  const [loading, setLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const toast = useToast();

  const handlePreview = async () => {
    setLoading(true);
    try {
      const res = await axios.post('/api/lexware-export/preview', {
        start_date: startDate,
        end_date: endDate,
      });
      setPreview(res.data);
      toast.success('Vorschau geladen');
    } catch (error) {
      console.error('Error loading preview:', error);
      toast.error('Fehler beim Laden der Vorschau');
    } finally {
      setLoading(false);
    }
  };

  const handleExport = async (format) => {
    setExporting(true);
    try {
      const response = await axios.post('/api/lexware-export/export', {
        start_date: startDate,
        end_date: endDate,
        format: format,
      }, {
        responseType: 'blob',
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      
      const filename = format === 'csv' 
        ? `lexoffice_export_${startDate}_bis_${endDate}.csv`
        : `datev_export_${startDate}_bis_${endDate}.csv`;
      
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success(`${format.toUpperCase()}-Datei erfolgreich heruntergeladen`);
    } catch (error) {
      console.error('Error exporting:', error);
      toast.error('Fehler beim Export');
    } finally {
      setExporting(false);
    }
  };

  const setCurrentMonth = () => {
    setStartDate(dayjs().startOf('month').format('YYYY-MM-DD'));
    setEndDate(dayjs().endOf('month').format('YYYY-MM-DD'));
  };

  const setPreviousMonth = () => {
    const prevMonth = dayjs().subtract(1, 'month');
    setStartDate(prevMonth.startOf('month').format('YYYY-MM-DD'));
    setEndDate(prevMonth.endOf('month').format('YYYY-MM-DD'));
  };

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
              <Download className="w-6 h-6 text-primary-600" />
            </div>
            <div>
              <h1 className="heading-2">Lexware Export</h1>
              <p className="text-small mt-1">
                Export für Aushilfen und Teilzeitlöhner
              </p>
            </div>
          </div>
        </div>

        <div className="p-4 bg-primary-50 border border-primary-200 rounded-lg flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-primary-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-semibold text-primary-900">Hinweis</p>
            <p className="text-sm text-primary-700 mt-1">
              Es werden nur Aushilfen und Teilzeitlöhner exportiert. Festangestellte werden nicht berücksichtigt.
            </p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Zeitraum auswählen</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div className="flex flex-wrap items-end gap-3">
                <Input
                  type="date"
                  label="Von"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="flex-1 min-w-[200px]"
                />
                <Input
                  type="date"
                  label="Bis"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="flex-1 min-w-[200px]"
                />
                <div className="flex gap-2">
                  <Button variant="outline" size="md" onClick={setCurrentMonth}>
                    Aktueller Monat
                  </Button>
                  <Button variant="outline" size="md" onClick={setPreviousMonth}>
                    Vormonat
                  </Button>
                  <Button variant="primary" size="md" onClick={handlePreview} loading={loading}>
                    Vorschau laden
                  </Button>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        {preview && (
          <>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Card>
                <CardBody className="p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Mitarbeiter</p>
                      <p className="text-3xl font-bold text-primary-600">
                        {preview.summary.total_employees}
                      </p>
                    </div>
                    <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                      <Users className="w-6 h-6 text-primary-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody className="p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Gesamtstunden</p>
                      <p className="text-3xl font-bold text-secondary-600">
                        {preview.summary.total_hours.toFixed(2)}h
                      </p>
                    </div>
                    <div className="w-12 h-12 rounded-xl bg-secondary-100 flex items-center justify-center">
                      <Clock className="w-6 h-6 text-secondary-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody className="p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-tiny text-neutral-500 mb-1">Gesamtlohn</p>
                      <p className="text-3xl font-bold text-success-600">
                        {preview.summary.total_wage.toFixed(2)}€
                      </p>
                    </div>
                    <div className="w-12 h-12 rounded-xl bg-success-100 flex items-center justify-center">
                      <Euro className="w-6 h-6 text-success-600" />
                    </div>
                  </div>
                </CardBody>
              </Card>
            </div>

            <Card>
              <CardHeader>
                <CardTitle>Export-Format wählen</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="flex flex-wrap gap-3">
                  <Button
                    variant="primary"
                    size="lg"
                    icon={<FileText className="w-5 h-5" />}
                    onClick={() => handleExport('csv')}
                    loading={exporting}
                  >
                    Als CSV exportieren (LexOffice)
                  </Button>
                  <Button
                    variant="secondary"
                    size="lg"
                    icon={<FileText className="w-5 h-5" />}
                    onClick={() => handleExport('datev')}
                    loading={exporting}
                  >
                    Als DATEV exportieren
                  </Button>
                </div>
                <p className="text-sm text-neutral-600 mt-3">
                  Zeitraum: {preview.period.start} - {preview.period.end}
                </p>
              </CardBody>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Vorschau der zu exportierenden Daten</CardTitle>
              </CardHeader>
              <CardBody>
                {preview.preview.length === 0 ? (
                  <div className="text-center py-12">
                    <AlertCircle className="w-16 h-16 text-neutral-300 mx-auto mb-4" />
                    <p className="text-neutral-600">
                      Keine Aushilfen mit Zeiteinträgen im gewählten Zeitraum gefunden
                    </p>
                  </div>
                ) : (
                  <div className="overflow-x-auto custom-scrollbar">
                    <table className="w-full">
                      <thead className="bg-neutral-50 border-b-2 border-neutral-200">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Mitarbeiternummer
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Name
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Einträge
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Stunden
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Stundenlohn
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-700 uppercase">
                            Bruttolohn
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-neutral-200">
                        {preview.preview.map((item, index) => (
                          <tr key={index} className="hover:bg-neutral-50">
                            <td className="px-4 py-3">
                              <span className="font-mono text-sm text-neutral-900">
                                {item.employee.employee_number || '-'}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              <div>
                                <p className="font-medium text-neutral-900">
                                  {item.employee.first_name} {item.employee.last_name}
                                </p>
                                <Badge variant="secondary" className="text-xs mt-1">
                                  Aushilfe
                                </Badge>
                              </div>
                            </td>
                            <td className="px-4 py-3 text-sm text-neutral-900">
                              {item.entries_count}
                            </td>
                            <td className="px-4 py-3">
                              <span className="font-semibold text-primary-600">
                                {item.total_hours.toFixed(2)}h
                              </span>
                            </td>
                            <td className="px-4 py-3 text-sm text-neutral-900">
                              {item.employee.hourly_rate ? `${item.employee.hourly_rate}€/h` : '-'}
                            </td>
                            <td className="px-4 py-3">
                              <span className="font-bold text-success-600">
                                {item.total_wage.toFixed(2)}€
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                      <tfoot className="bg-neutral-50 border-t-2 border-neutral-200">
                        <tr>
                          <td colSpan="3" className="px-4 py-3 text-right font-semibold text-neutral-900">
                            Gesamt:
                          </td>
                          <td className="px-4 py-3">
                            <span className="font-bold text-primary-600">
                              {preview.summary.total_hours.toFixed(2)}h
                            </span>
                          </td>
                          <td className="px-4 py-3"></td>
                          <td className="px-4 py-3">
                            <span className="font-bold text-success-600">
                              {preview.summary.total_wage.toFixed(2)}€
                            </span>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                )}
              </CardBody>
            </Card>

            {preview.preview.some(item => !item.employee.employee_number) && (
              <div className="p-4 bg-warning-50 border border-warning-200 rounded-lg flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="font-semibold text-warning-900">Fehlende Mitarbeiternummern</p>
                  <p className="text-sm text-warning-700 mt-1">
                    Einige Mitarbeiter haben keine Mitarbeiternummer. Bitte ergänzen Sie diese in den Einstellungen.
                  </p>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </TimeTrackingLayout>
  );
}