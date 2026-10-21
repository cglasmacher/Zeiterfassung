import test from "node:test";
import assert from "node:assert/strict";
import { reservationSummary } from "../../resources/js/Pages/Info/reservations.js";
const booking = (time, people) => ({ starts_at: time, people });
test("Berlin reservation blocks use exact boundaries without double counting", () => {
    const times = [
        "09:59",
        "10:00",
        "11:59",
        "12:00",
        "16:59",
        "17:00",
        "20:59",
        "21:00",
    ];
    const data = times.map((time, i) =>
        booking(`2026-10-21T${time}:00+02:00`, i + 1),
    );
    const summary = reservationSummary(data);
    assert.deepEqual(
        summary.blocks.map((b) => b.guests),
        [5, 9, 13],
    );
    assert.equal(summary.total, 36);
    assert.equal(summary.outside, 9);
    assert.equal(
        summary.blocks.reduce((n, b) => n + b.guests, 0) + summary.outside,
        summary.total,
    );
});
test("Berlin summer and winter offsets remain correct on UTC clients", () => {
    for (const [month, offset] of [
        ["07", "08"],
        ["12", "09"],
    ]) {
        const summary = reservationSummary([
            booking(`2026-${month}-15T${offset}:00:00Z`, 4),
        ]);
        assert.deepEqual(
            summary.blocks.map((b) => b.guests),
            [4, 0, 0],
        );
    }
});
test("Empty blocks are shown and invalid inputs do not corrupt guest totals", () => {
    const summary = reservationSummary([
        booking("bad-date", 3),
        booking("2026-10-21T10:30:00+02:00", -4),
        booking("2026-10-21T10:30:00+02:00", "invalid"),
    ]);
    assert.deepEqual(
        summary.blocks.map((b) => b.guests),
        [0, 0, 0],
    );
    assert.equal(summary.total, 0);
    assert.equal(summary.outside, 0);
});
