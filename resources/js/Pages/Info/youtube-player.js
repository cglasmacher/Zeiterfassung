let api;

export function youtubeEmbedUrl(id, origin, account = false) {
    if (typeof id !== "string" || !/^[\w-]{11}$/.test(id)) {
        throw new Error("Ungültige YouTube-Video-ID.");
    }
    const site = new URL(origin);
    if (!["https:", "http:"].includes(site.protocol)) {
        throw new Error("Ungültige Player-Origin.");
    }
    const url = new URL(
        `/embed/${id}`,
        account
            ? "https://www.youtube.com"
            : "https://www.youtube-nocookie.com",
    );
    url.search = new URLSearchParams({
        enablejsapi: "1",
        origin: site.origin,
        autoplay: "1",
        mute: "1",
        playsinline: "1",
        controls: "1",
        rel: "0",
    }).toString();
    return url.toString();
}

export function readAccountMode(key) {
    try {
        return localStorage.getItem(key) === "account";
    } catch {
        return false;
    }
}

export function saveAccountMode(key, enabled) {
    try {
        localStorage.setItem(key, enabled ? "account" : "private");
    } catch {}
}

export function loadYouTubeAPI() {
    if (window.YT?.Player) return Promise.resolve(window.YT);
    if (api) return api;
    api = new Promise((resolve, reject) => {
        const previous = window.onYouTubeIframeAPIReady;
        const timer = setTimeout(failed, 15000);
        function failed() {
            clearTimeout(timer);
            api = null;
            document.getElementById("tv-youtube-api")?.remove();
            reject(
                new Error("Der YouTube-Player konnte nicht geladen werden."),
            );
        }
        window.onYouTubeIframeAPIReady = () => {
            clearTimeout(timer);
            resolve(window.YT);
            previous?.();
        };
        const script = document.createElement("script");
        script.id = "tv-youtube-api";
        script.src = "https://www.youtube.com/iframe_api";
        script.referrerPolicy = "strict-origin-when-cross-origin";
        script.onerror = failed;
        document.head.append(script);
    });
    return api;
}

export function playerError(code) {
    if ([101, 150].includes(code))
        return "Dieses Video darf hier nicht eingebettet werden. Bitte ein anderes auswählen.";
    if (code === 100)
        return "Dieses Video ist nicht mehr verfügbar oder privat.";
    if (code === 153)
        return "YouTube konnte diesen Player nicht zuordnen. Bitte die TV-Seite neu laden.";
    return "Dieses Video kann gerade nicht abgespielt werden. Bitte ein anderes auswählen.";
}

export function readSelection(key) {
    try {
        const value = JSON.parse(localStorage.getItem(key));
        if (
            value &&
            typeof value.id === "string" &&
            /^[\w-]{11}$/.test(value.id) &&
            typeof value.title === "string"
        ) {
            return { id: value.id, title: value.title.slice(0, 200) };
        }
    } catch {}
    return null;
}

export function saveSelection(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {}
}
