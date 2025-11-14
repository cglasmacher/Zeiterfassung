import React, { useEffect, useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody, CardHeader, CardTitle, CardDescription } from '@/Components/ui/Card';
import Badge from '@/Components/ui/Badge';
import axios from 'axios';
import { 
  Users, 
  Clock, 
  TrendingUp, 
  Calendar,
  CheckCircle,
  XCircle,
  AlertCircle,
  DollarSign,
  Activity,
  UserCheck,
  UserX
} from 'lucide-react';

export default function Dashboard() {
  const [summary, setSummary] = useState(null);
  const [stats, setStats] = useState({
    activeEmployees: 0,
    totalHoursToday: 0,
    scheduledShifts: 0,
    weeklyOvertime: 0,
    presentToday: 0,
    absentToday: 0,
  });

  useEffect(() => {
    // Load current summary
    axios.get('/api/summary/current?rfid_tag=ABCD1234').then(res => setSummary(res.data));
    
    // Simulate loading stats (replace with real API calls)
    setStats({
      activeEmployees: 24,
      totalHoursToday: 156.5,
      scheduledShifts: 18,
      weeklyOvertime: 12.3,
      presentToday: 15,
      absentToday: 3,
    });
  }, []);

  const StatCard = ({ title, value, subtitle, icon: Icon, color = 'primary', trend = null }) => (
    <Card className="overflow-hidden">
      <CardBody className="p-6">
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-neutral-600 mb-1">{title}</p>
            <h3 className="text-3xl font-bold text-neutral-900 mb-1">{value}</h3>
            {subtitle && <p className="text-sm text-neutral-500">{subtitle}</p>}
            {trend && (
              <div className={`flex items-center gap-1 mt-2 text-sm ${trend > 0 ? 'text-success-600' : 'text-error-600'}`}>
                <TrendingUp className={`w-4 h-4 ${trend < 0 ? 'rotate-180' : ''}`} />
                <span className="font-medium">{Math.abs(trend)}%</span>
                <span className="text-neutral-500">vs. letzte Woche</span>
              </div>
            )}
          </div>
          <div className={`p-3 rounded-xl bg-${color}-100`}>
            <Icon className={`w-6 h-6 text-${color}-600`} />
          </div>
        </div>
      </CardBody>
    </Card>
  );

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard
            title="Anwesend Heute"
            value={stats.presentToday}
            subtitle={`von ${stats.activeEmployees} Mitarbeitern`}
            icon={UserCheck}
            color="success"
            trend={8.2}
          />
          <StatCard
            title="Arbeitsstunden Heute"
            value={`${stats.totalHoursToday}h`}
            subtitle="Gesamt Team"
            icon={Clock}
            color="primary"
            trend={5.1}
          />
          <StatCard
            title="Geplante Schichten"
            value={stats.scheduledShifts}
            subtitle="Diese Woche"
            icon={Calendar}
            color="accent"
          />
          <StatCard
            title="Überstunden (Woche)"
            value={`${stats.weeklyOvertime}h`}
            subtitle="Durchschnitt pro MA"
            icon={TrendingUp}
            color="warning"
            trend={-3.4}
          />
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Current Status */}
          <div className="lg:col-span-2 space-y-6">
            {/* Live Activity */}
            <Card glass>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>Live Aktivität</CardTitle>
                    <CardDescription>Aktuelle Anwesenheit</CardDescription>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-success-500 rounded-full animate-pulse"></div>
                    <span className="text-sm text-neutral-600">Live</span>
                  </div>
                </div>
              </CardHeader>
              <CardBody>
                {summary ? (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between p-4 bg-neutral-50 rounded-xl">
                      <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-lg">
                          {summary.employee?.charAt(0) || 'M'}
                        </div>
                        <div>
                          <p className="font-semibold text-neutral-900">{summary.employee}</p>
                          <p className="text-sm text-neutral-500">{summary.date}</p>
                        </div>
                      </div>
                      {summary.open_entry ? (
                        <Badge variant="success" icon={<CheckCircle className="w-3 h-3" />}>
                          Eingestempelt
                        </Badge>
                      ) : (
                        <Badge variant="neutral" icon={<XCircle className="w-3 h-3" />}>
                          Ausgestempelt
                        </Badge>
                      )}
                    </div>

                    {summary.open_entry && (
                      <div className="grid grid-cols-2 gap-4">
                        <div className="p-4 bg-success-50 rounded-xl border border-success-200">
                          <p className="text-sm text-success-700 font-medium mb-1">Eingestempelt seit</p>
                          <p className="text-2xl font-bold text-success-900">{summary.open_entry.clock_in}</p>
                        </div>
                        <div className="p-4 bg-primary-50 rounded-xl border border-primary-200">
                          <p className="text-sm text-primary-700 font-medium mb-1">Aktuelle Arbeitszeit</p>
                          <p className="text-2xl font-bold text-primary-900">
                            {Number(summary.open_entry.hours_since_in || 0).toFixed(2)} Std
                          </p>
                        </div>
                      </div>
                    )}

                    {summary.daily_summary && (
                      <div className="p-4 bg-accent-50 rounded-xl border border-accent-200">
                        <p className="text-sm text-accent-700 font-medium mb-1">Heutige Gesamtstunden</p>
                        <p className="text-2xl font-bold text-accent-900">
                          {Number(summary.daily_summary.total_hours || 0).toFixed(2)}h
                        </p>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
                  </div>
                )}
              </CardBody>
            </Card>

            {/* Quick Actions */}
            <Card>
              <CardHeader>
                <CardTitle>Schnellaktionen</CardTitle>
                <CardDescription>Häufig verwendete Funktionen</CardDescription>
              </CardHeader>
              <CardBody>
                <div className="grid grid-cols-2 gap-4">
                  <button className="p-4 border-2 border-dashed border-neutral-300 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 text-left group">
                    <Users className="w-8 h-8 text-neutral-400 group-hover:text-primary-500 mb-2" />
                    <p className="font-semibold text-neutral-900">Mitarbeiter hinzufügen</p>
                    <p className="text-sm text-neutral-500 mt-1">Neuen MA anlegen</p>
                  </button>
                  
                  <button className="p-4 border-2 border-dashed border-neutral-300 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 text-left group">
                    <Calendar className="w-8 h-8 text-neutral-400 group-hover:text-primary-500 mb-2" />
                    <p className="font-semibold text-neutral-900">Schicht planen</p>
                    <p className="text-sm text-neutral-500 mt-1">Neue Schicht erstellen</p>
                  </button>
                  
                  <button className="p-4 border-2 border-dashed border-neutral-300 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 text-left group">
                    <Activity className="w-8 h-8 text-neutral-400 group-hover:text-primary-500 mb-2" />
                    <p className="font-semibold text-neutral-900">Bericht erstellen</p>
                    <p className="text-sm text-neutral-500 mt-1">Auswertung generieren</p>
                  </button>
                  
                  <button className="p-4 border-2 border-dashed border-neutral-300 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 text-left group">
                    <DollarSign className="w-8 h-8 text-neutral-400 group-hover:text-primary-500 mb-2" />
                    <p className="font-semibold text-neutral-900">Lohnexport</p>
                    <p className="text-sm text-neutral-500 mt-1">Lexware Export</p>
                  </button>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Today's Absences */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <UserX className="w-5 h-5 text-error-500" />
                  Abwesenheiten
                </CardTitle>
                <CardDescription>Heute nicht anwesend</CardDescription>
              </CardHeader>
              <CardBody>
                <div className="space-y-3">
                  {[
                    { name: 'Max Mustermann', reason: 'Urlaub', color: 'primary' },
                    { name: 'Anna Schmidt', reason: 'Krank', color: 'error' },
                    { name: 'Tom Weber', reason: 'Frei', color: 'neutral' },
                  ].map((absence, i) => (
                    <div key={i} className="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-gradient-to-br from-neutral-300 to-neutral-400 flex items-center justify-center text-white text-sm font-semibold">
                          {absence.name.charAt(0)}
                        </div>
                        <div>
                          <p className="text-sm font-medium text-neutral-900">{absence.name}</p>
                          <p className="text-xs text-neutral-500">{absence.reason}</p>
                        </div>
                      </div>
                      <Badge variant={absence.color} className="text-xs">
                        {absence.reason}
                      </Badge>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>

            {/* Alerts */}
            <Card className="border-l-4 border-warning-500">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <AlertCircle className="w-5 h-5 text-warning-500" />
                  Hinweise
                </CardTitle>
              </CardHeader>
              <CardBody>
                <div className="space-y-3">
                  <div className="p-3 bg-warning-50 rounded-lg border border-warning-200">
                    <p className="text-sm font-medium text-warning-900">Offene Schichten</p>
                    <p className="text-xs text-warning-700 mt-1">3 Schichten für morgen noch nicht besetzt</p>
                  </div>
                  <div className="p-3 bg-error-50 rounded-lg border border-error-200">
                    <p className="text-sm font-medium text-error-900">Überstunden-Limit</p>
                    <p className="text-xs text-error-700 mt-1">2 Mitarbeiter über 40h/Woche</p>
                  </div>
                </div>
              </CardBody>
            </Card>

            {/* Recent Activity */}
            <Card>
              <CardHeader>
                <CardTitle>Letzte Aktivitäten</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="space-y-3">
                  {[
                    { action: 'Schicht erstellt', time: 'vor 5 Min', user: 'Admin' },
                    { action: 'MA eingestempelt', time: 'vor 12 Min', user: 'Lisa Müller' },
                    { action: 'Export erstellt', time: 'vor 1 Std', user: 'Admin' },
                  ].map((activity, i) => (
                    <div key={i} className="flex items-start gap-3">
                      <div className="w-2 h-2 bg-primary-500 rounded-full mt-2"></div>
                      <div className="flex-1">
                        <p className="text-sm font-medium text-neutral-900">{activity.action}</p>
                        <p className="text-xs text-neutral-500">{activity.user} • {activity.time}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
      </div>
    </TimeTrackingLayout>
  );
}