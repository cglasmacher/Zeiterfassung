let api;

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
