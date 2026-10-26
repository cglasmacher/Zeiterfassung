import test from "node:test";
import assert from "node:assert/strict";
import {
    readSelection,
    saveSelection,
    playerError,
    youtubeEmbedUrl,
    readAccountMode,
    saveAccountMode,
} from "../../resources/js/Pages/Info/youtube-player.js";

test("YouTube account test uses the regular host and sends only the embedding origin", () => {
    const id = "abcdefghijk";
    const origin =
        "https://stream.hemingway-hilden.de/display/kitchen/connect?token=private-token";
    const standard = new URL(youtubeEmbedUrl(id, origin, true));
    assert.equal(standard.hostname, "www.youtube.com");
    assert.equal(standard.pathname, `/embed/${id}`);
    assert.equal(
        standard.searchParams.get("origin"),
        "https://stream.hemingway-hilden.de",
    );
    assert.equal(standard.searchParams.get("enablejsapi"), "1");
    assert.equal(standard.searchParams.get("mute"), "1");
    assert.ok(!standard.href.includes("private-token"));
    assert.equal(
        new URL(youtubeEmbedUrl(id, origin)).hostname,
        "www.youtube-nocookie.com",
    );
    assert.throws(() => youtubeEmbedUrl("https://evil.test", origin));
    assert.throws(() => youtubeEmbedUrl(id, "javascript:alert(1)"));
});

test("Account mode is explicitly enabled per display and works with unavailable storage", () => {
    const storage = new Map();
    globalThis.localStorage = {
        getItem: (key) => storage.get(key),
        setItem: (key, value) => storage.set(key, value),
    };
    assert.equal(readAccountMode("kitchen"), false);
    saveAccountMode("kitchen", true);
    assert.equal(readAccountMode("kitchen"), true);
    assert.equal(readAccountMode("bar"), false);
    saveAccountMode("kitchen", false);
    assert.equal(readAccountMode("kitchen"), false);
    globalThis.localStorage = {
        getItem() {
            throw Error("blocked");
        },
        setItem() {
            throw Error("blocked");
        },
    };
    assert.equal(readAccountMode("kitchen"), false);
    assert.doesNotThrow(() => saveAccountMode("kitchen", true));
    delete globalThis.localStorage;
});

test("TV video selection survives reload, stays scoped to the display and tolerates blocked storage", () => {
    const storage = new Map();
    globalThis.localStorage = {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
    };
    saveSelection("kitchen", { id: "abcdefghijk", title: "Jazz" });
    assert.deepEqual(readSelection("kitchen"), {
        id: "abcdefghijk",
        title: "Jazz",
    });
    assert.equal(readSelection("bar"), null);
    storage.set("kitchen", "{broken");
    assert.equal(readSelection("kitchen"), null);
    storage.set(
        "kitchen",
        JSON.stringify({ id: "https://evil.test", title: "Bad" }),
    );
    assert.equal(readSelection("kitchen"), null);
    globalThis.localStorage = {
        getItem() {
            throw Error("blocked");
        },
        setItem() {
            throw Error("blocked");
        },
    };
    assert.equal(readSelection("kitchen"), null);
    assert.doesNotThrow(() =>
        saveSelection("kitchen", { id: "abcdefghijk", title: "Jazz" }),
    );
    delete globalThis.localStorage;
});

test("YouTube playback failures explain unavailable and non-embeddable videos", () => {
    assert.match(playerError(150), /eingebettet/);
    assert.match(playerError(101), /eingebettet/);
    assert.match(playerError(100), /privat/);
    assert.match(playerError(153), /neu laden/);
    assert.match(playerError(5), /anderes/);
});
