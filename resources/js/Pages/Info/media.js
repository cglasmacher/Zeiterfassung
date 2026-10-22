export function youtubeId(url) {
    try {
        const u = new URL(url);
        const host = u.hostname.replace(/^(www|m|music)\./, "");
        const id =
            host === "youtu.be"
                ? u.pathname.slice(1)
                : host === "youtube.com"
                  ? u.searchParams.get("v") || u.pathname.split("/")[2]
                  : "";
        return /^[\w-]{11}$/.test(id || "") ? id : null;
    } catch {
        return null;
    }
}
export const berlinTime = (value) =>
    value
        ? new Date(value).toLocaleTimeString("de-DE", {
              timeZone: "Europe/Berlin",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "–";
export const berlinDate = (value) =>
    value
        ? new Date(value).toLocaleDateString("de-DE", {
              timeZone: "Europe/Berlin",
              day: "2-digit",
              month: "2-digit",
          })
        : "–";
