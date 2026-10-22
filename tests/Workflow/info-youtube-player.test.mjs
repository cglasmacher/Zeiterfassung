import test from "node:test";
import assert from "node:assert/strict";
import {
    readSelection,
    saveSelection,
    playerError,
} from "../../resources/js/Pages/Info/youtube-player.js";

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
