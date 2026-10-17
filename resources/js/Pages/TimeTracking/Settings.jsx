import { Link } from "@inertiajs/react";
import React, { useState, useEffect } from "react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import { Card, CardBody } from "@/Components/ui/Card";
import {
    Building2,
    Users,
    Clock,
    Settings as SettingsIcon,
    CalendarDays,
} from "lucide-react";
import DepartmentsSettings from "./Settings/DepartmentsSettings";
import ShiftTypesSettings from "./Settings/ShiftTypesSettings";
import EmployeesSettings from "./Settings/EmployeesSettings";
import GeneralSettings from "./Settings/GeneralSettings";

export default function Settings({ initialTab = "general" }) {
    const [activeTab, setActiveTab] = useState(initialTab);

    useEffect(() => {
        setActiveTab(initialTab);
    }, [initialTab]);

    const tabs = [
        { id: "general", label: "Allgemein", icon: SettingsIcon },
        { id: "departments", label: "Stationen", icon: Building2 },
        { id: "shift-types", label: "Schichttypen", icon: Clock },
        { id: "employees", label: "Mitarbeiter", icon: Users },
    ];

    return (
        <TimeTrackingLayout>
            <div className="space-y-6 animate-fade-in">
                <div className="page-heading">
                    <div>
                        <p className="eyebrow">Verwaltung</p>
                        <h1>Einstellungen</h1>
                        <p>Team, Schichttypen und Betriebseinstellungen.</p>
                    </div>
                </div>

                {/* Tabs */}
                <Card glass>
                    <CardBody className="p-2">
                        <div className="flex flex-wrap items-center gap-1 bg-neutral-100 p-1 rounded-lg">
                            {tabs.map((tab) => {
                                const Icon = tab.icon;
                                return (
                                    <Link
                                        key={tab.id}
                                        href={route("timetracking.settings", {
                                            tab: tab.id,
                                        })}
                                        preserveScroll
                                        className={`flex-1 px-4 py-2.5 rounded-md transition-all flex items-center justify-center gap-2 font-medium ${
                                            activeTab === tab.id
                                                ? "bg-white shadow-sm text-primary-600"
                                                : "text-neutral-600 hover:text-neutral-900"
                                        }`}
                                    >
                                        <Icon className="w-4 h-4" />
                                        <span>{tab.label}</span>
                                    </Link>
                                );
                            })}
                        </div>
                    </CardBody>
                </Card>

                {/* Tab Content */}
                <div>
                    {activeTab === "general" && <GeneralSettings />}
                    {activeTab === "departments" && <DepartmentsSettings />}
                    {activeTab === "shift-types" && <ShiftTypesSettings />}
                    {activeTab === "employees" && <EmployeesSettings />}
                </div>
            </div>
        </TimeTrackingLayout>
    );
}
