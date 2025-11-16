import React, { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, CardTitle } from '@/Components/ui/Card';
import Button from '@/Components/ui/Button';
import { useToast } from '@/Components/ui/Toast';
import axios from 'axios';
import { Save } from 'lucide-react';

export default function GeneralSettings() {
  const [closedDays, setClosedDays] = useState([]);
  const [loading, setLoading] = useState(true);
  const toast = useToast();

  const weekDays = [
    { value: 1, label: 'Montag' },
    { value: 2, label: 'Dienstag' },
    { value: 3, label: 'Mittwoch' },
    { value: 4, label: 'Donnerstag' },
    { value: 5, label: 'Freitag' },
    { value: 6, label: 'Samstag' },
    { value: 0, label: 'Sonntag' },
  ];

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const res = await axios.get('/api/settings');
      setClosedDays(res.data.closed_days || []);
    } catch (error) {
      console.error('Error loading settings:', error);
      toast.error('Fehler beim Laden der Einstellungen');
    } finally {
      setLoading(false);
    }
  };

  const toggleDay = (day) => {
    if (closedDays.includes(day)) {
      setClosedDays(closedDays.filter(d => d !== day));
    } else {
      setClosedDays([...closedDays, day]);
    }
  };

  const handleSave = async () => {
    try {
      await axios.post('/api/settings', {
        closed_days: closedDays,
      });
      toast.success('Einstellungen gespeichert');
    } catch (error) {
      console.error('Error saving settings:', error);
      toast.error('Fehler beim Speichern');
    }
  };

  if (loading) {
    return <div className="p-6">Laden...</div>;
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Betriebswoche</CardTitle>
          <p className="text-sm text-neutral-600 mt-2">
            Wählen Sie die Tage aus, an denen Ihr Betrieb geschlossen ist. 
            Diese Tage werden im Schichtplan ausgeblendet.
          </p>
        </CardHeader>
        <CardBody>
          <div className="space-y-3">
            {weekDays.map((day) => (
              <label
                key={day.value}
                className="flex items-center gap-3 p-3 border border-neutral-200 rounded-lg hover:bg-neutral-50 cursor-pointer transition-colors"
              >
                <input
                  type="checkbox"
                  checked={closedDays.includes(day.value)}
                  onChange={() => toggleDay(day.value)}
                  className="w-4 h-4 text-primary-600 border-neutral-300 rounded focus:ring-primary-500"
                />
                <span className="text-sm font-medium text-neutral-900">{day.label}</span>
                {closedDays.includes(day.value) && (
                  <span className="ml-auto text-xs text-neutral-500">Geschlossen</span>
                )}
              </label>
            ))}
          </div>

          <div className="mt-6 flex justify-end">
            <Button
              variant="primary"
              onClick={handleSave}
              icon={<Save className="w-4 h-4" />}
            >
              Speichern
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}