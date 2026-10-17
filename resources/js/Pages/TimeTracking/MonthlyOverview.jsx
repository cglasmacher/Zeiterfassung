import React, { useEffect, useState } from "react";
import { Link } from "@inertiajs/react";
import axios from "axios";
import dayjs from "dayjs";
import "dayjs/locale/de";
import { ChevronLeft, ChevronRight, ArrowUpRight } from "lucide-react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import {
    formatHours,
    formatMoney,
    formatDate,
    formatTime,
} from "@/lib/navigation";

dayjs.locale("de");
export default function MonthlyOverview() {
    const [period, setPeriod] = useState(dayjs().format("YYYY-MM"));
    const [employeeId, setEmployeeId] = useState("");
    const [employees, setEmployees] = useState([]);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        axios
            .get("/api/employees", { signal: controller.signal })
            .then((res) => setEmployees(res.data))
            .catch((err) => {
                if (!axios.isCancel(err))
                    setError(
                        "Die Mitarbeiterliste konnte nicht geladen werden.",
                    );
            });
        return () => controller.abort();
    }, []);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setData(null);
        setError("");
        const [year, month] = period.split("-").map(Number);
        axios
            .get("/api/summary/monthly", {
                params: { year, month, employee_id: employeeId || undefined },
                signal: controller.signal,
            })
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err))
                    setError(
                        "Die Monatsübersicht konnte nicht geladen werden.",
                    );
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [period, employeeId]);
    return (
        <TimeTrackingLayout>
            <div className="space-y-5">
                <div className="page-heading">
                    <div>
                        <p className="eyebrow">Zeiterfassung</p>
                        <h1>Monatsübersicht</h1>
                        <p>
                            {dayjs(`${period}-01`).format("MMMM YYYY")} ·
                            Abgeschlossene Zeiteinträge
                        </p>
                    </div>
                    <Link
                        className="text-link"
                        href={route("timetracking.exports")}
                    >
                        Zum Lohnexport
                        <ArrowUpRight size={14} />
                    </Link>
                </div>
                <div className="card p-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Vorheriger Monat"
                            onClick={() =>
                                setPeriod(
                                    dayjs(`${period}-01`)
                                        .subtract(1, "month")
                                        .format("YYYY-MM"),
                                )
                            }
                            icon={<ChevronLeft size={16} />}
                        />
                        <input
                            type="month"
                            min="2000-01"
                            max="2100-12"
                            aria-label="Monat auswählen"
                            value={period}
                            className="input w-auto"
                            onChange={(e) => {
                                if (e.target.value) setPeriod(e.target.value);
                            }}
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Nächster Monat"
                            onClick={() =>
                                setPeriod(
                                    dayjs(`${period}-01`)
                                        .add(1, "month")
                                        .format("YYYY-MM"),
                                )
                            }
                            icon={<ChevronRight size={16} />}
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPeriod(dayjs().format("YYYY-MM"))}
                        >
                            Dieser Monat
                        </Button>
                    </div>
                    <select
                        aria-label="Mitarbeiter filtern"
                        className="input w-full sm:w-60"
                        value={employeeId}
                        onChange={(e) => setEmployeeId(e.target.value)}
                    >
                        <option value="">Gesamtes Team</option>
                        {employees.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.first_name} {e.last_name}
                                {!e.active ? " (inaktiv)" : ""}
                            </option>
                        ))}
                    </select>
                </div>
                {error && (
                    <div className="notice notice-error" role="alert">
                        {error}
                    </div>
                )}
                {loading ? (
                    <div className="card empty-inline" role="status">
                        Monatsdaten werden geladen …
                    </div>
                ) : (
                    data && (
                        <>
                            <div className="metric-grid">
                                {[
                                    [
                                        "Arbeitsstunden",
                                        `${formatHours(data.totals.hours)} Std.`,
                                    ],
                                    [
                                        "Mitarbeiter mit Einträgen",
                                        data.totals.employees,
                                    ],
                                    [
                                        "Abgeschlossene Einträge",
                                        data.totals.entries,
                                    ],
                                    [
                                        "Berechneter Bruttolohn",
                                        formatMoney(
                                            Math.round(
                                                data.totals.gross_wage * 100,
                                            ),
                                        ),
                                    ],
                                ].map(([label, value]) => (
                                    <div className="metric-card" key={label}>
                                        <div>{label}</div>
                                        <strong>{value}</strong>
                                    </div>
                                ))}
                            </div>
                            <section className="card">
                                <div className="section-heading">
                                    <h2>Zusammenfassung nach Mitarbeiter</h2>
                                    <span className="text-xs text-neutral-500">
                                        {data.rows.length} Mitarbeiter
                                    </span>
                                </div>
                                {data.rows.length ? (
                                    <div className="overflow-x-auto">
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Mitarbeiter</th>
                                                    <th className="text-right">
                                                        Arbeitstage
                                                    </th>
                                                    <th className="text-right">
                                                        Einträge
                                                    </th>
                                                    <th className="text-right">
                                                        Pause
                                                    </th>
                                                    <th className="text-right">
                                                        Stunden
                                                    </th>
                                                    <th className="text-right">
                                                        Bruttolohn
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.rows.map((row) => (
                                                    <tr key={row.employee_id}>
                                                        <td>
                                                            <button
                                                                className="font-medium text-primary-800 hover:underline"
                                                                onClick={() =>
                                                                    setEmployeeId(
                                                                        String(
                                                                            row.employee_id,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                {row.name}
                                                            </button>
                                                        </td>
                                                        <td className="text-right">
                                                            {row.working_days}
                                                        </td>
                                                        <td className="text-right">
                                                            {row.entries_count}
                                                        </td>
                                                        <td className="text-right whitespace-nowrap">
                                                            {row.break_minutes}{" "}
                                                            Min.
                                                        </td>
                                                        <td className="text-right font-medium tabular-nums">
                                                            {formatHours(
                                                                row.hours,
                                                            )}
                                                        </td>
                                                        <td className="text-right tabular-nums">
                                                            {formatMoney(
                                                                Math.round(
                                                                    row.gross_wage *
                                                                        100,
                                                                ),
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="empty-inline">
                                        Für diese Auswahl liegen keine
                                        abgeschlossenen Zeiteinträge vor.
                                    </div>
                                )}
                            </section>
                            {!!employeeId && data.entries.length > 0 && (
                                <section className="card">
                                    <div className="section-heading">
                                        <h2>Einzelne Zeiteinträge</h2>
                                        <Link
                                            className="text-link"
                                            href={route(
                                                "timetracking.manipulation",
                                            )}
                                        >
                                            Bearbeiten
                                            <ArrowUpRight size={14} />
                                        </Link>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Tag</th>
                                                    <th>Beginn</th>
                                                    <th>Ende</th>
                                                    <th className="text-right">
                                                        Pause
                                                    </th>
                                                    <th className="text-right">
                                                        Stunden
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.entries.map((entry) => (
                                                    <tr key={entry.id}>
                                                        <td>
                                                            {formatDate(
                                                                entry.clock_in,
                                                            )}
                                                        </td>
                                                        <td>
                                                            {formatTime(
                                                                entry.clock_in,
                                                            )}
                                                        </td>
                                                        <td>
                                                            {formatTime(
                                                                entry.clock_out,
                                                            )}
                                                        </td>
                                                        <td className="text-right">
                                                            {
                                                                entry.break_minutes
                                                            }{" "}
                                                            Min.
                                                        </td>
                                                        <td className="text-right font-medium tabular-nums">
                                                            {formatHours(
                                                                entry.hours,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            )}
                        </>
                    )
                )}
            </div>
        </TimeTrackingLayout>
    );
}
