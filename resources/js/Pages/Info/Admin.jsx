import React, { useEffect, useState } from "react";
import axios from "axios";
import TimeTrackingLayout from "@/Layouts/TimeTrackingLayout";
import { berlinDate, berlinTime } from "./media";

const api = "/api/infosystem";
const labels = {
    notice: "Mitteilungen",
    task: "Aufgaben",
    event: "Events",
    media: "Medien",
    displays: "Displays",
    resmio: "resmio",
};
const fresh = (kind) => ({
    kind,
    title: "",
    body: "",
    display_id: "",
    employee_id: "",
    starts_at: "",
    ends_at: "",
    due_at: "",
    priority: 0,
    active: true,
    media_type: "image",
    url: "",
    duration: 20,
    sort_order: 0,
});
const localTime = (value) => {
    if (!value) return "";
    const d = new Date(value);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
};
function Field({ label, children }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">{label}</span>
            {children}
        </label>
    );
}
const input = "rounded-lg border-neutral-300 w-full";
export default function Admin() {
    const [data, setData] = useState(null),
        [tab, setTab] = useState("notice"),
        [edit, setEdit] = useState(null),
        [file, setFile] = useState(null),
        [busy, setBusy] = useState(false),
        [error, setError] = useState(""),
        [message, setMessage] = useState(""),
        [connect, setConnect] = useState(""),
        [settings, setSettings] = useState(null);
    const load = async () => {
        const r = await axios.get(api);
        setData(r.data);
        return r.data;
    };
    useEffect(() => {
        load().catch(() => setError("Infosystem konnte nicht geladen werden."));
    }, []);
    const action = async (fn, success) => {
        setBusy(true);
        setError("");
        setMessage("");
        try {
            await fn();
            await load();
            if (success) setMessage(success);
        } catch (e) {
            setError(
                Object.values(e.response?.data?.errors || {})
                    .flat()
                    .join(" · ") ||
                    e.response?.data?.message ||
                    "Aktion fehlgeschlagen. Bitte erneut versuchen.",
            );
        } finally {
            setBusy(false);
        }
    };
    const change = (key, value) => setEdit((e) => ({ ...e, [key]: value }));
    const open = (item) => {
        setFile(null);
        setEdit({
            ...item,
            ...Object.fromEntries(
                ["starts_at", "ends_at", "due_at"].map((k) => [
                    k,
                    localTime(item[k]),
                ]),
            ),
        });
    };
    const saveItem = (e) => {
        e.preventDefault();
        action(async () => {
            const form = new FormData();
            for (const [k, v] of Object.entries(edit)) {
                if (
                    [
                        "id",
                        "created_at",
                        "updated_at",
                        "completed_at",
                        "employee",
                    ].includes(k)
                )
                    continue;
                let value = v;
                if (["starts_at", "ends_at", "due_at"].includes(k))
                    value = v ? new Date(v).toISOString() : "";
                form.append(
                    k,
                    typeof value === "boolean"
                        ? value
                            ? "1"
                            : "0"
                        : (value ?? ""),
                );
            }
            if (file) form.append("file", file);
            await axios.post(
                api + "/items" + (edit.id ? "/" + edit.id : ""),
                form,
            );
            setEdit(null);
            setFile(null);
        }, "Gespeichert.");
    };
    const selectTab = (t) => {
        setTab(t);
        setEdit(null);
        setConnect("");
        if (t === "resmio" && data)
            setSettings({
                ...data.integration,
                authorization: "",
                webhook_password: "",
            });
    };
    if (!data)
        return (
            <TimeTrackingLayout>
                <p role="status">{error || "Infosystem wird geladen …"}</p>
            </TimeTrackingLayout>
        );
    const displayName = (id) =>
        data.displays.find((d) => d.id === id)?.name || "Alle Displays";
    return (
        <TimeTrackingLayout>
            <div className="space-y-5">
                <header>
                    <h1 className="text-2xl font-semibold">
                        Hemingway Infosystem
                    </h1>
                    <p className="text-neutral-600 mt-1">
                        Aktuelles für die Küche und das Team. Änderungen
                        erscheinen automatisch auf den verbundenen Fernsehern.
                    </p>
                </header>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {[
                        [
                            "Offene Aufgaben",
                            data.items.filter(
                                (i) => i.kind === "task" && !i.completed_at,
                            ).length,
                        ],
                        [
                            "Aktive Displays",
                            data.displays.filter((d) => d.active).length,
                        ],
                        [
                            "Reservierungen",
                            data.reservations.filter(
                                (r) =>
                                    ![
                                        "deleted",
                                        "cancelled",
                                        "canceled",
                                        "removed",
                                    ].includes(r.status),
                            ).length,
                        ],
                        [
                            "resmio",
                            data.integration.enabled
                                ? data.integration.last_error
                                    ? "Gestört"
                                    : "Aktiv"
                                : "Nicht verbunden",
                        ],
                    ].map(([title, value]) => (
                        <div key={title} className="card p-4">
                            <p className="text-neutral-600 text-sm">{title}</p>
                            <strong className="text-xl">{value}</strong>
                        </div>
                    ))}
                </div>
                <nav
                    className="flex gap-2 flex-wrap"
                    aria-label="Infosystem-Bereiche"
                >
                    {Object.entries(labels).map(([key, label]) => (
                        <button
                            key={key}
                            className={`btn ${tab === key ? "bg-primary-600 text-white" : "bg-white border"}`}
                            onClick={() => selectTab(key)}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {error && (
                    <div
                        role="alert"
                        className="p-4 bg-red-50 text-red-800 rounded-lg"
                    >
                        {error}
                    </div>
                )}
                {message && (
                    <div
                        role="status"
                        className="p-4 bg-green-50 text-green-800 rounded-lg"
                    >
                        {message}
                    </div>
                )}
                {["notice", "task", "event", "media"].includes(tab) && (
                    <>
                        <div className="flex justify-between items-center">
                            <h2 className="text-lg font-semibold">
                                {labels[tab]}
                            </h2>
                            <button
                                disabled={busy}
                                className="btn btn-primary"
                                onClick={() => open(fresh(tab))}
                            >
                                + Neu
                            </button>
                        </div>
                        {edit && (
                            <form
                                onSubmit={saveItem}
                                className="card p-5 space-y-4"
                            >
                                <h3 className="font-semibold">
                                    {edit.id
                                        ? "Eintrag bearbeiten"
                                        : "Neuer Eintrag"}
                                </h3>
                                <div className="grid md:grid-cols-2 gap-4">
                                    <Field label="Titel">
                                        <input
                                            required
                                            maxLength={255}
                                            className={input}
                                            value={edit.title}
                                            onChange={(e) =>
                                                change("title", e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Display">
                                        <select
                                            className={input}
                                            value={edit.display_id || ""}
                                            onChange={(e) =>
                                                change(
                                                    "display_id",
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Alle Displays
                                            </option>
                                            {data.displays.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label="Text">
                                        <textarea
                                            rows={3}
                                            maxLength={10000}
                                            className={input}
                                            value={edit.body || ""}
                                            onChange={(e) =>
                                                change("body", e.target.value)
                                            }
                                        />
                                    </Field>
                                    <div className="space-y-3">
                                        <Field label="Priorität">
                                            <select
                                                className={input}
                                                value={edit.priority}
                                                onChange={(e) =>
                                                    change(
                                                        "priority",
                                                        Number(e.target.value),
                                                    )
                                                }
                                            >
                                                <option value={0}>
                                                    Normal
                                                </option>
                                                <option value={1}>Hoch</option>
                                                <option value={2}>
                                                    Dringend
                                                </option>
                                            </select>
                                        </Field>
                                        <label>
                                            <input
                                                type="checkbox"
                                                checked={edit.active}
                                                onChange={(e) =>
                                                    change(
                                                        "active",
                                                        e.target.checked,
                                                    )
                                                }
                                            />{" "}
                                            Aktiv auf dem TV
                                        </label>
                                    </div>
                                    <Field
                                        label={
                                            tab === "event"
                                                ? "Eventbeginn (Ortszeit)"
                                                : "Sichtbar ab (Ortszeit)"
                                        }
                                    >
                                        <input
                                            type="datetime-local"
                                            required={tab === "event"}
                                            className={input}
                                            value={edit.starts_at}
                                            onChange={(e) =>
                                                change(
                                                    "starts_at",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field label="Sichtbar bis / Eventende (Ortszeit)">
                                        <input
                                            type="datetime-local"
                                            className={input}
                                            value={edit.ends_at}
                                            onChange={(e) =>
                                                change(
                                                    "ends_at",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    {tab === "task" && (
                                        <>
                                            <Field label="Verantwortlich">
                                                <select
                                                    className={input}
                                                    value={
                                                        edit.employee_id || ""
                                                    }
                                                    onChange={(e) =>
                                                        change(
                                                            "employee_id",
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Gesamtes Team
                                                    </option>
                                                    {data.employees.map((e) => (
                                                        <option
                                                            key={e.id}
                                                            value={e.id}
                                                        >
                                                            {e.first_name}{" "}
                                                            {e.last_name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </Field>
                                            <Field label="Fällig (Ortszeit)">
                                                <input
                                                    type="datetime-local"
                                                    className={input}
                                                    value={edit.due_at}
                                                    onChange={(e) =>
                                                        change(
                                                            "due_at",
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                    {tab === "media" && (
                                        <>
                                            <Field label="Medientyp">
                                                <select
                                                    className={input}
                                                    value={edit.media_type}
                                                    onChange={(e) =>
                                                        change(
                                                            "media_type",
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="image">
                                                        Bild
                                                    </option>
                                                    <option value="video">
                                                        Video
                                                    </option>
                                                    <option value="youtube">
                                                        YouTube
                                                    </option>
                                                </select>
                                            </Field>
                                            <Field label="HTTPS-Link">
                                                <input
                                                    type="url"
                                                    className={input}
                                                    value={edit.url || ""}
                                                    placeholder="https://…"
                                                    onChange={(e) =>
                                                        change(
                                                            "url",
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            {edit.media_type !== "youtube" && (
                                                <Field label="Oder Datei hochladen (max. 100 MB)">
                                                    <input
                                                        type="file"
                                                        accept={
                                                            edit.media_type ===
                                                            "image"
                                                                ? ".jpg,.jpeg,.png,.webp"
                                                                : ".mp4,.webm"
                                                        }
                                                        onChange={(e) =>
                                                            setFile(
                                                                e.target
                                                                    .files[0] ||
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                    <small>
                                                        Bei vorhandenen Dateien
                                                        bleibt das Medium ohne
                                                        neuen Upload erhalten.
                                                    </small>
                                                </Field>
                                            )}
                                            <Field label="Anzeigedauer in Sekunden">
                                                <input
                                                    type="number"
                                                    min={5}
                                                    max={7200}
                                                    className={input}
                                                    value={edit.duration}
                                                    onChange={(e) =>
                                                        change(
                                                            "duration",
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field label="Reihenfolge (kleine Zahlen zuerst)">
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={10000}
                                                    className={input}
                                                    value={edit.sort_order}
                                                    onChange={(e) =>
                                                        change(
                                                            "sort_order",
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        disabled={busy}
                                        className="btn btn-primary"
                                    >
                                        {busy ? "Speichert …" : "Speichern"}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn border"
                                        onClick={() => setEdit(null)}
                                    >
                                        Abbrechen
                                    </button>
                                </div>
                            </form>
                        )}
                        <div className="space-y-3">
                            {data.items
                                .filter((i) => i.kind === tab)
                                .map((i) => (
                                    <article
                                        key={i.id}
                                        className="card p-4 flex flex-wrap gap-4 justify-between"
                                    >
                                        <div className="min-w-0">
                                            <h3
                                                className={`font-semibold ${i.completed_at ? "line-through text-neutral-500" : ""}`}
                                            >
                                                {i.title}
                                            </h3>
                                            <p className="text-sm text-neutral-600 whitespace-pre-wrap break-words">
                                                {i.body}
                                            </p>
                                            <p className="text-xs text-neutral-500 mt-2">
                                                {displayName(i.display_id)} ·{" "}
                                                {i.active ? "Aktiv" : "Inaktiv"}
                                                {i.starts_at
                                                    ? ` · ${berlinDate(i.starts_at)} ${berlinTime(i.starts_at)}`
                                                    : ""}
                                                {i.due_at
                                                    ? ` · fällig ${berlinDate(i.due_at)} ${berlinTime(i.due_at)}`
                                                    : ""}
                                                {i.completed_at
                                                    ? " · Erledigt"
                                                    : ""}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2 items-start">
                                            {tab === "task" && (
                                                <button
                                                    disabled={busy}
                                                    className="btn border"
                                                    onClick={() =>
                                                        action(
                                                            () =>
                                                                axios.patch(
                                                                    `${api}/items/${i.id}/complete`,
                                                                    {
                                                                        completed:
                                                                            !i.completed_at,
                                                                    },
                                                                ),
                                                            "Aufgabe aktualisiert.",
                                                        )
                                                    }
                                                >
                                                    {i.completed_at
                                                        ? "Wieder öffnen"
                                                        : "Erledigt"}
                                                </button>
                                            )}
                                            <button
                                                className="btn border"
                                                onClick={() => open(i)}
                                            >
                                                Bearbeiten
                                            </button>
                                            <button
                                                disabled={busy}
                                                className="btn text-red-700"
                                                onClick={() =>
                                                    action(
                                                        () =>
                                                            axios.delete(
                                                                `${api}/items/${i.id}`,
                                                            ),
                                                        "Gelöscht.",
                                                    )
                                                }
                                            >
                                                Löschen
                                            </button>
                                        </div>
                                    </article>
                                ))}
                            {!data.items.some((i) => i.kind === tab) && (
                                <p className="card p-5 text-neutral-600">
                                    Noch keine Einträge. Über „Neu“ kannst du
                                    den ersten anlegen.
                                </p>
                            )}
                        </div>
                    </>
                )}
                {tab === "displays" && (
                    <>
                        <div className="flex justify-between">
                            <h2 className="font-semibold text-lg">
                                Displays verwalten
                            </h2>
                            <button
                                className="btn btn-primary"
                                onClick={() =>
                                    setEdit({
                                        slug: "",
                                        name: "",
                                        active: true,
                                        show_guest_names: false,
                                        department_id: "",
                                    })
                                }
                            >
                                + Display
                            </button>
                        </div>
                        {connect && (
                            <div className="card p-5 bg-green-50 space-y-2">
                                <h3 className="font-semibold">
                                    Verbindungslink – nur jetzt sichtbar
                                </h3>
                                <p>
                                    Diesen Link einmal im Browser des Fernsehers
                                    öffnen. Er verbindet das Display dauerhaft.
                                    Bisherige Zugänge wurden widerrufen.
                                </p>
                                <input
                                    aria-label="Display-Verbindungslink"
                                    readOnly
                                    className={input}
                                    value={connect}
                                    onFocus={(e) => e.target.select()}
                                />
                                <a
                                    className="underline"
                                    href={connect}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Display öffnen
                                </a>
                            </div>
                        )}
                        {edit && (
                            <form
                                className="card p-5 space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    action(async () => {
                                        await axios[edit.id ? "put" : "post"](
                                            api +
                                                "/displays" +
                                                (edit.id ? "/" + edit.id : ""),
                                            edit,
                                        );
                                        setEdit(null);
                                    }, "Display gespeichert.");
                                }}
                            >
                                <div className="grid md:grid-cols-2 gap-4">
                                    <Field label="Name">
                                        <input
                                            required
                                            className={input}
                                            value={edit.name}
                                            onChange={(e) =>
                                                change("name", e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Kennung im Link">
                                        <input
                                            required
                                            pattern="[A-Za-z0-9_-]+"
                                            className={input}
                                            value={edit.slug}
                                            onChange={(e) =>
                                                change("slug", e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Dienstplan-Abteilung">
                                        <select
                                            className={input}
                                            value={edit.department_id || ""}
                                            onChange={(e) =>
                                                change(
                                                    "department_id",
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Alle Abteilungen
                                            </option>
                                            {data.departments.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <div className="space-y-2">
                                        <label className="block">
                                            <input
                                                type="checkbox"
                                                checked={edit.active}
                                                onChange={(e) =>
                                                    change(
                                                        "active",
                                                        e.target.checked,
                                                    )
                                                }
                                            />{" "}
                                            Display aktiv
                                        </label>
                                        <label className="block">
                                            <input
                                                type="checkbox"
                                                checked={edit.show_guest_names}
                                                onChange={(e) =>
                                                    change(
                                                        "show_guest_names",
                                                        e.target.checked,
                                                    )
                                                }
                                            />{" "}
                                            Gastnamen auf diesem TV anzeigen
                                        </label>
                                    </div>
                                </div>
                                <button
                                    disabled={busy}
                                    className="btn btn-primary"
                                >
                                    Speichern
                                </button>{" "}
                                <button
                                    type="button"
                                    className="btn border"
                                    onClick={() => setEdit(null)}
                                >
                                    Abbrechen
                                </button>
                            </form>
                        )}
                        {data.displays.map((d) => (
                            <article
                                className="card p-5 flex flex-wrap justify-between gap-4"
                                key={d.id}
                            >
                                <div>
                                    <h3 className="font-semibold">
                                        {d.name} ·{" "}
                                        {d.active ? "Aktiv" : "Deaktiviert"}
                                    </h3>
                                    <p className="text-neutral-600">
                                        /display/{d.slug} · Zuletzt verbunden:{" "}
                                        {d.last_seen_at
                                            ? `${berlinDate(d.last_seen_at)} ${berlinTime(d.last_seen_at)}`
                                            : "noch nicht"}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        className="btn border"
                                        onClick={() =>
                                            setEdit({
                                                ...d,
                                                department_id:
                                                    d.department_id || "",
                                            })
                                        }
                                    >
                                        Bearbeiten
                                    </button>
                                    <button
                                        disabled={busy}
                                        className="btn border"
                                        onClick={() =>
                                            action(async () => {
                                                const r = await axios.post(
                                                    `${api}/displays/${d.id}/rotate`,
                                                );
                                                setConnect(r.data.connect_url);
                                            })
                                        }
                                    >
                                        Zugang neu erzeugen
                                    </button>
                                </div>
                            </article>
                        ))}
                    </>
                )}
                {tab === "resmio" && settings && (
                    <>
                        <form
                            className="card p-5 space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                action(
                                    () =>
                                        axios.put(
                                            api + "/integration",
                                            settings,
                                        ),
                                    "resmio-Einstellungen gespeichert.",
                                );
                            }}
                        >
                            <h2 className="font-semibold text-lg">
                                resmio-Anbindung
                            </h2>
                            <p className="text-neutral-600">
                                Den reinen API-Key aus eurem resmio-Konto eintragen, ohne Bearer oder sonstiges Präfix.
                                Gespeicherte Zugangsdaten werden nicht zurück
                                angezeigt. Leere Passwortfelder behalten den
                                bisherigen Wert.
                            </p>
                            <div className="grid md:grid-cols-2 gap-4">
                                <Field label="Facility-ID">
                                    <input
                                        className={input}
                                        value={settings.facility || ""}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                facility: e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                                <Field
                                    label={`API-Key ${data.has_authorization ? "(gespeichert)" : ""}`}
                                >
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={input}
                                        value={settings.authorization}
                                        placeholder="Reinen API-Key aus resmio einfügen"
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                authorization: e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                                <Field label="Webhook-Benutzer">
                                    <input
                                        className={input}
                                        value={settings.webhook_user || ""}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                webhook_user: e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                                <Field
                                    label={`Webhook-Passwort ${data.has_webhook_password ? "(gespeichert)" : ""}`}
                                >
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={input}
                                        value={settings.webhook_password}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                webhook_password:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            </div>
                            <label className="block">
                                <input
                                    type="checkbox"
                                    checked={settings.enabled}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            enabled: e.target.checked,
                                        })
                                    }
                                />{" "}
                                Automatische Synchronisation aktivieren
                            </label>
                            <p className="text-sm">
                                Webhook-Ziel:{" "}
                                <code className="break-all">
                                    https://dienstplan.hemingway-hilden.de/api/integrations/resmio/webhook
                                </code>
                                <br />
                                In resmio die drei Reservierungsaktionen mit
                                Basic Auth hinterlegen.
                            </p>
                            <button disabled={busy} className="btn btn-primary">
                                Einstellungen speichern
                            </button>
                        </form>
                        <div className="card p-5 space-y-3">
                            <p>
                                Letzter erfolgreicher Abgleich:{" "}
                                {data.integration.last_success_at
                                    ? `${berlinDate(data.integration.last_success_at)} ${berlinTime(data.integration.last_success_at)}`
                                    : "noch keiner"}
                            </p>
                            {data.integration.last_error && (
                                <p className="text-red-700">
                                    {data.integration.last_error}
                                </p>
                            )}
                            <button
                                disabled={busy}
                                className="btn border"
                                onClick={() =>
                                    action(async () => {
                                        const r = await axios.post(
                                            api + "/sync",
                                        );
                                        setMessage(
                                            `${r.data.count} Reservierungen abgeglichen.`,
                                        );
                                    })
                                }
                            >
                                {busy
                                    ? "Synchronisiert …"
                                    : "Jetzt synchronisieren"}
                            </button>
                            <p className="text-xs text-neutral-500">
                                Regelmäßiger Abgleich alle fünf Minuten benötigt
                                den Laravel-Scheduler. Webhooks werden über die
                                Queue verarbeitet.
                            </p>
                        </div>
                        <div className="card p-5">
                            <h3 className="font-semibold mb-3">
                                Nächste Reservierungen
                            </h3>
                            {data.reservations.map((r) => (
                                <p key={r.id} className="py-2 border-b">
                                    {berlinDate(r.starts_at)}{" "}
                                    {berlinTime(r.starts_at)} · {r.people}{" "}
                                    Personen · {r.guest_name || "Gast"} ·{" "}
                                    {r.status}
                                </p>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </TimeTrackingLayout>
    );
}
