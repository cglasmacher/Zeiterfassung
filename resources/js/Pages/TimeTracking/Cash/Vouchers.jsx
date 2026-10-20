import React, { useEffect, useState } from "react";
import axios from "axios";
import { Link } from "@inertiajs/react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import { formatMoney as money } from "@/lib/navigation";

const amount = (cents) => (cents / 100).toFixed(2);
const cents = (value) => Math.round(Number(value || 0) * 100);
const date = (value) => value ? new Date(value.slice(0, 10) + "T12:00:00Z").toLocaleDateString("de-DE") : "";
const timestamp = (value) => new Date(value.endsWith("Z") || value.includes("+") ? value : value.replace(" ", "T") + "Z").toLocaleString("de-DE", { timeZone: "Europe/Berlin" });
const statusLabel = { open: "Offen", partial: "Teilweise eingelöst", redeemed: "Entwertet" };
function Field({ label, ...props }) {
    return <label className="block text-sm text-neutral-700"><span className="block mb-1">{label}</span>
        <input className="w-full rounded-lg border-neutral-300" {...props} /></label>;
}
function SaleBatch({ batch, busy, register }) {
    const [rows, setRows] = useState([{ number: "", amount: amount(batch.amount_cents), payment_method: "cash" }]);
    const [requestId] = useState(() => crypto.randomUUID());
    const total = rows.reduce((sum, row) => sum + cents(row.amount), 0);
    const update = (i, patch) => setRows((previous) => previous.map((row, n) => n === i ? { ...row, ...patch } : row));
    const valid = rows.every((r) => /^[0-9]{1,32}$/.test(r.number) && cents(r.amount) > 0);
    return <form className="border border-neutral-200 rounded-xl p-4 space-y-4" onSubmit={(e) => {
        e.preventDefault(); register(batch.id, { request_id: requestId, vouchers: rows });
    }}>
        <div><h3 className="font-semibold">{batch.label}</h3>
            <p>Verkaufstag {date(batch.business_date)} · Übernommener Betrag <strong>{money(batch.amount_cents)}</strong></p></div>
        {rows.map((row, i) => <div key={i} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <Field label="Nummer auf dem Gutschein" value={row.number} inputMode="numeric" pattern="[0-9]{1,32}" maxLength={32} required onChange={(e) => update(i, { number: e.target.value })} />
            <Field label="Gutscheinwert (€)" type="number" min="0.01" step="0.01" required value={row.amount} onChange={(e) => update(i, { amount: e.target.value })} />
            <label className="block text-sm text-neutral-700"><span className="block mb-1">Bezahlt mit</span>
                <select className="w-full rounded-lg border-neutral-300" value={row.payment_method} onChange={(e) => update(i, { payment_method: e.target.value })}>
                    <option value="cash">Bar</option><option value="card">Karte</option>
                </select></label>
            {rows.length > 1 && <Button variant="outline" onClick={() => setRows(rows.filter((_, n) => n !== i))}>Entfernen</Button>}
        </div>)}
        <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" disabled={busy || rows.length >= 200} onClick={() => setRows([...rows, { number: "", amount: amount(Math.max(0, batch.amount_cents - total)), payment_method: "cash" }])}>Weiterer Gutschein</Button>
            <span className={total === batch.amount_cents ? "text-green-800" : "text-amber-800"}>Zugeordnet {money(total)} von {money(batch.amount_cents)}</span>
            <Button type="submit" disabled={busy || !valid || total !== batch.amount_cents}>Verkauf bestätigen</Button>
        </div>
    </form>;
}
function VoucherCard({ voucher, day, busy, redeem, inspect, detail }) {
    const [value, setValue] = useState("");
    const [requestId, setRequestId] = useState(() => crypto.randomUUID());
    const [confirm, setConfirm] = useState(false);
    const resetAmount = (newValue) => { setValue(newValue); setRequestId(crypto.randomUUID()); setConfirm(false); };
    return <article className="border border-neutral-200 rounded-xl p-4 space-y-3">
        <div className="flex flex-wrap justify-between gap-2"><h3 className="font-semibold">Gutschein {voucher.number}</h3>
            <span className={`rounded-full px-3 py-1 text-xs ${voucher.status === "redeemed" ? "bg-neutral-100 text-neutral-600" : "bg-primary-50 text-primary-800"}`}>{statusLabel[voucher.status]}</span></div>
        <p className="text-sm text-neutral-600">Verkauft am {date(voucher.sold_on)} · {voucher.payment_method === "cash" ? "Bar" : "Karte"} · Erfasst von Benutzer {voucher.created_by} · {timestamp(voucher.created_at)}</p>
        <div className="flex flex-wrap gap-6"><p>Ursprünglicher Wert <strong>{money(voucher.amount_cents)}</strong></p>
            <p>Restguthaben <strong>{money(voucher.balance_cents)}</strong></p></div>
        {voucher.balance_cents > 0 && <form className="space-y-3" onSubmit={async (e) => {
            e.preventDefault();
            if (!confirm) { setConfirm(true); return; }
            const success = await redeem({ number: voucher.number, amount: value, request_id: requestId });
            if (success) resetAmount("");
        }}>
            <div className="flex flex-wrap gap-3 items-end">
                <Field label="Jetzt einlösen (€)" type="number" step="0.01" min="0.01" max={amount(voucher.balance_cents)} required value={value} disabled={!day || busy} onChange={(e) => resetAmount(e.target.value)} />
                <Button variant="outline" disabled={!day || busy} onClick={() => resetAmount(amount(voucher.balance_cents))}>Restbetrag übernehmen</Button>
                <Button type="submit" disabled={!day || busy || cents(value) <= 0 || cents(value) > voucher.balance_cents}>{confirm ? "Einlösung verbindlich bestätigen" : "Einlösung prüfen"}</Button>
            </div>
            {confirm && <p className="rounded-lg bg-amber-50 p-3 text-sm">Gutschein {voucher.number}: {money(cents(value))} am Geschäftstag {date(day.business_date)} einlösen.
                Danach verbleiben {money(voucher.balance_cents - cents(value))}. Die Einlösung wird protokolliert und in der nächsten Abrechnung automatisch berücksichtigt.</p>}
        </form>}
        <Button variant="ghost" size="sm" disabled={busy} onClick={() => inspect(voucher.id)}>Einlösungen anzeigen</Button>
        {detail?.voucher.id === voucher.id && <div className="text-sm border-t pt-3 space-y-2">
            {detail.redemptions.length === 0 ? <p>Noch keine Einlösung.</p> : detail.redemptions.map((r) => <p key={r.id}>
                {date(r.business_date)} · {money(r.amount_cents)} · Benutzer {r.created_by} · {timestamp(r.created_at)} · {r.handover_id ? `Übergabe SU-${r.handover_id}` : "Tagesabrechnung"}
            </p>)}
        </div>}
    </article>;
}
export default function Vouchers() {
    const [data, setData] = useState(null);
    const [number, setNumber] = useState("");
    const [status, setStatus] = useState("");
    const [filters, setFilters] = useState({ number: "", status: "", page: 1 });
    const [detail, setDetail] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const load = async (params = filters) => setData((await axios.get("/api/cash/vouchers", { params })).data);
    const run = async (action) => {
        setBusy(true); setError(""); setMessage("");
        try { await action(); return true; }
        catch (e) { setError(Object.values(e.response?.data?.errors || {}).flat().join(" ") || e.response?.data?.message || e.message); return false; }
        finally { setBusy(false); }
    };
    useEffect(() => { run(() => load(filters)); }, [filters]);
    const register = (id, body) => run(async () => {
        await axios.post(`/api/cash/vouchers/batches/${id}/register`, body);
        await load(); setMessage("Gutscheine mit Verkaufsdatum, Wert und Zahlart gespeichert.");
    });
    const redeem = (body) => run(async () => {
        const response = await axios.post("/api/cash/vouchers/redeem", body);
        setDetail(response.data); await load();
        setMessage(`Einlösung gespeichert. Restguthaben: ${money(response.data.voucher.balance_cents)}. In der Kassenabrechnung keine zusätzliche Entnahme erfassen.`);
    });
    return <TimeTrackingLayout><div className="space-y-6">
        <div><h1 className="text-2xl font-bold">Gutscheinverkauf</h1><p className="mt-2 text-neutral-600">Formularnummern zuordnen, Restguthaben prüfen und Gutscheine ganz oder teilweise einlösen.</p></div>
        {error && <p role="alert" className="rounded-lg bg-red-50 text-red-800 p-4">{error}</p>}
        {message && <p role="status" className="rounded-lg bg-green-50 text-green-800 p-4">{message}</p>}
        {!data ? <p>Lädt …</p> : <>
            <section className="card p-5 space-y-4"><h2 className="font-semibold">Verkäufe ergänzen</h2>
                <p className="text-sm text-neutral-600">Tag und Verkaufssumme stammen aus der bestätigten Schichtübergabe oder dem gespeicherten Tagesabschluss. Bei mehreren Gutscheinen die Summe aufteilen. Vor dem endgültigen Tagesabschluss müssen alle Nummern ergänzt sein.</p>
                {data.pending_sales.length ? data.pending_sales.map((b) => <SaleBatch key={`${b.id}:${b.amount_cents}`} batch={b} busy={busy} register={register} />) : <p>Keine offenen Verkäufe. Neue Gutscheinverkäufe zuerst in der <Link className="underline text-primary-700" href={route("cash.handovers")}>Schichtübergabe</Link> erfassen.</p>}
            </section>
            <section className="card p-5 space-y-4"><h2 className="font-semibold">Gutscheinliste und Einlösung</h2>
                <p className="text-sm text-neutral-600">{data.day ? `Einlösungen gehören zum offenen Geschäftstag ${date(data.day.business_date)} und werden bei der nächsten Übergabe oder beim Tagesabschluss berücksichtigt.` : "Für eine Einlösung zuerst einen Geschäftstag in der Schichtübergabe eröffnen."} In Vectron weiterhin als Barzahlung abschließen. Hier erfasste Einlösungen nicht zusätzlich als Entnahme buchen.</p>
                <form onSubmit={(e) => { e.preventDefault(); setFilters({ number, status, page: 1 }); }} className="flex flex-wrap items-end gap-3">
                    <Field label="Gutscheinnummer suchen" inputMode="numeric" pattern="[0-9]{1,32}" maxLength={32} value={number} onChange={(e) => setNumber(e.target.value)} />
                    <label className="text-sm text-neutral-700"><span className="block mb-1">Status</span><select className="rounded-lg border-neutral-300" value={status} onChange={(e) => setStatus(e.target.value)}><option value="">Alle</option><option value="open">Offen</option><option value="partial">Teilweise eingelöst</option><option value="redeemed">Entwertet</option></select></label>
                    <Button type="submit" disabled={busy}>Suchen</Button><Button variant="outline" disabled={busy} onClick={() => { setNumber(""); setStatus(""); setFilters({ number: "", status: "", page: 1 }); }}>Alle anzeigen</Button>
                    <Button variant="ghost" disabled={busy} onClick={() => run(() => load())}>Aktualisieren</Button>
                </form>
                <div className="space-y-4">{data.vouchers.data.length ? data.vouchers.data.map((v) => <VoucherCard key={v.id} voucher={v} day={data.day} busy={busy} redeem={redeem} inspect={(id) => run(async () => setDetail((await axios.get(`/api/cash/vouchers/${id}`)).data))} detail={detail} />) : <p>Keine Gutscheine gefunden.</p>}</div>
                <div className="flex flex-wrap items-center gap-3"><Button variant="outline" disabled={busy || data.vouchers.current_page <= 1} onClick={() => setFilters({ ...filters, page: filters.page - 1 })}>Zurück</Button><span>Seite {data.vouchers.current_page} von {data.vouchers.last_page} · {data.vouchers.total} Gutscheine</span><Button variant="outline" disabled={busy || data.vouchers.current_page >= data.vouchers.last_page} onClick={() => setFilters({ ...filters, page: filters.page + 1 })}>Weiter</Button></div>
            </section>
            {data.pending_redemptions.length > 0 && <section className="card p-5 space-y-3"><h2 className="font-semibold">Für die nächste Abrechnung vorgemerkt</h2>{data.pending_redemptions.map((r) => <p key={r.id}>Gutschein {r.number} · {money(r.amount_cents)} · Benutzer {r.created_by}</p>)}<Link className="underline text-primary-700" href={route("cash.handovers")}>Zur Schichtübergabe</Link></section>}
        </>}
    </div></TimeTrackingLayout>;
}
