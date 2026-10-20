import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import { formatMoney } from "@/lib/navigation";

const statusLabels = {
    received: "Eingegangen",
    analyzed: "Analysiert",
    review: "Prüfung erforderlich",
    ready: "Bereit zur Freigabe",
    approved: "Freigegeben",
    exported: "Exportiert",
};

const statusClass = {
    received: "bg-neutral-100 text-neutral-700",
    analyzed: "bg-blue-50 text-blue-800",
    review: "bg-amber-50 text-amber-800",
    ready: "bg-emerald-50 text-emerald-800",
    approved: "bg-green-50 text-green-800",
    exported: "bg-violet-50 text-violet-800",
};

const date = (value) => value ? new Date(`${value.slice(0, 10)}T12:00:00Z`).toLocaleDateString("de-DE") : "–";

function Badge({ status }) {
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusClass[status] || statusClass.received}`}>{statusLabels[status] || status}</span>;
}

function DocumentList({ data, selectedId, select, filters, setFilters, busy }) {
    return <section className="card overflow-hidden">
        <div className="p-5 border-b border-neutral-200 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><h2 className="font-semibold">Eingangsbelege</h2><p className="text-sm text-neutral-600">Kontierung prüfen und zur Freigabe vorbereiten.</p></div>
                <Button variant="ghost" disabled={busy} onClick={() => setFilters({ ...filters })}>Aktualisieren</Button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input className="rounded-lg border-neutral-300" placeholder="Lieferant oder Rechnungsnummer" value={filters.q} onChange={(e) => setFilters({ ...filters, q: e.target.value, page: 1 })} />
                <select className="rounded-lg border-neutral-300" value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}>
                    <option value="">Alle Status</option>
                    <option value="review">Prüfung erforderlich</option>
                    <option value="ready">Bereit zur Freigabe</option>
                    <option value="approved">Freigegeben</option>
                    <option value="exported">Exportiert</option>
                </select>
            </div>
        </div>
        <div className="divide-y divide-neutral-200">
            {data?.documents?.data?.length ? data.documents.data.map((doc) => <button key={doc.id} onClick={() => select(doc.id)} className={`w-full text-left p-4 hover:bg-neutral-50 ${selectedId === doc.id ? "bg-primary-50" : ""}`}>
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0"><p className="font-medium truncate">{doc.supplier?.name || doc.supplier_name_raw || "Unbekannter Lieferant"}</p><p className="text-sm text-neutral-600">{doc.invoice_number || "ohne Rechnungsnummer"} · {date(doc.invoice_date)}</p></div>
                    <strong>{formatMoney(doc.gross_cents)}</strong>
                </div>
                <div className="mt-2 flex flex-wrap items-center gap-2"><Badge status={doc.status} />{doc.unresolved_lines_count > 0 && <span className="text-xs text-amber-800">{doc.unresolved_lines_count} unklare Position(en)</span>}</div>
            </button>) : <p className="p-5 text-neutral-600">Noch keine Belege vorhanden.</p>}
        </div>
    </section>;
}

function Detail({ data, busy, saveLine, approve }) {
    const doc = data?.document;
    if (!doc) return <section className="card p-6 text-neutral-600">Beleg auswählen.</section>;
    return <section className="card p-5 space-y-5">
        <div className="grid grid-cols-1 2xl:grid-cols-[minmax(360px,0.9fr)_minmax(0,1.1fr)] gap-5 items-start">
            <div className="rounded-xl border border-neutral-200 overflow-hidden bg-neutral-50 min-h-[520px]">
                {doc.file_path ? <iframe title={`Rechnung ${doc.invoice_number || doc.id}`} src={`/api/accounting/documents/${doc.id}/pdf`} className="w-full h-[720px] bg-white" /> : <div className="min-h-[520px] flex items-center justify-center p-6 text-center text-neutral-500">Zu diesem Beleg wurde noch kein PDF gespeichert.</div>}
            </div>
            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 className="text-xl font-semibold">{doc.supplier?.name || doc.supplier_name_raw || "Unbekannter Lieferant"}</h2><p className="text-neutral-600">Rechnung {doc.invoice_number || "–"} · {date(doc.invoice_date)}</p><p className="text-sm text-neutral-500">Kreditor {doc.supplier?.creditor_account || "noch nicht zugeordnet"}</p>{doc.original_filename && <a className="inline-block mt-2 text-sm underline text-primary-700" href={`/api/accounting/documents/${doc.id}/pdf`} target="_blank" rel="noreferrer">Original-PDF öffnen</a>}</div>
                    <div className="text-right"><strong className="text-xl">{formatMoney(doc.gross_cents)}</strong><div className="mt-2"><Badge status={doc.status} /></div></div>
                </div>
                {doc.validation_errors?.length > 0 && <div className="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">{doc.validation_errors.join(" · ")}</div>}
                <div className="grid grid-cols-3 gap-3 text-sm"><div className="rounded-lg bg-neutral-50 p-3"><span className="block text-neutral-500">Netto</span><strong>{formatMoney(doc.net_cents)}</strong></div><div className="rounded-lg bg-neutral-50 p-3"><span className="block text-neutral-500">USt.</span><strong>{formatMoney(doc.tax_cents)}</strong></div><div className="rounded-lg bg-neutral-50 p-3"><span className="block text-neutral-500">Brutto</span><strong>{formatMoney(doc.gross_cents)}</strong></div></div>
            </div>
        </div>

        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead><tr className="border-b text-left text-neutral-500"><th className="py-2 pr-3">Pos.</th><th className="py-2 pr-3">Beschreibung</th><th className="py-2 pr-3">Netto</th><th className="py-2 pr-3">USt.</th><th className="py-2 pr-3 min-w-64">Konto</th><th className="py-2">Status</th></tr></thead>
                <tbody>{doc.lines.map((line) => <AccountingLine key={line.id} line={line} accounts={data.accounts} busy={busy} save={saveLine} />)}</tbody>
            </table>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
            <div className="text-sm text-neutral-600">Erst nach vollständiger Kontierung wird der Beleg zur Freigabe zugelassen.</div>
            <Button disabled={busy || !["ready", "review", "analyzed"].includes(doc.status) || doc.lines.some((line) => !line.account_id)} onClick={approve}>Beleg freigeben</Button>
        </div>
    </section>;
}

function AccountingLine({ line, accounts, busy, save }) {
    const [accountId, setAccountId] = useState(line.account_id || "");
    const [remember, setRemember] = useState(false);
    useEffect(() => setAccountId(line.account_id || ""), [line.account_id]);
    const changed = String(accountId) !== String(line.account_id || "");
    return <tr className="border-b border-neutral-100 align-top">
        <td className="py-3 pr-3">{line.position_no ?? "–"}</td>
        <td className="py-3 pr-3"><p>{line.description}</p>{line.article_number && <p className="text-xs text-neutral-500">Art. {line.article_number}</p>}</td>
        <td className="py-3 pr-3 whitespace-nowrap">{formatMoney(line.net_cents)}</td>
        <td className="py-3 pr-3 whitespace-nowrap">{line.tax_rate !== null ? `${Number(line.tax_rate).toLocaleString("de-DE")}%` : "–"}</td>
        <td className="py-3 pr-3">
            <div className="space-y-2">
                <select className="w-full rounded-lg border-neutral-300" value={accountId} onChange={(e) => setAccountId(e.target.value)} disabled={busy || doc?.status === "exported"}>
                    <option value="">Konto auswählen …</option>
                    {accounts.map((account) => <option key={account.id} value={account.id}>{account.account_number} · {account.name}</option>)}
                </select>
                {changed && <label className="flex items-center gap-2 text-xs text-neutral-600"><input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />Regel für diesen Artikel merken</label>}
                {changed && <Button size="sm" disabled={busy || !accountId} onClick={() => save(line.id, Number(accountId), remember)}>Kontierung übernehmen</Button>}
            </div>
        </td>
        <td className="py-3"><span className={line.status === "approved" ? "text-green-700" : line.status === "review" ? "text-amber-700" : "text-neutral-600"}>{line.status === "approved" ? "Bestätigt" : line.status === "review" ? "Prüfen" : "Vorschlag"}</span>{line.confidence !== null && <p className="text-xs text-neutral-500">{line.confidence}%</p>}</td>
    </tr>;
}

export default function Documents() {
    const [list, setList] = useState(null);
    const [detail, setDetail] = useState(null);
    const [selectedId, setSelectedId] = useState(null);
    const [filters, setFilters] = useState({ q: "", status: "", page: 1 });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const params = useMemo(() => ({ ...filters, q: filters.q || undefined, status: filters.status || undefined }), [filters]);

    const run = async (action) => {
        setBusy(true); setError(""); setMessage("");
        try { return await action(); }
        catch (e) { setError(Object.values(e.response?.data?.errors || {}).flat().join(" ") || e.response?.data?.message || e.message); }
        finally { setBusy(false); }
    };
    const loadList = async () => setList((await axios.get("/api/accounting/documents", { params })).data);
    const loadDetail = async (id) => { setSelectedId(id); setDetail((await axios.get(`/api/accounting/documents/${id}`)).data); };

    useEffect(() => { const timer = setTimeout(() => run(loadList), 250); return () => clearTimeout(timer); }, [params]);

    const saveLine = (lineId, accountId, rememberRule) => run(async () => {
        await axios.patch(`/api/accounting/documents/${selectedId}/lines/${lineId}`, { account_id: accountId, remember_rule: rememberRule });
        await Promise.all([loadDetail(selectedId), loadList()]);
        setMessage("Kontierung übernommen.");
    });
    const approve = () => run(async () => {
        await axios.post(`/api/accounting/documents/${selectedId}/approve`);
        await Promise.all([loadDetail(selectedId), loadList()]);
        setMessage("Beleg freigegeben und für den späteren Lexware-Export vorgemerkt.");
    });

    return <TimeTrackingLayout><div className="space-y-6">
        <div><h1 className="text-2xl font-bold">Buchhaltung</h1><p className="mt-2 text-neutral-600">Buchungsvorschläge prüfen, unklare Positionen kontieren und Belege für den Lexware-CSV-Export freigeben.</p></div>
        {error && <p role="alert" className="rounded-lg bg-red-50 text-red-800 p-4">{error}</p>}
        {message && <p role="status" className="rounded-lg bg-green-50 text-green-800 p-4">{message}</p>}
        {list?.counts && <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">{Object.entries({ review: "Prüfen", ready: "Bereit", approved: "Freigegeben", exported: "Exportiert", received: "Eingegangen" }).map(([key, label]) => <div key={key} className="card p-4"><p className="text-xs text-neutral-500">{label}</p><strong className="text-2xl">{list.counts[key] || 0}</strong></div>)}</div>}
        <div className="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-6 items-start">
            <DocumentList data={list} selectedId={selectedId} select={(id) => run(() => loadDetail(id))} filters={filters} setFilters={setFilters} busy={busy} />
            <Detail data={detail} busy={busy} saveLine={saveLine} approve={approve} />
        </div>
    </div></TimeTrackingLayout>;
}
