import React, { useEffect, useState } from "react";
import axios from "axios";
import Button from "@/Components/ui/Button";
import { formatMoney } from "@/lib/navigation";

export default function ExportPanel({ onCreated }) {
    const [year, setYear] = useState(new Date().getFullYear());
    const [preview, setPreview] = useState(null);
    const [history, setHistory] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [download, setDownload] = useState(null);
    const loadHistory = async () => setHistory((await axios.get("/api/accounting/exports")).data.batches);
    useEffect(() => { loadHistory().catch(() => setError("Exportverlauf konnte nicht geladen werden.")); }, []);
    const run = async action => {
        setBusy(true); setError("");
        try { await action(); }
        catch (e) { setError(Object.values(e.response?.data?.errors || {}).flat().join(" ") || e.response?.data?.message || e.message); }
        finally { setBusy(false); }
    };
    const prepare = () => run(async () => {
        setPreview(null); setDownload(null);
        setPreview((await axios.post("/api/accounting/exports/preview", { year: Number(year) })).data);
    });
    const create = () => run(async () => {
        const result = (await axios.post("/api/accounting/exports", {
            year: Number(year), document_ids: preview.documents.map(doc => doc.id), fingerprint: preview.fingerprint,
        })).data;
        setDownload(result); setPreview(null);
        await Promise.all([loadHistory(), onCreated()]);
    });
    return <section className="card p-5 space-y-4">
        <div className="flex flex-wrap gap-3 items-end justify-between">
            <div><h2 className="font-semibold">Lexware-Buchungsstapel</h2><p className="text-sm text-neutral-600">Alle freigegebenen, noch nicht exportierten Belege des ausgewählten Jahres.</p></div>
            <div className="flex gap-2 items-end"><label className="text-sm">Belegjahr<input type="number" min="2000" max="2100" className="block w-28 rounded-lg border-neutral-300" value={year} disabled={busy} onChange={e => { setYear(e.target.value); setPreview(null); }} /></label><Button variant="outline" disabled={busy || !year} onClick={prepare}>Export vorbereiten</Button></div>
        </div>
        {error && <p role="alert" className="rounded-lg bg-red-50 text-red-800 p-3">{error}</p>}
        {preview && <div className="space-y-3 border-t pt-4">
            <p><strong>{preview.documents.length} Beleg(e), {preview.row_count} Buchungssätze</strong> · Rechnungsbrutto gesamt {formatMoney(preview.total_cents)}</p>
            <p className="text-sm text-neutral-600">Je Rechnung eine Kreditorenbuchung über den Gesamtbetrag mit der Rechnungsnummer als Text. Die Aufteilungen laufen über Verrechnungskonto {preview.clearing_account}, das je Rechnung ausgeglichen ist.</p>
            <p className="text-sm text-neutral-600">Bitte die Vorschau prüfen. Mit „Export erstellen“ werden diese Belege als exportiert markiert. Die Datei lässt sich danach unverändert erneut herunterladen.</p>
            <div className="max-h-96 overflow-auto"><table className="min-w-full text-sm">
                <thead><tr className="border-b text-left"><th className="p-2">Datum</th><th className="p-2">Beleg</th><th className="p-2">Buchungstext</th><th className="p-2">Betrag</th><th className="p-2">Soll</th><th className="p-2">Haben</th><th className="p-2">St.-Schlüssel</th></tr></thead>
                <tbody>{preview.rows.map((row, i) => <tr key={i} className="border-b border-neutral-100"><td className="p-2 whitespace-nowrap">{row.date}</td><td className="p-2">{row.invoice_number}</td><td className="p-2">{row.text}</td><td className="p-2 whitespace-nowrap">{formatMoney(row.amount_cents)}</td><td className="p-2">{row.debit_account}</td><td className="p-2">{row.credit_account}</td><td className="p-2">{row.tax_key || "–"}</td></tr>)}</tbody>
            </table></div>
            <div className="flex gap-2"><Button disabled={busy} onClick={create}>Export erstellen</Button><Button variant="ghost" disabled={busy} onClick={() => setPreview(null)}>Abbrechen</Button></div>
        </div>}
        {download && <p role="status" className="rounded-lg bg-green-50 text-green-800 p-3">Export erstellt. <a className="underline font-medium" href={download.download_url}>{download.batch.filename} herunterladen</a></p>}
        {history.length > 0 && <details><summary className="cursor-pointer text-sm font-medium">Bisherige Exporte ({history.length})</summary><ul className="mt-2 space-y-2 text-sm">{history.map(batch => <li key={batch.id}><a className="underline" href={`/api/accounting/exports/${batch.id}/download`}>{batch.filename}</a> · {batch.row_count} Buchungssätze · {formatMoney(batch.total_cents)}</li>)}</ul></details>}
        <p className="text-xs text-neutral-500">Import in Lexware Financial Office über Datei → Import → Text/ASCII. CSV mit Semikolon, ANSI/Windows-1252 und Kopfzeile. Exporte werden hier bereitgestellt; der Import in Lexware erfolgt anschließend manuell.</p>
    </section>;
}
