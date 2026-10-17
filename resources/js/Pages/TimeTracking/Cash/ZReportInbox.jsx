import React, { useEffect, useState } from "react";
import axios from "axios";
import { FileText, RefreshCw } from "lucide-react";
import Button from "@/Components/ui/Button";

export default function ZReportInbox({ businessDate }) {
    const [inbox, setInbox] = useState(null);
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [dates, setDates] = useState({});
    async function load(signal) {
        try {
            const { data } = await axios.get("/api/cash/z-reports", {
                params: { date: businessDate }, signal, timeout: 15000,
            });
            setInbox(data);
            setError("");
        } catch (err) {
            if (!axios.isCancel(err)) setError("Z-Bericht-Eingang konnte nicht geladen werden.");
        }
    }
    useEffect(() => {
        const controller = new AbortController();
        setInbox(null);
        setError("");
        setDates({});
        load(controller.signal);
        const timer = setInterval(() => load(controller.signal), 60000);
        return () => { controller.abort(); clearInterval(timer); };
    }, [businessDate]);
    async function assign(report) {
        setBusy(true);
        try {
            await axios.patch(`/api/cash/z-reports/${report.id}/assignment`, {
                business_date: dates[report.id] || businessDate,
            });
            await load();
        } catch {
            setError("Zuordnung konnte nicht gespeichert werden.");
        } finally { setBusy(false); }
    }
    function row(report, allowAssignment) {
        return <div key={report.id} className="border border-neutral-200 rounded-lg p-3 space-y-3">
            <a href={report.pdf_url} target="_blank" rel="noopener noreferrer"
                className="text-link text-sm break-words">
                <FileText size={17} className="shrink-0" />
                Z-Bericht {report.report_number ? `Nr. ${report.report_number}` : report.original_name} öffnen
            </a>
            <p className="text-xs text-neutral-600">
                {report.register_name && `${report.register_name} · `}
                {report.business_date ? `Geschäftstag ${report.business_date}` : "Noch keinem Geschäftstag zugeordnet"}
                {report.assignment_source === "report_time" && " · nach Berichtszeit zugeordnet – bitte prüfen"}
            </p>
            {allowAssignment && <div className="flex flex-wrap items-end gap-2">
                <label className="text-xs text-neutral-600 flex-1 min-w-0">Geschäftstag
                    <input type="date" className="input block mt-1" aria-label={`Geschäftstag für Z-Bericht ${report.id}`}
                        value={dates[report.id] || businessDate}
                        onChange={event => setDates(current => ({...current, [report.id]: event.target.value}))} />
                </label>
                <Button size="sm" variant="outline" disabled={busy} onClick={() => assign(report)}>Zuordnen</Button>
            </div>}
        </div>;
    }
    return <section className="card p-5 space-y-4" aria-label="Z-Bericht-Eingang">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div><p className="eyebrow">Originalbeleg · {businessDate}</p>
                <h2 className="font-semibold mt-1">
                    {inbox?.reports.length ? "Z-Bericht für diesen Tag verfügbar" : "Z-Bericht-Eingang"}
                </h2>
            </div>
            <Button size="sm" variant="ghost" icon={<RefreshCw size={16} />} onClick={() => load()}>Prüfen</Button>
        </div>
        {error && <p className="notice notice-error" role="alert">{error}</p>}
        {!inbox && !error && <p className="text-sm text-neutral-600" role="status">Eingang wird geprüft …</p>}
        {inbox && <>
            {!inbox.reports.length && <p className="text-sm text-neutral-600">Noch kein Z-Bericht für diesen Tag vorhanden.</p>}
            {inbox.reports.map(report => row(report, false))}
            {inbox.unassigned.length > 0 && <div className="notice space-y-3">
                <strong>{inbox.unassigned.length} Z-Bericht(e) zur Zuordnung</strong>
                <p>Das Berichtsdatum war nicht eindeutig lesbar. Bitte PDF öffnen und Geschäftstag wählen.</p>
                {inbox.unassigned.map(report => row(report, true))}
            </div>}
            {inbox.recent.length > 0 && <details className="text-sm">
                <summary className="cursor-pointer py-2">Letzte Eingänge und Tageszuordnung korrigieren</summary>
                <div className="mt-3 space-y-3">{inbox.recent.map(report => row(report, true))}</div>
            </details>}
            <p className="text-xs text-neutral-500">Der PDF-Eingang verändert keine Umsätze und gibt keinen Abschluss frei. Z-Bericht und Berichtszeitraum vor der Abrechnung prüfen.</p>
        </>}
    </section>;
}
