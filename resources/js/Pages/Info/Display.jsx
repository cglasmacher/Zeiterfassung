import React, { useEffect, useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import { youtubeId, berlinTime, berlinDate } from "./media";
import "./display.css";

function Media({ items, sound }) {
    const [index, setIndex] = useState(0);
    const item = items[index % Math.max(items.length, 1)];
    const next = () => setIndex((i) => i + 1);
    const signature = items.map((i) => `${i.id}:${i.updated_at}`).join(",");
    useEffect(() => {
        setIndex(0);
    }, [signature]);
    useEffect(() => {
        if (!item) return;
        const timer = setTimeout(next, item.duration * 1000);
        return () => clearTimeout(timer);
    }, [item?.id, item?.updated_at, index, signature]);
    if (!item)
        return (
            <div className="tv-placeholder">
                <img src="/brand/hemingway-logo.png" alt="Hemingway" />
                <p>Gemeinsam einen guten Tag gestalten.</p>
            </div>
        );
    const id = youtubeId(item.url);
    return (
        <div className="tv-media">
            {item.media_type === "youtube" && id ? (
                <iframe
                    key={`${item.id}-${index}-${sound}`}
                    title={item.title}
                    src={`https://www.youtube-nocookie.com/embed/${id}?autoplay=1&mute=${sound ? 0 : 1}&playsinline=1&rel=0&loop=1&playlist=${id}`}
                    allow="autoplay; encrypted-media; fullscreen"
                    allowFullScreen
                    referrerPolicy="no-referrer"
                />
            ) : item.media_type === "video" ? (
                <video
                    key={item.id + ":" + index + ":" + item.url}
                    src={item.url}
                    autoPlay
                    muted={!sound}
                    playsInline
                    onEnded={next}
                    onError={next}
                    controls
                />
            ) : item.media_type === "image" ? (
                <img src={item.url} alt={item.title} onError={next} />
            ) : (
                <p>Dieses Medium kann nicht angezeigt werden.</p>
            )}
            <span className="tv-media-title">{item.title}</span>
        </div>
    );
}
function Pages({ items, size = 5, render, empty }) {
    const [page, setPage] = useState(0);
    useEffect(() => {
        const t = setInterval(() => setPage((p) => p + 1), 10000);
        return () => clearInterval(t);
    }, []);
    const count = Math.max(1, Math.ceil(items.length / size));
    const offset = (page % count) * size;
    return (
        <>
            {items.length ? (
                items.slice(offset, offset + size).map(render)
            ) : (
                <p className="tv-empty">{empty}</p>
            )}
            {count > 1 && (
                <small>
                    Seite {(page % count) + 1} / {count}
                </small>
            )}
        </>
    );
}
export default function Display({ feed: initial }) {
    const [feed, setFeed] = useState(initial),
        [online, setOnline] = useState(true),
        [revoked, setRevoked] = useState(false);
    const [clock, setClock] = useState(new Date()),
        [sound, setSound] = useState(false);
    const lastSuccess = useRef(Date.now());
    const base = "/display/" + initial.display.slug;
    useEffect(() => {
        let stopped = false;
        const controller = new AbortController();
        const accept = (data) => {
            if (!stopped) {
                setFeed(data);
                setOnline(true);
                lastSuccess.current = Date.now();
            }
        };
        const source = new EventSource(base + "/events");
        source.addEventListener("update", (e) => {
            try {
                accept(JSON.parse(e.data));
            } catch {}
        });
        source.addEventListener("revoked", () => {
            source.close();
            setRevoked(true);
            setFeed(null);
        });
        const poll = async () => {
            try {
                const r = await fetch(base + "/data", {
                    signal: controller.signal,
                    cache: "no-store",
                });
                if (r.status === 403 || r.status === 404) {
                    setRevoked(true);
                    setFeed(null);
                    source.close();
                    return;
                }
                if (!r.ok) throw Error();
                accept(await r.json());
            } catch {
                if (!stopped && Date.now() - lastSuccess.current > 30000)
                    setOnline(false);
            }
        };
        poll();
        const timer = setInterval(poll, 15000);
        const tick = setInterval(() => setClock(new Date()), 1000);
        return () => {
            stopped = true;
            controller.abort();
            source.close();
            clearInterval(timer);
            clearInterval(tick);
        };
    }, [base]);
    if (revoked || !feed)
        return (
            <div className="tv-revoked">
                Display-Zugang widerrufen. Bitte über das Infosystem neu
                verbinden.
            </div>
        );
    const items = feed.items,
        notices = items.filter((i) => i.kind === "notice"),
        tasks = items
            .filter((i) => i.kind === "task")
            .sort(
                (a, b) =>
                    b.priority - a.priority ||
                    (a.due_at || "9999").localeCompare(b.due_at || "9999"),
            ),
        events = items
            .filter((i) => i.kind === "event")
            .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    const fullscreen = () => {
        if (!document.fullscreenElement)
            document.documentElement.requestFullscreen?.().catch(() => {});
        else document.exitFullscreen?.();
    };
    return (
        <div className="tv-shell">
            <Head title={`Hemingway · ${feed.display.name}`} />
            <header className="tv-header">
                <img src="/brand/hemingway-logo.png" alt="Hemingway" />
                <div>
                    <span>HEMINGWAY · TEAM INFO</span>
                    <h1>{feed.display.name}</h1>
                </div>
                <div className="tv-clock">
                    <strong>
                        {clock.toLocaleTimeString("de-DE", {
                            timeZone: "Europe/Berlin",
                            hour: "2-digit",
                            minute: "2-digit",
                        })}
                    </strong>
                    <span>
                        {clock.toLocaleDateString("de-DE", {
                            timeZone: "Europe/Berlin",
                            weekday: "long",
                            day: "2-digit",
                            month: "long",
                        })}
                    </span>
                </div>
            </header>
            {!online && (
                <div className="tv-warning">
                    Verbindung unterbrochen · Letzter Stand wird angezeigt
                </div>
            )}
            <main className="tv-grid">
                <section className="tv-screen">
                    <Media
                        items={items.filter((i) => i.kind === "media")}
                        sound={sound}
                    />
                    <div className="tv-notices">
                        <Pages
                            items={notices}
                            size={1}
                            empty="Aktuell keine Mitteilungen."
                            render={(i) => (
                                <article key={i.id}>
                                    <small>
                                        {i.priority === 2
                                            ? "WICHTIG"
                                            : "MITTEILUNG"}
                                    </small>
                                    <h2>{i.title}</h2>
                                    <p>{i.body}</p>
                                </article>
                            )}
                        />
                    </div>
                </section>
                <section className="tv-panel">
                    <h2>
                        Reservierungen heute{" "}
                        <b>
                            {feed.reservations.reduce(
                                (n, r) => n + r.people,
                                0,
                            )}{" "}
                            Gäste
                        </b>
                    </h2>
                    <Pages
                        items={feed.reservations}
                        size={5}
                        empty="Keine Reservierungen vorhanden."
                        render={(r) => (
                            <article key={r.id} className="tv-row">
                                <strong>{berlinTime(r.starts_at)}</strong>
                                <div>
                                    <b>{r.people} Personen</b>
                                    <p>
                                        {r.guest_name ||
                                            (r.tables
                                                ? `Tisch ${r.tables}`
                                                : "Reservierung")}
                                        {r.guest_name && r.tables
                                            ? ` · ${r.tables}`
                                            : ""}
                                    </p>
                                </div>
                            </article>
                        )}
                    />
                    <small className="tv-sync">
                        {!feed.resmio.enabled
                            ? "resmio noch nicht verbunden"
                            : feed.resmio.failed
                              ? "resmio: Synchronisation gestört"
                              : feed.resmio.last_success_at
                                ? `resmio · Stand ${berlinDate(feed.resmio.last_success_at)} ${berlinTime(feed.resmio.last_success_at)}`
                                : "resmio: erster Abgleich ausstehend"}
                    </small>
                </section>
                <section className="tv-panel">
                    <h2>
                        Aufgaben <b>{tasks.length} offen</b>
                    </h2>
                    <Pages
                        items={tasks}
                        size={4}
                        empty="Alle Aufgaben erledigt."
                        render={(i) => (
                            <article
                                className={`tv-task priority-${i.priority}`}
                                key={i.id}
                            >
                                <h3>{i.title}</h3>
                                <p>{i.body}</p>
                                <small>
                                    {i.employee
                                        ? `${i.employee.first_name} ${i.employee.last_name}`
                                        : "Team"}
                                    {i.due_at
                                        ? ` · bis ${berlinDate(i.due_at)} ${berlinTime(i.due_at)}`
                                        : ""}
                                </small>
                            </article>
                        )}
                    />
                </section>
                <section className="tv-panel">
                    <h2>Demnächst</h2>
                    <Pages
                        items={events}
                        size={3}
                        empty="Keine anstehenden Events."
                        render={(i) => (
                            <article className="tv-row" key={i.id}>
                                <strong>{berlinDate(i.starts_at)}</strong>
                                <div>
                                    <h3>{i.title}</h3>
                                    <small>{berlinTime(i.starts_at)}</small>
                                    <p>{i.body}</p>
                                </div>
                            </article>
                        )}
                    />
                </section>
                <section className="tv-panel tv-team">
                    <h2>Heute im Team</h2>
                    <Pages
                        items={feed.shifts}
                        size={5}
                        empty="Keine Schichten eingetragen."
                        render={(s) => (
                            <article className="tv-row" key={s.id}>
                                <b>{s.name}</b>
                                <span>
                                    {s.department} ·{" "}
                                    {s.start?.slice(0, 5) || "–"} –{" "}
                                    {s.end?.slice(0, 5) || "–"}
                                </span>
                            </article>
                        )}
                    />
                </section>
            </main>
            <footer className="tv-footer">
                <span className={online ? "tv-live" : "tv-offline"}>
                    {online ? "● Live" : "● Offline"}
                </span>
                <span>Hemingway · Hilden</span>
                <div>
                    <button onClick={() => setSound(!sound)}>
                        {sound ? "Ton aus" : "Ton an"}
                    </button>
                    <button onClick={fullscreen}>Vollbild</button>
                </div>
            </footer>
        </div>
    );
}
