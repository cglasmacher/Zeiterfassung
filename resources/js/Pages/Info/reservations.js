const clock = new Intl.DateTimeFormat("en-GB", {
    timeZone: "Europe/Berlin",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
});

// Count guests by reservation start, with inclusive starts and exclusive ends.
export function reservationSummary(reservations = []) {
    const blocks = [
        { label: "10–12 Uhr", from: 600, until: 720, guests: 0 },
        { label: "12–17 Uhr", from: 720, until: 1020, guests: 0 },
        { label: "17–21 Uhr", from: 1020, until: 1260, guests: 0 },
    ];
    let total = 0,
        outside = 0;
    for (const reservation of reservations) {
        const date = new Date(reservation.starts_at);
        const guests = Number(reservation.people);
        if (
            !Number.isFinite(date.getTime()) ||
            !Number.isInteger(guests) ||
            guests < 1
        )
            continue;
        const parts = clock.formatToParts(date);
        const minutes =
            Number(parts.find((p) => p.type === "hour").value) * 60 +
            Number(parts.find((p) => p.type === "minute").value);
        const block = blocks.find(
            (b) => minutes >= b.from && minutes < b.until,
        );
        total += guests;
        if (block) block.guests += guests;
        else outside += guests;
    }
    return { blocks, total, outside };
}
