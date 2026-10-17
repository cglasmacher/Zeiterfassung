import React, { useCallback, useEffect, useState } from "react";
import { Link } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowUpRight,
    ArrowRight,
    RefreshCw,
    Clock3,
    Users,
    CalendarRange,
    ReceiptText,
    Wallet,
    Check,
    CircleAlert,
} from "lucide-react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import {
    formatHours,
    formatMoney,
    formatTime,
    formatDate,
} from "@/lib/navigation";

function Section({ title, subtitle, action, children, className = "" }) {
    return (
        <section className={`card ${className}`}>
            <div className="section-heading">
                <div>
                    <h2>{title}</h2>
                    {subtitle && <p>{subtitle}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
function Empty({ children }) {
    return <div className="empty-inline">{children}</div>;
}
export default function Dashboard() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const load = useCallback(async (signal) => {
        try {
            const response = await axios.get("/api/dashboard", { signal });
            setData(response.data);
            setError("");
        } catch (err) {
            if (!axios.isCancel(err))
                setError("Die Übersicht konnte nicht aktualisiert werden.");
        } finally {
            if (!signal?.aborted) setLoading(false);
        }
    }, []);
    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal);
        const timer = setInterval(() => load(controller.signal), 60000);
        return () => {
            controller.abort();
            clearInterval(timer);
        };
    }, [load]);
    const stats = data?.stats;
    return (
        <TimeTrackingLayout>
            <div className="space-y-6">
                <div className="page-heading">
                    <div>
                        <p className="eyebrow">Betriebsübersicht</p>
                        <h1>Heute im Betrieb</h1>
                        <p>Team, Dienstplan und Kasse an einem Ort.</p>
                    </div>
                    <div className="page-actions">
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={
                                <RefreshCw
                                    size={15}
                                    className={loading ? "animate-spin" : ""}
                                />
                            }
                            onClick={() => {
                                setLoading(true);
                                load();
                            }}
                            disabled={loading}
                        >
                            Aktualisieren
                        </Button>
                        <Link
                            className="btn-primary inline-flex items-center gap-2"
                            href={route("cash.handovers")}
                        >
                            Schichtübergabe
                            <ArrowUpRight size={16} />
                        </Link>
                    </div>
                </div>
                {error && (
                    <div className="notice notice-error" role="alert">
                        {error}
                        {data &&
                            " Die zuletzt geladenen Daten bleiben sichtbar."}
                    </div>
                )}
                {!data && loading ? (
                    <div
                        className="card p-12 text-center text-sm text-neutral-600"
                        role="status"
                    >
                        Betriebsdaten werden geladen …
                    </div>
                ) : (
                    data && (
                        <>
                            <div className="metric-grid">
                                {[
                                    [
                                        "Eingestempelt",
                                        stats.clocked_in,
                                        `${stats.active_employees} ${stats.active_employees === 1 ? "aktiver Mitarbeiter" : "aktive Mitarbeiter"}`,
                                        Users,
                                    ],
                                    [
                                        "Stunden heute",
                                        formatHours(
                                            stats.completed_hours_today,
                                        ),
                                        "Abgeschlossene Einträge mit Beginn heute",
                                        Clock3,
                                    ],
                                    [
                                        "Schichten heute",
                                        stats.shifts_today,
                                        stats.unassigned_shifts_today
                                            ? `${stats.unassigned_shifts_today} noch unbesetzt`
                                            : "Dienstplan für heute",
                                        CalendarRange,
                                    ],
                                    [
                                        "Offene Abschlüsse",
                                        stats.open_cash_days,
                                        "Noch nicht freigegebene Geschäftstage",
                                        ReceiptText,
                                    ],
                                ].map(([label, value, caption, Icon]) => (
                                    <div className="metric-card" key={label}>
                                        <div className="flex justify-between items-center">
                                            <span>{label}</span>
                                            <Icon size={17} strokeWidth={1.6} />
                                        </div>
                                        <strong>{value}</strong>
                                        <p>{caption}</p>
                                    </div>
                                ))}
                            </div>
                            <div className="dashboard-columns">
                                <div className="space-y-5">
                                    <Section
                                        title="Aktuell eingestempelt"
                                        subtitle="Offene Zeiteinträge, auch vom Vortag"
                                        action={
                                            <Link
                                                className="text-link"
                                                href={route(
                                                    "timetracking.manual",
                                                )}
                                            >
                                                Zeiten erfassen
                                                <ArrowUpRight size={14} />
                                            </Link>
                                        }
                                    >
                                        {data.attendance.length ? (
                                            <div className="divide-y divide-neutral-100">
                                                {data.attendance.map(
                                                    (person) => (
                                                        <div
                                                            className="person-row"
                                                            key={person.id}
                                                        >
                                                            <span className="person-avatar">
                                                                {person.name
                                                                    .split(" ")
                                                                    .map(
                                                                        (p) =>
                                                                            p[0],
                                                                    )
                                                                    .slice(0, 2)
                                                                    .join("")}
                                                            </span>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="font-medium truncate">
                                                                    {
                                                                        person.name
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-neutral-600 mt-1">
                                                                    Seit{" "}
                                                                    {formatTime(
                                                                        person.clock_in,
                                                                    )}{" "}
                                                                    Uhr
                                                                    {formatDate(
                                                                        person.clock_in,
                                                                    ) !==
                                                                    formatDate(
                                                                        data.updated_at,
                                                                    )
                                                                        ? ` · ${formatDate(person.clock_in)}`
                                                                        : ""}
                                                                </p>
                                                            </div>
                                                            <span className="status-pill">
                                                                <span />
                                                                Eingestempelt
                                                            </span>
                                                            <span className="tabular-nums text-sm text-neutral-700 w-20 text-right">
                                                                {Math.floor(
                                                                    person.elapsed_minutes /
                                                                        60,
                                                                )}
                                                                :
                                                                {String(
                                                                    person.elapsed_minutes %
                                                                        60,
                                                                ).padStart(
                                                                    2,
                                                                    "0",
                                                                )}{" "}
                                                                Std.
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <Empty>
                                                Zurzeit ist niemand
                                                eingestempelt.
                                            </Empty>
                                        )}
                                    </Section>
                                    <Section
                                        title="Dienstplan heute"
                                        action={
                                            <Link
                                                className="text-link"
                                                href={route(
                                                    "timetracking.planner",
                                                )}
                                            >
                                                Wochenplan
                                                <ArrowUpRight size={14} />
                                            </Link>
                                        }
                                    >
                                        {data.shifts.length ? (
                                            <div className="overflow-x-auto">
                                                <table className="data-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Mitarbeiter</th>
                                                            <th>Bereich</th>
                                                            <th>Schicht</th>
                                                            <th className="text-right">
                                                                Zeit
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {data.shifts.map(
                                                            (shift) => (
                                                                <tr
                                                                    key={
                                                                        shift.id
                                                                    }
                                                                >
                                                                    <td>
                                                                        <span
                                                                            className={
                                                                                shift.name
                                                                                    ? "font-medium"
                                                                                    : "text-amber-700"
                                                                            }
                                                                        >
                                                                            {shift.name ||
                                                                                "Noch unbesetzt"}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        {shift.department ||
                                                                            "—"}
                                                                    </td>
                                                                    <td>
                                                                        {shift.type ||
                                                                            "—"}
                                                                    </td>
                                                                    <td className="text-right whitespace-nowrap tabular-nums">
                                                                        {
                                                                            shift.start
                                                                        }
                                                                        –
                                                                        {
                                                                            shift.end
                                                                        }
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <Empty>
                                                Für heute sind keine Schichten
                                                geplant.
                                                <Link
                                                    href={route(
                                                        "timetracking.planner",
                                                    )}
                                                    className="text-link mt-3"
                                                >
                                                    Dienstplan öffnen
                                                    <ArrowRight size={14} />
                                                </Link>
                                            </Empty>
                                        )}
                                    </Section>
                                </div>
                                <div className="space-y-5">
                                    <section className="cash-status-card">
                                        <div className="flex items-center justify-between">
                                            <span className="eyebrow">
                                                Gemeinsame Kasse
                                            </span>
                                            <Wallet
                                                size={19}
                                                strokeWidth={1.5}
                                            />
                                        </div>
                                        <p className="cash-status-label">
                                            Zuletzt bestätigter Bestand
                                        </p>
                                        <strong>
                                            {data.cash.balance_cents === null
                                                ? "Noch nicht erfasst"
                                                : formatMoney(
                                                      data.cash.balance_cents,
                                                  )}
                                        </strong>
                                        <p className="text-sm">
                                            {data.cash.open_business_date
                                                ? `Offener Geschäftstag: ${formatDate(data.cash.open_business_date)}`
                                                : data.cash.last_business_date
                                                  ? `Letzter Abschluss: ${formatDate(data.cash.last_business_date)}`
                                                  : "Ersten Geschäftstag eröffnen und Bargeld zählen."}
                                        </p>
                                        <Link href={route("cash.days")}>
                                            Zum Tagesabschluss
                                            <ArrowUpRight size={16} />
                                        </Link>
                                    </section>
                                    <Section title="Zu erledigen">
                                        {data.alerts.length ? (
                                            <div className="px-5 divide-y divide-neutral-100">
                                                {data.alerts.map((alert, i) => (
                                                    <Link
                                                        href={alert.href}
                                                        className="task-row"
                                                        key={i}
                                                    >
                                                        <CircleAlert
                                                            size={16}
                                                            className="text-amber-600 shrink-0 mt-0.5"
                                                        />
                                                        <div>
                                                            <p className="font-medium text-sm">
                                                                {alert.title}
                                                            </p>
                                                            <p className="text-xs text-neutral-600 mt-1 leading-relaxed">
                                                                {alert.message}
                                                            </p>
                                                        </div>
                                                        <ChevronArrow />
                                                    </Link>
                                                ))}
                                            </div>
                                        ) : (
                                            <Empty>
                                                <Check
                                                    size={18}
                                                    className="text-primary-600 mb-2"
                                                />
                                                Zurzeit keine offenen Hinweise.
                                            </Empty>
                                        )}
                                    </Section>
                                    <Section title="Zuletzt bearbeitet">
                                        {data.activities.length ? (
                                            <div className="px-5 divide-y divide-neutral-100">
                                                {data.activities.map(
                                                    (activity) => (
                                                        <Link
                                                            href={activity.href}
                                                            className="activity-row"
                                                            key={activity.id}
                                                        >
                                                            <span className="activity-dot" />
                                                            <div>
                                                                <p className="text-sm font-medium">
                                                                    {
                                                                        activity.name
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-neutral-600 mt-1">
                                                                    {
                                                                        activity.action
                                                                    }
                                                                </p>
                                                            </div>
                                                            <time className="text-xs text-neutral-500 ml-auto whitespace-nowrap">
                                                                {formatDate(
                                                                    activity.at,
                                                                ) ===
                                                                formatDate(
                                                                    data.updated_at,
                                                                )
                                                                    ? formatTime(
                                                                          activity.at,
                                                                      )
                                                                    : formatDate(
                                                                          activity.at,
                                                                      )}
                                                            </time>
                                                        </Link>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <Empty>
                                                Noch keine Aktivitäten
                                                vorhanden.
                                            </Empty>
                                        )}
                                    </Section>
                                </div>
                            </div>
                            <p className="text-xs text-neutral-500">
                                Stand {formatTime(data.updated_at)} Uhr ·
                                Aktualisierung jede Minute
                            </p>
                            {stats.active_employees === 0 && (
                                <div className="notice">
                                    Noch kein aktives Team hinterlegt.
                                    <Link
                                        className="text-link ml-2"
                                        href={route("timetracking.settings", {
                                            tab: "employees",
                                        })}
                                    >
                                        Mitarbeiter anlegen
                                        <ArrowRight size={14} />
                                    </Link>
                                </div>
                            )}
                        </>
                    )
                )}
            </div>
        </TimeTrackingLayout>
    );
}
function ChevronArrow() {
    return (
        <ArrowUpRight size={14} className="ml-auto text-neutral-400 shrink-0" />
    );
}
