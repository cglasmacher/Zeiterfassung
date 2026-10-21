import test from "node:test";
import assert from "node:assert/strict";
import { youtubeId } from "../../resources/js/Pages/Info/media.js";
test("YouTube accepts video links and rejects untrusted hosts and invalid IDs", () => {
    for (const url of [
        "https://youtu.be/dQw4w9WgXcQ",
        "https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5",
        "https://youtube.com/embed/dQw4w9WgXcQ",
        "https://youtube.com/shorts/dQw4w9WgXcQ",
    ])
        assert.equal(youtubeId(url), "dQw4w9WgXcQ");
    for (const url of [
        "https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ",
        "https://evil.example/dQw4w9WgXcQ",
        "invalid",
        "https://youtu.be/bad",
    ])
        assert.equal(youtubeId(url), null);
});
