import React, { useEffect, useState } from "react";
import { Link } from "@inertiajs/react";
import axios from "axios";
import dayjs from "dayjs";
import { ChevronLeft, ChevronRight, Timer, ArrowUpRight } from "lucide-react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import { formatHours, formatTime, formatMoney } from "@/lib/navigation";

export default function DailyOverview() {
    const [date, setDate] = useState(dayjs().format("YYYY-MM-DD"));
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError("");
        setData(null);
        axios
            .get("/api/daily-overview", {
                params: { date },
                signal: controller.signal,
            })
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err))
                    setError("Die Zeiteinträge konnten nicht geladen werden.");
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [date]);
    const rows = data?.all_entries || [];
    return (
        <TimeTrackingLayout>
            <div className="space-y-5">
                <div className="page-heading">
                    <div>
                        <p className="eyebrow">Zeiterfassung</p>
                        <h1>Tagesübersicht</h1>
                        <p>Abgeschlossene Zeiteinträge des Teams.</p>
                    </div>
                    <Link
                        href={route("timetracking.manual")}
                        className="btn-primary inline-flex items-center gap-2"
                    >
                        <Timer size={16} />
                        Zeiten erfassen
                    </Link>
                </div>
                <div className="card p-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Vorheriger Tag"
                            onClick={() =>
                                setDate(
                                    dayjs(date)
                                        .subtract(1, "day")
                                        .format("YYYY-MM-DD"),
                                )
                            }
                            icon={<ChevronLeft size={16} />}
                        />
                        <input
                            aria-label="Tag auswählen"
                            type="date"
                            className="input w-auto"
                            value={date}
                            onChange={(e) => {
                                if (e.target.value) setDate(e.target.value);
                            }}
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Nächster Tag"
                            onClick={() =>
                                setDate(
                                    dayjs(date)
                                        .add(1, "day")
                                        .format("YYYY-MM-DD"),
                                )
                            }
                            icon={<ChevronRight size={16} />}
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setDate(dayjs().format("YYYY-MM-DD"))
                            }
                        >
                            Heute
                        </Button>
                    </div>
                    <Link
                        href={route("timetracking.manipulation")}
                        className="text-link"
                    >
                        Zeitkorrekturen
                        <ArrowUpRight size={14} />
                    </Link>
                </div>
                {error && (
                    <div className="notice notice-error" role="alert">
                        {error}
                    </div>
                )}
                {loading ? (
                    <div className="card empty-inline" role="status">
                        Zeiteinträge werden geladen …
                    </div>
                ) : (
                    data && (
                        <>
                            <div className="metric-grid">
                                {[
                                    [
                                        "Mitarbeiter",
                                        new Set(rows.map((r) => r.employee_id))
                                            .size,
                                    ],
                                    [
                                        "Arbeitsstunden",
                                        `${formatHours(rows.reduce((sum, r) => sum + Number(r.total_hours || 0), 0))} Std.`,
                                    ],
                                    [
                                        "Pausen",
                                        `${rows.reduce((sum, r) => sum + Number(r.break_minutes || 0), 0)} Min.`,
                                    ],
                                    [
                                        "Berechneter Bruttolohn",
                                        formatMoney(
                                            Math.round(
                                                rows.reduce(
                                                    (sum, r) =>
                                                        sum +
                                                        Number(
                                                            r.gross_wage || 0,
                                                        ),
                                                    0,
                                                ) * 100,
                                            ),
                                        ),
                                    ],
                                ].map(([label, value]) => (
                                    <div key={label} className="metric-card">
                                        <div>{label}</div>
                                        <strong>{value}</strong>
                                    </div>
                                ))}
                            </div>
                            <section className="card">
                                <div className="section-heading">
                                    <h2>Zeiteinträge</h2>
                                    <span className="text-xs text-neutral-500">
                                        {rows.length} Einträge
                                    </span>
                                </div>
                                {rows.length ? (
                                    <div className="overflow-x-auto">
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Mitarbeiter</th>
                                                    <th>Bereich</th>
                                                    <th>Beginn</th>
                                                    <th>Ende</th>
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
                                                {rows.map((row) => (
                                                    <tr key={row.id}>
                                                        <td className="font-medium whitespace-nowrap">
                                                            {[
                                                                row.employee
                                                                    ?.first_name,
                                                                row.employee
                                                                    ?.last_name,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(" ") ||
                                                                "Gelöschter Mitarbeiter"}
                                                        </td>
                                                        <td className="text-neutral-600">
                                                            {row.employee?.departments
                                                                ?.map(
                                                                    (d) =>
                                                                        d.name,
                                                                )
                                                                .join(", ") ||
                                                                "—"}
                                                        </td>
                                                        <td className="tabular-nums">
                                                            {formatTime(
                                                                row.clock_in,
                                                            )}
                                                        </td>
                                                        <td className="tabular-nums">
                                                            {formatTime(
                                                                row.clock_out,
                                                            )}
                                                            {dayjs(
                                                                row.clock_out,
                                                            ).format(
                                                                "YYYY-MM-DD",
                                                            ) !==
                                                                dayjs(
                                                                    row.clock_in,
                                                                ).format(
                                                                    "YYYY-MM-DD",
                                                                ) && (
                                                                <span className="text-xs text-neutral-500 ml-1">
                                                                    +1 Tag
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="text-right tabular-nums">
                                                            {row.break_minutes ||
                                                                0}{" "}
                                                            Min.
                                                        </td>
                                                        <td className="text-right tabular-nums font-medium">
                                                            {formatHours(
                                                                row.total_hours,
                                                            )}
                                                        </td>
                                                        <td className="text-right tabular-nums">
                                                            {formatMoney(
                                                                Math.round(
                                                                    Number(
                                                                        row.gross_wage ||
                                                                            0,
                                                                    ) * 100,
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
                                        Für diesen Tag liegen keine
                                        abgeschlossenen Zeiteinträge vor.
                                    </div>
                                )}
                            </section>
                            <p className="text-xs text-neutral-500">
                                Die Kassenabrechnung mit tatsächlichen
                                Barauszahlungen erfolgt unter{" "}
                                <Link
                                    href={route("cash.handovers")}
                                    className="text-primary-700 underline"
                                >
                                    Schichtübergabe
                                </Link>
                                .
                            </p>
                        </>
                    )
                )}
            </div>
        </TimeTrackingLayout>
    );
}
