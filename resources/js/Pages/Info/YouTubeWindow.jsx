import React, { useEffect, useRef, useState } from "react";
import { youtubeId } from "./media";
import {
    loadYouTubeAPI,
    playerError,
    readSelection,
    saveSelection,
    youtubeEmbedUrl,
    readAccountMode,
    saveAccountMode,
} from "./youtube-player";

function Player({ video, sound, selecting, account, onSettings }) {
    const iframe = useRef(null),
        player = useRef(null);
    // Keep the original iframe URL stable: changing sound/feed must not restart playback.
    const initial = useRef(video.id),
        latest = useRef({ video, sound, selecting });
    latest.current = { video, sound, selecting };
    const [ready, setReady] = useState(false),
        [error, setError] = useState("");
    const [playing, setPlaying] = useState(false),
        [attempt, setAttempt] = useState(0);
    const resume = useRef(false);
    useEffect(() => {
        let stopped = false;
        setReady(false);
        setError("");
        loadYouTubeAPI()
            .then((YT) => {
                if (stopped) return;
                player.current = new YT.Player(iframe.current, {
                    events: {
                        onReady: ({ target }) => {
                            if (stopped) return;
                            const state = latest.current;
                            state.sound ? target.unMute() : target.mute();
                            if (state.selecting) target.pauseVideo();
                            setReady(true);
                        },
                        onStateChange: ({ data }) => {
                            if (!stopped) setPlaying(data === 1);
                        },
                        onError: ({ data }) => {
                            if (!stopped) setError(playerError(data));
                        },
                        onAutoplayBlocked: () => {
                            if (!stopped) setPlaying(false);
                        },
                    },
                });
            })
            .catch((e) => {
                if (!stopped) setError(e.message);
            });
        return () => {
            stopped = true;
            player.current?.destroy();
            player.current = null;
        };
    }, [attempt]);
    const loaded = useRef(initial.current);
    useEffect(() => {
        if (!ready) return;
        setError("");
        if (loaded.current !== video.id) {
            player.current.loadVideoById(video.id);
            loaded.current = video.id;
        }
    }, [video.id, ready]);
    useEffect(() => {
        if (ready) sound ? player.current.unMute() : player.current.mute();
    }, [sound, ready]);
    useEffect(() => {
        if (!ready) return;
        if (selecting) {
            resume.current = player.current.getPlayerState() === 1;
            player.current.pauseVideo();
        } else if (resume.current) {
            player.current.playVideo();
            resume.current = false;
        }
    }, [selecting, ready]);
    return (
        <>
            <div
                className={`tv-media tv-youtube-player${error ? " has-error" : ""}`}
                key={attempt}
            >
                <iframe
                    ref={iframe}
                    title="YouTube-Player"
                    src={youtubeEmbedUrl(
                        initial.current,
                        window.location.origin,
                        account,
                    )}
                    allow="autoplay; encrypted-media; fullscreen"
                    allowFullScreen
                    referrerPolicy="strict-origin-when-cross-origin"
                />
                {error && (
                    <p
                        className="tv-youtube-error tv-player-error"
                        role="alert"
                    >
                        {error}{" "}
                        <button
                            type="button"
                            onClick={() => {
                                loaded.current = initial.current;
                                setAttempt((n) => n + 1);
                            }}
                        >
                            Erneut laden
                        </button>
                    </p>
                )}
            </div>
            <div className="tv-youtube-status">
                <span title={video.title}>{video.title}</span>
                <button
                    type="button"
                    aria-haspopup="dialog"
                    aria-label="Kontomodus einrichten"
                    onClick={onSettings}
                >
                    {account ? "Kontomodus an" : "Kontomodus"}
                </button>
                <button
                    type="button"
                    disabled={!ready}
                    onClick={() =>
                        playing
                            ? player.current.pauseVideo()
                            : player.current.playVideo()
                    }
                >
                    {playing ? "Pause" : "Abspielen"}
                </button>
            </div>
        </>
    );
}

function AccountSettings({ enabled, onChange, onClose }) {
    const dialog = useRef(null);
    useEffect(() => {
        const previous = document.activeElement;
        dialog.current.querySelector("button").focus();
        return () => previous?.focus();
    }, []);
    function keys(e) {
        if (e.key === "Escape" || e.key === "BrowserBack") {
            e.preventDefault();
            onClose();
        }
        if (e.key === "Tab") {
            const nodes = [
                ...dialog.current.querySelectorAll("button, a[href]"),
            ];
            const first = nodes[0],
                last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
            if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
    return (
        <div className="tv-search-backdrop">
            <section
                ref={dialog}
                className="tv-search-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tv-account-title"
                onKeyDown={keys}
            >
                <header>
                    <h2 id="tv-account-title">YouTube-Kontomodus testen</h2>
                    <button type="button" onClick={onClose}>
                        Schließen
                    </button>
                </header>
                <p>
                    Im selben Silk-Browser bei YouTube mit dem
                    Hemingway-Premium-Konto anmelden. Danach hier den Kontomodus
                    aktivieren und mehrere Videos im Infoboard prüfen.
                </p>
                <p>
                    Der Kontomodus verwendet den normalen YouTube-Player. Ob
                    YouTube deine Anmeldung und Premium hier erkennt, hängt vom
                    Browser und seinen Cookie-Einstellungen ab. Das Infoboard
                    kann den Kontostatus nicht prüfen und keine Werbefreiheit
                    bestätigen.
                </p>
                <p>
                    <a
                        href="https://www.youtube.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        YouTube im Browser öffnen
                    </a>
                </p>
                <div className="tv-account-actions">
                    <button type="button" onClick={() => onChange(true)}>
                        {enabled
                            ? "Kontomodus erneut starten"
                            : "Kontomodus testen"}
                    </button>
                    <button type="button" onClick={() => onChange(false)}>
                        Ohne Kontomodus
                    </button>
                </div>
                <small>
                    Die Einstellung bleibt auf diesem TV gespeichert. Beim
                    Wechsel startet der Player neu. Die Anmeldung der
                    YouTube-App wird nicht übernommen.
                </small>
            </section>
        </div>
    );
}

function VideoSearch({ base, onSelect, onClose }) {
    const [query, setQuery] = useState(""),
        [submitted, setSubmitted] = useState("");
    const [items, setItems] = useState([]),
        [next, setNext] = useState(null);
    const [busy, setBusy] = useState(false),
        [error, setError] = useState("");
    const input = useRef(null),
        dialog = useRef(null),
        request = useRef(null),
        results = useRef(null);
    useEffect(() => {
        const before = document.activeElement;
        input.current.focus();
        return () => {
            request.current?.abort();
            before?.focus();
        };
    }, []);
    async function search(page = "") {
        const text = (page ? submitted : query).trim();
        const id = youtubeId(text);
        if (id) {
            onSelect({ id, title: "YouTube-Video" });
            return;
        }
        if (text.length < 2) {
            setError(
                "Bitte mindestens zwei Zeichen oder einen YouTube-Video-Link eingeben.",
            );
            return;
        }
        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;
        setBusy(true);
        setError("");
        setItems([]);
        setNext(null);
        setSubmitted(text);
        try {
            const response = await fetch(
                `${base}/youtube/search?${new URLSearchParams({ q: text, ...(page ? { page } : {}) })}`,
                {
                    signal: controller.signal,
                    cache: "no-store",
                    headers: { Accept: "application/json" },
                },
            );
            const data = await response.json().catch(() => ({}));
            if (!response.ok)
                throw new Error(
                    response.status === 429
                        ? "Bitte kurz warten, bevor du erneut suchst."
                        : response.status === 403 || response.status === 404
                          ? "Display-Zugang abgelaufen. Bitte den Fernseher erneut verbinden."
                          : data.message ||
                            "Die Suche ist gerade nicht erreichbar.",
                );
            if (controller.signal.aborted) return;
            setItems(data.items);
            setNext(data.next);
            requestAnimationFrame(() =>
                results.current?.querySelector("button")?.focus(),
            );
        } catch (e) {
            if (!controller.signal.aborted) setError(e.message);
        } finally {
            if (!controller.signal.aborted) setBusy(false);
        }
    }
    function keys(e) {
        if (e.key === "Escape" || e.key === "BrowserBack") {
            e.preventDefault();
            onClose();
        }
        if (e.key === "Tab") {
            const nodes = [
                ...dialog.current.querySelectorAll(
                    "button:not(:disabled), input, a[href]",
                ),
            ];
            const first = nodes[0],
                last = nodes.at(-1);
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
            if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
    function navigate(e) {
        const buttons = [...results.current.querySelectorAll("button")],
            index = buttons.indexOf(document.activeElement);
        if (index < 0) return;
        const columns = getComputedStyle(
            results.current,
        ).gridTemplateColumns.split(" ").length;
        const offset = {
            ArrowRight: 1,
            ArrowLeft: -1,
            ArrowDown: columns,
            ArrowUp: -columns,
        }[e.key];
        if (!offset) return;
        e.preventDefault();
        if (index + offset < 0) input.current.focus();
        else if (index + offset >= buttons.length)
            dialog.current.querySelector("[data-next]")?.focus();
        else buttons[index + offset].focus();
    }
    return (
        <div className="tv-search-backdrop">
            <section
                className="tv-search-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tv-search-title"
                ref={dialog}
                onKeyDown={keys}
            >
                <header>
                    <h2 id="tv-search-title">Video auf YouTube auswählen</h2>
                    <button type="button" onClick={onClose}>
                        Schließen
                    </button>
                </header>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        search();
                    }}
                >
                    <label htmlFor="tv-youtube-query">
                        Suchbegriff oder YouTube-Video-Link
                    </label>
                    <div>
                        <input
                            id="tv-youtube-query"
                            ref={input}
                            value={query}
                            maxLength={120}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="z. B. Jazz Café"
                            autoComplete="off"
                        />
                        <button disabled={busy} type="submit">
                            {busy ? "Sucht …" : "Suchen"}
                        </button>
                    </div>
                </form>
                {error && (
                    <p role="alert" className="tv-youtube-error">
                        {error}
                    </p>
                )}
                {!busy && !error && submitted && !items.length && (
                    <p role="status">Keine passenden Videos gefunden.</p>
                )}
                <div
                    className="tv-search-results"
                    ref={results}
                    onKeyDown={navigate}
                    aria-busy={busy}
                >
                    {items.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => onSelect(item)}
                        >
                            <img
                                src={item.thumbnail}
                                alt=""
                                loading="lazy"
                                referrerPolicy="no-referrer"
                            />
                            <strong>{item.title}</strong>
                            <span>{item.channel}</span>
                        </button>
                    ))}
                </div>
                {next && (
                    <button
                        type="button"
                        data-next
                        disabled={busy}
                        onClick={() => search(next)}
                    >
                        Weitere Ergebnisse
                    </button>
                )}
                <small>
                    YouTube · Auswahl mit Pfeiltasten und OK. Mit Zurück/Escape
                    schließen.
                </small>
            </section>
        </div>
    );
}

export default function YouTubeWindow({ items, sound, base, fallback }) {
    const storageKey = `hemingway:youtube:${base}`;
    const seed = items.find(
        (item) => item.media_type === "youtube" && youtubeId(item.url),
    );
    const [selected, setSelected] = useState(() => readSelection(storageKey));
    const [account, setAccount] = useState(() =>
        readAccountMode(`${storageKey}:account`),
    );
    const [settings, setSettings] = useState(false);
    const [playerVersion, setPlayerVersion] = useState(0);
    const video =
        selected ||
        (seed ? { id: youtubeId(seed.url), title: seed.title } : null);
    const [open, setOpen] = useState(false),
        [mode, setMode] = useState(() => {
            try {
                return localStorage.getItem(`${storageKey}:mode`) === "media"
                    ? "media"
                    : "youtube";
            } catch {
                return "youtube";
            }
        });
    function choose(item) {
        const value = { id: item.id, title: item.title };
        setSelected(value);
        saveSelection(storageKey, value);
        setMode("youtube");
        saveMode("youtube");
        setOpen(false);
    }
    function saveMode(value) {
        try {
            localStorage.setItem(`${storageKey}:mode`, value);
        } catch {}
    }
    return (
        <>
            <div className="tv-youtube-window">
                <div className="tv-youtube-toolbar">
                    <strong>{mode === "youtube" ? "YouTube" : "Medien"}</strong>
                    <button type="button" onClick={() => setOpen(true)}>
                        Video auswählen
                    </button>
                    {items.length > 0 && (
                        <button
                            type="button"
                            onClick={() => {
                                const value =
                                    mode === "youtube" ? "media" : "youtube";
                                setMode(value);
                                saveMode(value);
                            }}
                        >
                            {mode === "youtube"
                                ? "Medien anzeigen"
                                : "Zum YouTube-Player"}
                        </button>
                    )}
                </div>
                {mode === "media" ? (
                    fallback
                ) : video ? (
                    <Player
                        key={`${account}:${playerVersion}`}
                        video={video}
                        sound={sound}
                        selecting={open || settings}
                        account={account}
                        onSettings={() => setSettings(true)}
                    />
                ) : (
                    <div className="tv-placeholder tv-youtube-empty">
                        <img src="/brand/hemingway-logo.png" alt="Hemingway" />
                        <p>Wähle ein Video für euren Küchen-TV.</p>
                        <button type="button" onClick={() => setOpen(true)}>
                            YouTube öffnen
                        </button>
                    </div>
                )}
            </div>
            {open && (
                <VideoSearch
                    base={base}
                    onSelect={choose}
                    onClose={() => setOpen(false)}
                />
            )}
            {settings && (
                <AccountSettings
                    enabled={account}
                    onClose={() => setSettings(false)}
                    onChange={(value) => {
                        setAccount(value);
                        saveAccountMode(`${storageKey}:account`, value);
                        setPlayerVersion((version) => version + 1);
                        setSettings(false);
                    }}
                />
            )}
        </>
    );
}
