import React, { useEffect, useState } from "react";
import axios from "axios";
import dayjs from "dayjs";
import { Link, router } from "@inertiajs/react";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import Button from "@/Components/ui/Button";
import ZReportInbox from "./ZReportInbox";

const euro = (cents) =>
    ((cents || 0) / 100).toLocaleString("de-DE", {
        style: "currency",
        currency: "EUR",
    });
const amount = (cents) => ((cents || 0) / 100).toFixed(2);
const cents = (value) => Math.round(Number(value || 0) * 100);
const initialReport = () => ({
    operator: "",
    reference: "",
    cash_sales: "",
    cash_due: "",
    card_sales: "",
    card_due: "",
    voucher_sales: "0.00",
});
const emptyForm = () => ({
    label: "",
    reports: [initialReport()],
    workers: [],
    movements: [],
    counted: "",
    envelope: "0.00",
    note: "",
});
const emptyZ = (date) => ({
    number: "",
    register: "Kasse #1",
    reference: "",
    period_start: `${date}T06:00`,
    period_end: `${dayjs(date).add(1, "day").format("YYYY-MM-DD")}T06:00`,
    net7: "",
    tax7: "",
    gross7: "",
    net19: "",
    tax19: "",
    gross19: "",
    cash_sales: "",
    cash_due: "",
    card_sales: "",
    card_due: "",
    voucher_sales: "0.00",
    reconciliation_note: "",
});
function Field({ label, value, onChange, type = "text", ...props }) {
    return (
        <label className="block text-sm text-neutral-700">
            <span className="block mb-1">{label}</span>
            <input
                className="w-full rounded-lg border-neutral-300"
                type={type}
                value={value ?? ""}
                onChange={(e) => onChange(e.target.value)}
                {...props}
            />
        </label>
    );
}
function MoneyField(props) {
    return <Field type="number" step="0.01" {...props} />;
}
function Panel({ title, children }) {
    return (
        <section className="card p-5 space-y-4">
            <h2 className="text-base font-semibold">{title}</h2>
            {children}
        </section>
    );
}
function Totals({ calc }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {[
                ["Erwartet vor Umschlag", calc.expected_cents],
                ["Gutscheine eingelöst (abgezogen)", calc.voucher_redemptions_cents],
                ["Gezählt vor Umschlag", calc.counted_cents],
                ["Kassendifferenz", calc.difference_cents],
                ["Umschlag", calc.envelope_cents],
                ["Verbleibender Bestand", calc.remaining_cents],
                ["Wechselgeld-Soll", calc.float_target_cents],
                ["Auffüllbedarf", calc.top_up_cents],
            ].map(([label, value]) => (
                <div className="rounded-lg bg-neutral-50 p-3" key={label}>
                    <div className="text-xs text-neutral-600">{label}</div>
                    <strong>{euro(value)}</strong>
                </div>
            ))}
        </div>
    );
}
export default function Accounting({ mode }) {
    const [index, setIndex] = useState(null);
    const [selected, setSelected] = useState("");
    const [detail, setDetail] = useState(null);
    const [date, setDate] = useState(dayjs().format("YYYY-MM-DD"));
    const [opening, setOpening] = useState("250.00");
    const [form, setForm] = useState(emptyForm());
    const [z, setZ] = useState(emptyZ(date));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [bi, setBi] = useState(null);
    const [dirty, setDirty] = useState(false);
    const isHandover = mode === "handover";
    useEffect(() => {
        if (!dirty) return;
        const unsubscribe = router.on("before", () =>
            window.confirm("Ungespeicherte Eingaben verwerfen und die Seite wechseln?"),
        );
        const warnBeforeUnload = (event) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", warnBeforeUnload);
        return () => {
            unsubscribe();
            window.removeEventListener("beforeunload", warnBeforeUnload);
        };
    }, [dirty]);
    const loadIndex = async () => {
        const { data } = await axios.get("/api/cash/days");
        setIndex(data);
        return data;
    };
    const applyDetail = (data) => {
        setDetail(data);
        setSelected(String(data.day.id));
        const supplement = data.day.supplement;
        if (!isHandover && supplement) {
            const saved = data.day.z_report;
            const nextZ = { ...saved };
            [
                "net7",
                "tax7",
                "gross7",
                "net19",
                "tax19",
                "gross19",
                "cash_sales",
                "cash_due",
                "card_sales",
                "card_due",
                "voucher_sales",
            ].forEach((key) => (nextZ[key] = amount(saved[key + "_cents"])));
            // Stored ISO timestamps include UTC; datetime-local displays Berlin time explicitly.
            const localTime = (iso) =>
                new Intl.DateTimeFormat("sv-SE", {
                    timeZone: "Europe/Berlin",
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: false,
                })
                    .format(new Date(iso))
                    .replace(" ", "T");
            nextZ.period_start = localTime(saved.period_start);
            nextZ.period_end = localTime(saved.period_end);
            setZ(nextZ);
            setForm({
                ...emptyForm(),
                workers: supplement.workers.map((w) => ({
                    employee_id: w.employee_id,
                    hours: (w.minutes / 60).toFixed(4),
                    hourly_rate: amount(w.hourly_rate_cents),
                    amount: amount(w.amount_cents),
                    paid_cash: w.paid_cash,
                    note: w.note,
                })),
                movements: supplement.movements.map((m) => ({
                    ...m,
                    amount: amount(Math.abs(m.amount_cents)),
                })),
                counted: data.day.closing ? amount(data.day.closing.counted_cents) : "",
                envelope: amount(data.day.closing?.envelope_cents),
                note: data.day.closing?.note || "",
            });
        } else {
            setForm(emptyForm());
            setZ(emptyZ(data.day.business_date));
        }
        setBi(null);
        setDirty(false);
    };
    const run = async (fn) => {
        setBusy(true);
        setError("");
        setMessage("");
        try {
            await fn();
        } catch (err) {
            setError(
                Object.values(err.response?.data?.errors || {})
                    .flat()
                    .join(" ") ||
                    err.response?.data?.message ||
                    err.message,
            );
        } finally {
            setBusy(false);
        }
    };
    useEffect(() => {
        run(async () => {
            const data = await loadIndex();
            const open = data.days.find((d) => d.status === "draft");
            if (open)
                applyDetail(
                    (await axios.get(`/api/cash/days/${open.id}`)).data,
                );
        });
    }, [mode]);
    const change = (key, value) => {
        setDirty(true);
        setForm((prev) => ({ ...prev, [key]: value }));
    };
    const changeZ = (key, value) => {
        setDirty(true);
        setZ((prev) => ({ ...prev, [key]: value }));
    };
    const updateRow = (key, i, patch) => {
        setDirty(true);
        setForm((prev) => ({
            ...prev,
            [key]: prev[key].map((row, n) =>
                n === i ? { ...row, ...patch } : row,
            ),
        }));
    };
    const remove = (key, i) =>
        change(
            key,
            form[key].filter((_, n) => n !== i),
        );
    const handovers = detail?.handovers || [];
    const day = detail?.day;
    const locked = day?.status === "finalized";
    const handoverDue = handovers.reduce(
        (sum, h) => sum + h.snapshot.calculation.cash_due_cents,
        0,
    );
    const lastBalance = handovers.length
        ? handovers[handovers.length - 1].snapshot.calculation.remaining_cents
        : day?.opening_cents || 0;
    const due = isHandover
        ? form.reports.reduce((sum, r) => sum + cents(r.cash_due), 0)
        : cents(z.cash_due) - handoverDue;
    const expenses = form.movements.reduce(
        (sum, m) => sum + cents(m.amount) * (m.kind === "deposit" ? -1 : 1),
        0,
    );
    const wages = form.workers.reduce(
        (sum, w) => sum + (w.paid_cash ? cents(w.amount) : 0),
        0,
    );
    const counted = cents(form.counted),
        envelope = cents(form.envelope),
        target = day?.float_target_cents || 0;
    const redemptions = detail?.voucher_redemptions || [];
    const redeemed = redemptions.reduce((sum, row) => sum + row.amount_cents, 0);
    const calc = {
        expected_cents: lastBalance + due - redeemed - expenses - wages,
        voucher_redemptions_cents: redeemed,
        counted_cents: counted,
        difference_cents: counted - (lastBalance + due - redeemed - expenses - wages),
        envelope_cents: envelope,
        remaining_cents: counted - envelope,
        float_target_cents: target,
        top_up_cents: Math.max(0, target - (counted - envelope)),
    };
    const submit = () =>
        run(async () => {
            if (isHandover) {
                // Retain the UUID across retries, including network failures.
                const requestId = form.request_id || crypto.randomUUID();
                change("request_id", requestId);
                const response = await axios.post(
                    `/api/cash/days/${day.id}/handovers`,
                    { ...form, request_id: requestId, revision: day.revision },
                );
                applyDetail(response.data);
                setMessage(
                    "Schichtübergabe gespeichert. Gutscheinverkäufe gegebenenfalls unter Gutscheinverkauf mit Nummer und Zahlart ergänzen.",
                );
            } else {
                applyDetail(
                    (
                        await axios.put(`/api/cash/days/${day.id}`, {
                            ...form,
                            z,
                            revision: day.revision,
                        })
                    ).data,
                );
                setMessage(
                    "Entwurf gespeichert. Zum Abschließen unten freigeben.",
                );
            }
            await loadIndex();
        });
    return (
        <TimeTrackingLayout>
            <div className="cash-accounting space-y-5">
                <div>
                    <h1 className="heading-2">
                        {isHandover ? "Schichtübergabe" : "Abrechnung"}
                    </h1>
                    <p className="text-neutral-600 mt-2">
                        {isHandover
                            ? "X-Berichte, Auszahlungen und Umschlag erfassen."
                            : "Tagesabschluss prüfen, speichern und freigeben."}
                    </p>
                </div>
                <nav className="cash-section-tabs" aria-label="Kassenbereich">
                    <Link href={route("cash.handovers")} aria-current={isHandover ? "page" : undefined}>
                        Schichtübergabe
                    </Link>
                    <Link href={route("cash.days")} aria-current={!isHandover ? "page" : undefined}>
                        Abrechnung · Tagesabschluss
                    </Link>
                </nav>
                {error && (
                    <div
                        role="alert"
                        className="bg-red-50 text-red-800 p-4 rounded-lg"
                    >
                        {error}
                    </div>
                )}
                {message && (
                    <div
                        role="status"
                        className="bg-green-50 text-green-800 p-4 rounded-lg"
                    >
                        {message}
                    </div>
                )}
                <ZReportInbox businessDate={day?.business_date || date} />
                <Panel title="Geschäftstag">
                    <div className="cash-day-picker grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 items-end gap-3">
                        <label className="text-sm">
                            Vorhandener Tag
                            <select
                                className="block w-full rounded-lg border-neutral-300"
                                value={selected}
                                disabled={busy}
                                onChange={(e) =>
                                    run(async () =>
                                        applyDetail(
                                            (
                                                await axios.get(
                                                    `/api/cash/days/${e.target.value}`,
                                                )
                                            ).data,
                                        ),
                                    )
                                }
                            >
                                <option value="" disabled>
                                    Bitte wählen
                                </option>
                                {index?.days.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.business_date} ·{" "}
                                        {d.status === "draft"
                                            ? "Offen"
                                            : "Abgeschlossen"}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <Field
                            label="Neuer Geschäftstag"
                            type="date"
                            value={date}
                            onChange={setDate}
                        />
                        {index?.state?.balance_cents == null && (
                            <MoneyField
                                label="Gezählter erster Anfangsbestand (€)"
                                min="0"
                                value={opening}
                                onChange={setOpening}
                            />
                        )}
                        <Button
                            disabled={
                                busy ||
                                index?.days.some((d) => d.status === "draft")
                            }
                            onClick={() =>
                                run(async () => {
                                    applyDetail(
                                        (
                                            await axios.post("/api/cash/days", {
                                                business_date: date,
                                                opening,
                                            })
                                        ).data,
                                    );
                                    await loadIndex();
                                })
                            }
                        >
                            Tag eröffnen
                        </Button>
                    </div>
                    <p className="text-sm text-neutral-500">
                        Folgetage übernehmen den tatsächlichen Restbestand. Ein
                        offener Geschäftstag muss zuerst abgeschlossen werden.
                    </p>
                </Panel>
                {day && (
                    <>
                        <Panel
                            title={`${day.business_date} · Anfangsbestand ${euro(day.opening_cents)}`}
                        >
                            <p>
                                Bestand nach letzter Übergabe:{" "}
                                <strong>{euro(lastBalance)}</strong> ·
                                Wechselgeld-Soll:{" "}
                                <strong>{euro(target)}</strong>
                            </p>
                            {handovers.map((h) => (
                                <details
                                    key={h.id}
                                    className="border rounded p-3"
                                >
                                    <summary>
                                        Umschlag SU-{h.id} · {h.snapshot.label}{" "}
                                        ·{" "}
                                        {euro(
                                            h.snapshot.calculation
                                                .envelope_cents,
                                        )}
                                    </summary>
                                    <div className="mt-3 space-y-3">
                                        <Totals calc={h.snapshot.calculation} />
                                        {h.snapshot.reports.map((r, i) => (
                                            <p key={i}>
                                                {r.operator} · {r.reference} ·
                                                Bar Soll{" "}
                                                {euro(r.cash_due_cents)}
                                                {" · Gutscheinverkauf "}
                                                {euro(r.voucher_sales_cents)}
                                            </p>
                                        ))}
                                        {h.snapshot.workers.map((w, i) => (
                                            <p key={i}>
                                                {w.name}:{" "}
                                                {(w.minutes / 60).toFixed(2)}{" "}
                                                Std. · {euro(w.amount_cents)} ·{" "}
                                                {w.paid_cash
                                                    ? "bar ausgezahlt"
                                                    : "nicht ausgezahlt"}
                                            </p>
                                        ))}
                                        {h.snapshot.movements.map((m, i) => (
                                            <p key={i}>
                                                {m.description}:{" "}
                                                {euro(m.amount_cents)}{" "}
                                                {m.receipt}
                                            </p>
                                        ))}
                                        <p>{h.snapshot.note}</p>
                                    </div>
                                </details>
                            ))}
                        </Panel>
                        {locked ? (
                            <Panel title="Abschluss gespeichert und gesperrt">
                                <Totals calc={day.snapshot.closing} />
                                <div className="flex flex-wrap gap-3">
                                    <a
                                        className="text-primary-700 underline"
                                        href={`/api/cash/days/${day.id}/pdf`}
                                    >
                                        Tagesabschluss als PDF
                                    </a>
                                    <Button
                                        disabled={
                                            busy ||
                                            detail.delivery?.status ===
                                                "delivered"
                                        }
                                        onClick={() =>
                                            run(async () => {
                                                setMessage(
                                                    (
                                                        await axios.post(
                                                            `/api/cash/days/${day.id}/deliver`,
                                                        )
                                                    ).data.message,
                                                );
                                                applyDetail(
                                                    (
                                                        await axios.get(
                                                            `/api/cash/days/${day.id}`,
                                                        )
                                                    ).data,
                                                );
                                            })
                                        }
                                    >
                                        An n8n übergeben
                                    </Button>
                                    <Button
                                        disabled={busy}
                                        onClick={() =>
                                            run(async () =>
                                                applyDetail(
                                                    (
                                                        await axios.get(
                                                            `/api/cash/days/${day.id}`,
                                                        )
                                                    ).data,
                                                ),
                                            )
                                        }
                                    >
                                        Status laden
                                    </Button>
                                </div>
                                <p>
                                    n8n: {detail.delivery?.status || "pending"}{" "}
                                    · Versuche: {detail.delivery?.attempts || 0}
                                </p>
                                {detail.delivery?.last_error && (
                                    <p className="text-red-700">
                                        {detail.delivery.last_error}
                                    </p>
                                )}
                            </Panel>
                        ) : (
                            <>
                                <Panel title="Gutscheinverkäufe und Einlösungen">
                                    <p className="text-sm text-neutral-600">
                                        Neue Verkäufe werden nach dem Speichern automatisch zur Nummerierung vorgemerkt.
                                        Einlösungen mit Gutscheinnummer im Gutscheinbereich erfassen; sie werden hier automatisch
                                        vom erwarteten Bargeld abgezogen. Nicht zusätzlich als Entnahme eintragen.
                                    </p>
                                    {detail.pending_voucher_sales > 0 && <p className="text-amber-800">
                                        {detail.pending_voucher_sales} Verkaufseinträge benötigen noch Gutscheinnummern und Zahlarten.
                                    </p>}
                                    <Link href={route("cash.vouchers")} className="text-primary-700 underline">Gutscheinverkauf und Einlösung öffnen</Link>
                                    {redemptions.map((r) => <p key={r.id}>Gutschein {r.number}: {euro(r.amount_cents)} · wird bei dieser Abrechnung abgezogen</p>)}
                                </Panel>
                                {isHandover ? (
                                    <Panel title="Bediener-X-Berichte">
                                        <Field
                                            label="Bezeichnung der Übergabe"
                                            value={form.label}
                                            onChange={(v) => change("label", v)}
                                        />
                                        <p className="text-sm text-neutral-600">
                                            Nur Werte seit der vorherigen
                                            Übergabe eintragen. Bei kumulativen
                                            X-Berichten die Differenz verwenden.
                                            Bar Soll enthält das bereits
                                            ausgezahlte Kartentrinkgeld; dieses
                                            nicht erneut als Ausgabe erfassen.
                                            {" "}Gutscheinverkauf unter „Mehrzweck“
                                            separat übernehmen. Er ist im Soll
                                            bereits enthalten und keine zusätzliche
                                            Bareinlage.
                                        </p>
                                        {form.reports.map((r, i) => (
                                            <div
                                                key={i}
                                                className="border rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"
                                            >
                                                <Field
                                                    label="Bediener / Nummer"
                                                    value={r.operator}
                                                    onChange={(v) =>
                                                        updateRow(
                                                            "reports",
                                                            i,
                                                            { operator: v },
                                                        )
                                                    }
                                                />
                                                <Field
                                                    label="Berichtszeitraum / Referenz"
                                                    value={r.reference}
                                                    onChange={(v) =>
                                                        updateRow(
                                                            "reports",
                                                            i,
                                                            { reference: v },
                                                        )
                                                    }
                                                />
                                                {[
                                                    ["cash_sales", "Barumsatz"],
                                                    ["cash_due", "Bar Soll"],
                                                    [
                                                        "card_sales",
                                                        "Kartenumsatz",
                                                    ],
                                                    ["card_due", "Karte Soll"],
                                                    ["voucher_sales", "Gutscheinverkauf (Mehrzweck)"],
                                                ].map(([key, label]) => (
                                                    <MoneyField
                                                        key={key}
                                                        label={`${label} (€)`}
                                                        value={r[key]}
                                                        onChange={(v) =>
                                                            updateRow(
                                                                "reports",
                                                                i,
                                                                { [key]: v },
                                                            )
                                                        }
                                                    />
                                                ))}
                                                <Button
                                                    onClick={() =>
                                                        remove("reports", i)
                                                    }
                                                >
                                                    Entfernen
                                                </Button>
                                            </div>
                                        ))}
                                        <Button
                                            onClick={() =>
                                                change("reports", [
                                                    ...form.reports,
                                                    initialReport(),
                                                ])
                                            }
                                        >
                                            Bedienerbericht ergänzen
                                        </Button>
                                    </Panel>
                                ) : (
                                    <Panel title="Z-Bericht und Vectron-Abgleich">
                                        <p className="text-sm text-neutral-600">
                                            Die BI-Schnittstelle liefert
                                            Rechnungen. Z-Bericht-Werte bis zur
                                            geprüften automatischen Zuordnung
                                            vom Original übernehmen. Keine
                                            Schichtberichte zu den Tagesumsätzen
                                            addieren.
                                            {" "}Gutscheinverkauf als Tagessumme aus
                                            dem Z-Bericht übernehmen. Er ist im Soll
                                            bereits enthalten und wird separat von
                                            den 7-/19-%-Umsätzen erfasst.
                                        </p>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            {[
                                                ["number", "Z-Nummer"],
                                                ["register", "Kasse"],
                                                [
                                                    "reference",
                                                    "Originalbeleg / Ablagereferenz",
                                                ],
                                            ].map(([key, label]) => (
                                                <Field
                                                    key={key}
                                                    label={label}
                                                    value={z[key]}
                                                    onChange={(v) =>
                                                        changeZ(key, v)
                                                    }
                                                />
                                            ))}
                                            {[
                                                [
                                                    "period_start",
                                                    "Berichtszeitraum von",
                                                ],
                                                [
                                                    "period_end",
                                                    "Berichtszeitraum bis (exklusiv)",
                                                ],
                                            ].map(([key, label]) => (
                                                <Field
                                                    key={key}
                                                    label={label}
                                                    type="datetime-local"
                                                    value={z[key]}
                                                    onChange={(v) =>
                                                        changeZ(key, v)
                                                    }
                                                />
                                            ))}
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            {[
                                                ["net7", "7 % Netto"],
                                                ["tax7", "7 % Steuer"],
                                                ["gross7", "7 % Brutto"],
                                                ["net19", "19 % Netto"],
                                                ["tax19", "19 % Steuer"],
                                                ["gross19", "19 % Brutto"],
                                                ["cash_sales", "Barumsatz"],
                                                ["cash_due", "Bar Soll"],
                                                ["card_sales", "Kartenumsatz"],
                                                ["card_due", "Karte Soll"],
                                                ["voucher_sales", "Gutscheinverkauf (Mehrzweck)"],
                                            ].map(([key, label]) => (
                                                <MoneyField
                                                    key={key}
                                                    label={`${label} (€)`}
                                                    value={z[key]}
                                                    onChange={(v) =>
                                                        changeZ(key, v)
                                                    }
                                                />
                                            ))}
                                        </div>
                                        <Field
                                            label="Vermerk zum Datenabgleich"
                                            value={z.reconciliation_note}
                                            onChange={(v) =>
                                                changeZ(
                                                    "reconciliation_note",
                                                    v,
                                                )
                                            }
                                        />
                                        <div className="flex flex-wrap gap-3">
                                            <Button
                                                disabled={busy}
                                                onClick={() =>
                                                    run(async () => {
                                                        const { data } =
                                                            await axios.post(
                                                                "/api/cash/vectron/sync",
                                                            );
                                                        setMessage(
                                                            data.message,
                                                        );
                                                        if (data.sites)
                                                            setMessage(
                                                                data.message +
                                                                    " Filialen: " +
                                                                    data.sites
                                                                        .map(
                                                                            (
                                                                                s,
                                                                            ) =>
                                                                                `${s.name}: ${s.siteId}`,
                                                                        )
                                                                        .join(
                                                                            ", ",
                                                                        ),
                                                            );
                                                    })
                                                }
                                            >
                                                Vectron-Daten abrufen
                                            </Button>
                                            <Button
                                                disabled={
                                                    busy ||
                                                    !z.period_start ||
                                                    !z.period_end
                                                }
                                                onClick={() =>
                                                    run(async () =>
                                                        setBi(
                                                            (
                                                                await axios.get(
                                                                    "/api/cash/vectron/summary",
                                                                    {
                                                                        params: {
                                                                            start: z.period_start,
                                                                            end: z.period_end,
                                                                        },
                                                                    },
                                                                )
                                                            ).data,
                                                        ),
                                                    )
                                                }
                                            >
                                                Importierte Rechnungen
                                                vergleichen
                                            </Button>
                                        </div>
                                        {bi && (
                                            <div className="bg-amber-50 p-3 rounded">
                                                <p>
                                                    {bi.count} Rechnungen ·
                                                    Kontrollsumme{" "}
                                                    {euro(
                                                        bi.reported_invoice_total_cents,
                                                    )}
                                                </p>
                                                <p>{bi.message}</p>
                                                {bi.warnings.map((w, i) => (
                                                    <p key={i}>{w}</p>
                                                ))}
                                            </div>
                                        )}
                                    </Panel>
                                )}
                                <Panel
                                    title={
                                        isHandover
                                            ? "Mitarbeiter und Barauszahlungen"
                                            : "Zusätzliche Mitarbeiter und Barauszahlungen"
                                    }
                                >
                                    <p className="text-sm text-neutral-600">
                                        Nur neue Vorgänge ergänzen. Bestätigte
                                        Schichtübergaben sind bereits
                                        berücksichtigt. Diese Stunden ändern
                                        keine Zeitstempel und keinen Lohnexport.
                                    </p>
                                    {form.workers.map((w, i) => (
                                        <div
                                            key={i}
                                            className="border rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
                                        >
                                            <label className="text-sm">
                                                Mitarbeiter
                                                <select
                                                    className="block w-full rounded-lg border-neutral-300 mt-1"
                                                    value={w.employee_id}
                                                    onChange={(e) => {
                                                        const employee =
                                                            index.employees.find(
                                                                (emp) =>
                                                                    emp.id ===
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                            );
                                                        const rate = Number(
                                                            employee?.hourly_rate ||
                                                                0,
                                                        );
                                                        updateRow(
                                                            "workers",
                                                            i,
                                                            {
                                                                employee_id:
                                                                    e.target
                                                                        .value,
                                                                hourly_rate:
                                                                    rate.toFixed(
                                                                        2,
                                                                    ),
                                                                amount: (
                                                                    (Math.round(
                                                                        Number(
                                                                            w.hours ||
                                                                                0,
                                                                        ) * 60,
                                                                    ) /
                                                                        60) *
                                                                    rate
                                                                ).toFixed(2),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <option value="">
                                                        Bitte wählen
                                                    </option>
                                                    {index?.employees.map(
                                                        (emp) => (
                                                            <option
                                                                key={emp.id}
                                                                value={emp.id}
                                                            >
                                                                {emp.first_name}{" "}
                                                                {emp.last_name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <Field
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="24"
                                                label="Stunden (dezimal)"
                                                value={w.hours}
                                                onChange={(v) =>
                                                    updateRow("workers", i, {
                                                        hours: v,
                                                        amount: (
                                                            (Math.round(
                                                                Number(v) * 60,
                                                            ) /
                                                                60) *
                                                            Number(
                                                                w.hourly_rate ||
                                                                    0,
                                                            )
                                                        ).toFixed(2),
                                                    })
                                                }
                                            />
                                            <MoneyField
                                                label="Stundensatz (€)"
                                                min="0"
                                                value={w.hourly_rate}
                                                onChange={(v) =>
                                                    updateRow("workers", i, {
                                                        hourly_rate: v,
                                                        amount: (
                                                            (Math.round(
                                                                Number(
                                                                    w.hours ||
                                                                        0,
                                                                ) * 60,
                                                            ) /
                                                                60) *
                                                            Number(v)
                                                        ).toFixed(2),
                                                    })
                                                }
                                            />
                                            <MoneyField
                                                label="Betrag (€), änderbar"
                                                min="0"
                                                value={w.amount}
                                                onChange={(v) =>
                                                    updateRow("workers", i, {
                                                        amount: v,
                                                    })
                                                }
                                            />
                                            <label className="text-sm flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={w.paid_cash}
                                                    onChange={(e) =>
                                                        updateRow(
                                                            "workers",
                                                            i,
                                                            {
                                                                paid_cash:
                                                                    e.target
                                                                        .checked,
                                                            },
                                                        )
                                                    }
                                                />
                                                Tatsächlich bar ausgezahlt
                                            </label>
                                            <Field
                                                label="Vermerk"
                                                value={w.note}
                                                onChange={(v) =>
                                                    updateRow("workers", i, {
                                                        note: v,
                                                    })
                                                }
                                            />
                                            <Button
                                                onClick={() =>
                                                    remove("workers", i)
                                                }
                                            >
                                                Entfernen
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        onClick={() =>
                                            change("workers", [
                                                ...form.workers,
                                                {
                                                    employee_id: "",
                                                    hours: "",
                                                    hourly_rate: "0.00",
                                                    amount: "0.00",
                                                    paid_cash: false,
                                                    note: "",
                                                },
                                            ])
                                        }
                                    >
                                        Mitarbeiter ergänzen
                                    </Button>
                                </Panel>
                                <Panel title="Einkäufe und weitere Geldbewegungen">
                                    {form.movements.map((m, i) => (
                                        <div
                                            key={i}
                                            className="grid grid-cols-2 lg:grid-cols-5 gap-3 border rounded-lg p-3"
                                        >
                                            <label className="text-sm">
                                                Art
                                                <select
                                                    className="block rounded-lg border-neutral-300 mt-1 w-full"
                                                    value={m.kind}
                                                    onChange={(e) =>
                                                        updateRow(
                                                            "movements",
                                                            i,
                                                            {
                                                                kind: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {[
                                                        ["purchase", "Einkauf"],
                                                        [
                                                            "expense",
                                                            "Sonstige Ausgabe",
                                                        ],
                                                        [
                                                            "deposit",
                                                            "Einlage / Auffüllung",
                                                        ],
                                                        [
                                                            "withdrawal",
                                                            "Sonstige Entnahme",
                                                        ],
                                                    ].map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <MoneyField
                                                label="Betrag (€)"
                                                min="0.01"
                                                value={m.amount}
                                                onChange={(v) =>
                                                    updateRow("movements", i, {
                                                        amount: v,
                                                    })
                                                }
                                            />
                                            <Field
                                                label="Empfänger / Grund"
                                                value={m.description}
                                                onChange={(v) =>
                                                    updateRow("movements", i, {
                                                        description: v,
                                                    })
                                                }
                                            />
                                            <Field
                                                label="Belegreferenz"
                                                value={m.receipt}
                                                onChange={(v) =>
                                                    updateRow("movements", i, {
                                                        receipt: v,
                                                    })
                                                }
                                            />
                                            <Button
                                                onClick={() =>
                                                    remove("movements", i)
                                                }
                                            >
                                                Entfernen
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        onClick={() =>
                                            change("movements", [
                                                ...form.movements,
                                                {
                                                    kind: "purchase",
                                                    amount: "",
                                                    description: "",
                                                    receipt: "",
                                                },
                                            ])
                                        }
                                    >
                                        Geldbewegung ergänzen
                                    </Button>
                                </Panel>
                                <Panel title="Zählen und Umschlag">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <MoneyField
                                            label="Gezähltes Bargeld vor Umschlagentnahme (€)"
                                            min="0"
                                            value={form.counted}
                                            onChange={(v) =>
                                                change("counted", v)
                                            }
                                        />
                                        <MoneyField
                                            label="Tatsächlich in Umschlag entnommen (€)"
                                            min="0"
                                            value={form.envelope}
                                            onChange={(v) =>
                                                change("envelope", v)
                                            }
                                        />
                                    </div>
                                    <Button
                                        onClick={() =>
                                            change(
                                                "envelope",
                                                amount(
                                                    Math.max(
                                                        0,
                                                        counted - target,
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        Umschlagvorschlag übernehmen (
                                        {euro(Math.max(0, counted - target))})
                                    </Button>
                                    <Totals calc={calc} />
                                    <Field
                                        label="Vermerk / Begründung einer Kassendifferenz"
                                        value={form.note}
                                        onChange={(v) => change("note", v)}
                                    />
                                    {!isHandover && dirty && (
                                        <p className="text-sm text-amber-700">
                                            Ungespeicherte Änderungen: vor dem
                                            Abschließen zuerst den Entwurf
                                            speichern.
                                        </p>
                                    )}
                                    <div className="flex flex-wrap gap-3">
                                        <Button
                                            disabled={busy}
                                            onClick={submit}
                                        >
                                            {isHandover
                                                ? "Übergabe und Umschlag bestätigen"
                                                : "Entwurf speichern"}
                                        </Button>
                                        {!isHandover && (
                                            <Button
                                                disabled={
                                                    busy ||
                                                    dirty ||
                                                    !day.z_report
                                                }
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            "Den gespeicherten Entwurf verbindlich abschließen? Ungespeicherte Eingaben werden nicht übernommen.",
                                                        )
                                                    )
                                                        run(async () => {
                                                            applyDetail(
                                                                (
                                                                    await axios.post(
                                                                        `/api/cash/days/${day.id}/finalize`,
                                                                        {
                                                                            revision:
                                                                                day.revision,
                                                                        },
                                                                    )
                                                                ).data,
                                                            );
                                                            await loadIndex();
                                                        });
                                                }}
                                            >
                                                Gespeicherten Entwurf
                                                abschließen
                                            </Button>
                                        )}
                                    </div>
                                </Panel>
                            </>
                        )}
                    </>
                )}
            </div>
        </TimeTrackingLayout>
    );
}
