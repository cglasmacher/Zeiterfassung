import React, { useState } from 'react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { Card, CardBody } from '@/Components/ui/Card';
import { Building2, Users, Clock, Settings as SettingsIcon } from 'lucide-react';
import DepartmentsSettings from './Settings/DepartmentsSettings';
import ShiftTypesSettings from './Settings/ShiftTypesSettings';
import EmployeesSettings from './Settings/EmployeesSettings';

export default function Settings() {
  const [activeTab, setActiveTab] = useState('departments');

  const tabs = [
    { id: 'departments', label: 'Stationen', icon: Building2 },
    { id: 'shift-types', label: 'Schichttypen', icon: Clock },
    { id: 'employees', label: 'Mitarbeiter', icon: Users },
  ];

  return (
    <TimeTrackingLayout>
      <div className="space-y-6 animate-fade-in">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
              <SettingsIcon className="w-6 h-6 text-primary-600" />
            </div>
            <div>
              <h1 className="heading-2">Einstellungen</h1>
              <p className="text-small mt-1">
                Verwalten Sie Stationen, Schichttypen und Mitarbeiter
              </p>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <Card glass>
          <CardBody className="p-2">
            <div className="flex items-center gap-1 bg-neutral-100 p-1 rounded-lg">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`flex-1 px-4 py-2.5 rounded-md transition-all flex items-center justify-center gap-2 font-medium ${
                      activeTab === tab.id
                        ? 'bg-white shadow-sm text-primary-600'
                        : 'text-neutral-600 hover:text-neutral-900'
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                    <span>{tab.label}</span>
                  </button>
                );
              })}
            </div>
          </CardBody>
        </Card>

        {/* Tab Content */}
        <div>
          {activeTab === 'departments' && <DepartmentsSettings />}
          {activeTab === 'shift-types' && <ShiftTypesSettings />}
          {activeTab === 'employees' && <EmployeesSettings />}
        </div>
      </div>
    </TimeTrackingLayout>
  );
}