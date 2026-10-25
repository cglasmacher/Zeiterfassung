export const TABLE_FIELDS = ['id', 'name', 'capacity', 'shape', 'x', 'y', 'width', 'height', 'rotation'];
export const ELEMENT_FIELDS = ['id', 'type', 'label', 'x', 'y', 'width', 'height', 'rotation'];
const pick = (object, fields) => Object.fromEntries(fields.map(key => [key, object[key]]));
export const roomPayload = room => ({ name: room.name, width: room.width, height: room.height, revision: room.revision,
    tables: room.tables.map(t => pick(t, TABLE_FIELDS)), elements: (room.elements || []).map(e => pick(e, ELEMENT_FIELDS)) });
export const fingerprint = room => JSON.stringify(roomPayload(room));
export function extents(object) {
    const a = object.rotation * Math.PI / 180;
    return { x: Math.abs(Math.cos(a)) * object.width / 2 + Math.abs(Math.sin(a)) * object.height / 2,
        y: Math.abs(Math.sin(a)) * object.width / 2 + Math.abs(Math.cos(a)) * object.height / 2 };
}
export function fitObject(object, room, snap = false) {
    const extent = extents(object);
    const round = v => snap ? Math.round(v / 10) * 10 : Math.round(v);
    return { ...object, x: Math.max(Math.ceil(extent.x), Math.min(Math.floor(room.width - extent.x), round(object.x))),
        y: Math.max(Math.ceil(extent.y), Math.min(Math.floor(room.height - extent.y), round(object.y))) };
}
export const fitsRoom = (object, room) => { const e = extents(object); return e.x * 2 <= room.width && e.y * 2 <= room.height; };
export function validateRoom(room) {
    if (!room.name.trim()) return 'Bitte einen Raumnamen eingeben.';
    if (!Number.isInteger(room.width) || room.width < 400 || room.width > 4000 || !Number.isInteger(room.height) || room.height < 300 || room.height > 3000) return 'Raumgröße: Breite 400–4000, Höhe 300–3000.';
    const names = new Set();
    for (const table of room.tables) {
        const name = table.name.trim().toLocaleLowerCase('de');
        if (!name || names.has(name)) return 'Bitte eindeutige, ausgefüllte Tischnamen verwenden.';
        names.add(name);
        if (!Number.isInteger(table.capacity) || table.capacity < 1 || table.capacity > 100) return 'Ein Tisch benötigt 1–100 Plätze.';
    }
    for (const object of [...room.tables, ...(room.elements || [])]) {
        const isTable = 'capacity' in object;
        const low = isTable ? 30 : 10, high = isTable ? 600 : 2000;
        if (!Number.isInteger(object.width) || !Number.isInteger(object.height) || object.width < low || object.height < low || object.width > high || object.height > high) return `Objektgröße: ${low}–${high}.`;
        if (!Number.isInteger(object.rotation) || object.rotation < 0 || object.rotation > 359) return 'Drehung: 0–359 Grad.';
        const e = extents(object);
        if (!fitsRoom(object, room) || object.x - e.x < -0.01 || object.y - e.y < -0.01 || object.x + e.x > room.width + 0.01 || object.y + e.y > room.height + 0.01) return 'Ein Objekt liegt außerhalb des Raums. Position oder Raumgröße anpassen.';
    }
    return null;
}
export function nextTableName(tables) { let n = 1; while (tables.some(t => t.name === String(n))) n++; return String(n); }
