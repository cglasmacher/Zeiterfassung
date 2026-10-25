import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Plus, Save, Undo2, Redo2, Trash2, Copy, RotateCw, Armchair, Grid2X2, ZoomIn, ZoomOut } from 'lucide-react';
import TimeTrackingLayout from '@/Layouts/TimeTrackingLayout';
import { fingerprint, roomPayload, fitObject, fitsRoom, validateRoom, nextTableName } from './floorPlanGeometry.js';
import './floorPlan.css';

const endpoint = '/api/reservations/floor-plan';
const uuid = () => crypto.randomUUID();
const tools = [['table','Tisch'],['wall','Wand'],['door','Tür'],['bar','Theke'],['text','Text'],['plant','Pflanze']];
const names = Object.fromEntries(tools);
const message = error => Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'Die Verbindung ist fehlgeschlagen. Bitte erneut versuchen.';
const clone = value => structuredClone(value);

export default function FloorPlan() {
    const [rooms, setRooms] = useState([]);
    const [history, setHistory] = useState({ past: [], present: null, future: [] });
    const [saved, setSaved] = useState('');
    const [selected, setSelected] = useState(null);
    const [busy, setBusy] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [conflict, setConflict] = useState(false);
    const [snap, setSnap] = useState(true);
    const [zoom, setZoom] = useState(1);
    const [newRoom, setNewRoom] = useState(null);
    const svg = useRef(null), drag = useRef(null);
    const draft = history.present;
    const dirty = !!draft && fingerprint(draft) !== saved;
    const chosen = draft && [...draft.tables, ...(draft.elements || [])].find(o => o.id === selected);
    const isTable = chosen && 'capacity' in chosen;
    const chooseRoom = room => {
        const next = clone({ ...room, elements: room.elements || [] });
        setHistory({ past: [], present: next, future: [] }); setSaved(fingerprint(next));
        setSelected(null); setError(''); setNotice(''); setConflict(false);
    };
    const canLeave = () => !dirty || window.confirm('Ungespeicherte Änderungen verwerfen?');
    const load = async (preferred = null) => {
        setBusy(true); setError('');
        try { const { data } = await axios.get(endpoint); setRooms(data.rooms); const room = data.rooms.find(r => r.id === preferred) || data.rooms[0]; if (room) chooseRoom(room); }
        catch (e) { setError(message(e)); } finally { setBusy(false); }
    };
    useEffect(() => { load(); }, []);
    useEffect(() => {
        const unload = e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
        window.addEventListener('beforeunload', unload);
        const stop = router.on('before', e => { if (dirty && !window.confirm('Ungespeicherte Änderungen verwerfen und Seite verlassen?')) e.preventDefault(); });
        return () => { window.removeEventListener('beforeunload', unload); stop(); };
    }, [dirty]);
    const change = next => {
        if (busy) return;
        setNotice('');
        setHistory(h => {
            const updated = typeof next === 'function' ? next(clone(h.present)) : next;
            if (fingerprint(updated) === fingerprint(h.present)) return h;
            return { past: [...h.past.slice(-49), h.present], present: updated, future: [] };
        });
    };
    const undo = () => { setSelected(null); setHistory(h => h.past.length ? { past: h.past.slice(0,-1), present: h.past.at(-1), future: [h.present, ...h.future] } : h); };
    const redo = () => { setSelected(null); setHistory(h => h.future.length ? { past: [...h.past, h.present], present: h.future[0], future: h.future.slice(1) } : h); };
    const updateObject = patch => change(room => {
        const key = isTable ? 'tables' : 'elements';
        room[key] = room[key].map(o => {
            if (o.id !== selected) return o;
            const next = { ...o, ...patch };
            return fitsRoom(next, room) ? fitObject(next, room) : next;
        });
        return room;
    });
    const add = type => {
        if (!draft || busy) return;
        const id = uuid();
        change(room => {
            if (type === 'table') room.tables.push(fitObject({ id, name: nextTableName(room.tables), capacity: 2, shape: 'rectangle', x: room.width / 2, y: room.height / 2, width: 90, height: 70, rotation: 0 }, room, snap));
            else {
                const size = { wall:[200,10], door:[80,20], bar:[180,60], text:[180,40], plant:[50,50] }[type];
                room.elements.push(fitObject({ id, type, label: names[type], x: room.width / 2, y: room.height / 2, width: size[0], height: size[1], rotation: 0 }, room, snap));
            }
            return room;
        });
        setSelected(id);
    };
    const duplicate = () => {
        if (!chosen || busy) return;
        const id = uuid();
        change(room => {
            const key = isTable ? 'tables' : 'elements';
            room[key].push(fitObject({ ...chosen, id, ...(isTable ? { name: nextTableName(room.tables) } : {}), x: chosen.x + 30, y: chosen.y + 30 }, room, snap)); return room;
        }); setSelected(id);
    };
    const remove = () => {
        if (!chosen || busy || !window.confirm(`${isTable ? `Tisch ${chosen.name}` : names[chosen.type]} aus dem Plan entfernen?`)) return;
        change(room => ({ ...room, tables: room.tables.filter(o => o.id !== selected), elements: room.elements.filter(o => o.id !== selected) })); setSelected(null);
    };
    const point = e => { const matrix = svg.current?.getScreenCTM(); if (!matrix) return null; return new DOMPoint(e.clientX, e.clientY).matrixTransform(matrix.inverse()); };
    const startDrag = (e, object) => {
        e.stopPropagation(); setSelected(object.id);
        if (busy || e.button !== 0) return;
        const p = point(e); if (!p) return;
        svg.current.focus();
        e.currentTarget.setPointerCapture(e.pointerId);
        drag.current = { id: object.id, object: clone(object), start: p, original: clone(draft), pointerId: e.pointerId };
    };
    const moveDrag = e => {
        const d = drag.current; if (!d || d.pointerId !== e.pointerId) return;
        const p = point(e); if (!p) return;
        const room = clone(d.original), key = 'capacity' in d.object ? 'tables' : 'elements';
        const next = fitObject({ ...d.object, x: d.object.x + p.x - d.start.x, y: d.object.y + p.y - d.start.y }, room, snap);
        room[key] = room[key].map(o => o.id === d.id ? next : o);
        setHistory(h => ({ ...h, present: room }));
    };
    const endDrag = (e, cancelled = false) => {
        const d = drag.current; if (!d || d.pointerId !== e.pointerId) return;
        drag.current = null;
        setHistory(h => cancelled ? { ...h, present: d.original } : fingerprint(d.original) === fingerprint(h.present) ? h : { past: [...h.past.slice(-49), d.original], present: h.present, future: [] });
    };
    const canvasKeys = e => {
        if (busy) return;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); return; }
        if (e.key === 'Escape') { setSelected(null); return; }
        if (!chosen) return;
        const delta = { ArrowLeft:[-1,0], ArrowRight:[1,0], ArrowUp:[0,-1], ArrowDown:[0,1] }[e.key];
        if (delta) { e.preventDefault(); const step = e.shiftKey || snap ? 10 : 1; updateObject({ x: chosen.x + delta[0] * step, y: chosen.y + delta[1] * step }); }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(); }
    };
    const save = async () => {
        const problem = validateRoom(draft); if (problem) { setError(problem); return; }
        setBusy(true); setError(''); setNotice('');
        try {
            const { data } = await axios.put(`${endpoint}/rooms/${draft.id}`, roomPayload(draft));
            setRooms(rs => rs.map(r => r.id === data.id ? data : r)); chooseRoom(data); setNotice('Raum und Tischpositionen gespeichert.');
        } catch (e) { setError(message(e)); setConflict(e.response?.status === 409); } finally { setBusy(false); }
    };
    const createRoom = async e => {
        e.preventDefault(); if (!canLeave()) return;
        setBusy(true); setError('');
        try {
            const { data } = await axios.post(`${endpoint}/rooms`, { name: newRoom.name, template: newRoom.template });
            setRooms(rs => [...rs, data]); chooseRoom(data); setNewRoom(null); setNotice('Raum angelegt. Die Vorlagenpositionen könnt ihr frei anpassen.');
        } catch (e) { setError(message(e)); } finally { setBusy(false); }
    };
    return <TimeTrackingLayout><div className="fp-builder">
        <header className="fp-heading"><div><p className="fp-eyebrow">Reservierungen · Einrichtung</p><h1>Tischplan-Builder</h1><p>Räume gestalten, Tische anordnen und Sitzplätze festlegen.</p></div>
            <button className="fp-button fp-primary" onClick={save} disabled={busy || !draft || !dirty}><Save size={17}/>{busy ? 'Bitte warten …' : 'Änderungen speichern'}</button></header>
        {error && <div className="fp-message fp-error" role="alert">{error}{conflict && <button onClick={() => { if (canLeave()) load(draft.id); }} disabled={busy}>Gespeicherten Stand neu laden</button>}</div>}
        {notice && <div className="fp-message" role="status">{notice}</div>}
        <nav className="fp-rooms" aria-label="Räume">{rooms.map(room => <button key={room.id} disabled={busy} aria-pressed={draft?.id === room.id} onClick={() => { if (room.id !== draft?.id && canLeave()) chooseRoom(room); }}>{room.id === draft?.id ? draft.name : room.name}</button>)}
            <button disabled={busy} onClick={() => setNewRoom({ name: rooms.length ? 'Neuer Raum' : 'Restaurant', template: rooms.length ? 'empty' : 'restaurant' })}><Plus size={16}/>Raum anlegen</button></nav>
        {newRoom && <form className="fp-new-room" onSubmit={createRoom}><Field label="Raumname"><input autoFocus required maxLength={80} value={newRoom.name} onChange={e => setNewRoom({ ...newRoom, name: e.target.value })}/></Field>
            <Field label="Startvorlage"><select value={newRoom.template} onChange={e => setNewRoom({ name: e.target.value === 'restaurant' ? 'Restaurant' : e.target.value === 'bar' ? 'Theke' : newRoom.name, template: e.target.value })}><option value="empty">Leerer Raum</option><option value="restaurant">Hemingway Restaurant · 15 Zweiertische</option><option value="bar">Hemingway Theke · 4 + 8 Plätze</option></select></Field><button className="fp-button fp-primary" disabled={busy}>Anlegen</button><button type="button" className="fp-button" disabled={busy} onClick={() => setNewRoom(null)}>Abbrechen</button></form>}
        {!draft ? <div className="fp-empty"><Armchair size={38}/><h2>{busy ? 'Tischpläne werden geladen …' : 'Euer Restaurant beginnt mit einem Raum'}</h2><p>Lege „Restaurant“ oder „Theke“ mit euren vorhandenen Tischen an. Die Startpositionen sind eine Vorlage und bilden noch keinen echten Grundriss ab.</p>{!busy && error && <button className="fp-button" onClick={() => load()}>Erneut laden</button>}</div> : <>
        <div className="fp-status"><span>{draft.tables.length} Tische · {draft.tables.reduce((n,t) => n + Number(t.capacity || 0),0)} Plätze</span><span>{dirty ? '● Ungespeicherte Änderungen' : 'Gespeichert'} · Stand {draft.revision}</span></div>
        <div className="fp-workspace"><aside className="fp-panel"><h2>Elemente hinzufügen</h2><div className="fp-tool-list">{tools.map(([type,label]) => <button key={type} className="fp-button" disabled={busy} onClick={() => add(type)}><Plus size={15}/>{label}</button>)}</div>
            <h2>Raum</h2><Field label="Name"><input disabled={busy} maxLength={80} value={draft.name} onChange={e => change(r => ({ ...r, name: e.target.value }))}/></Field>
            <div className="fp-pair"><NumberField label="Breite" value={draft.width} min={400} max={4000} disabled={busy} set={width => change(r => ({ ...r, width }))}/><NumberField label="Höhe" value={draft.height} min={300} max={3000} disabled={busy} set={height => change(r => ({ ...r, height }))}/></div>
            <p className="fp-hint">Maße sind Planeinheiten. Änderungen der Raumgröße verschieben vorhandene Tische nicht.</p>
            <h2>Tische auswählen</h2><div className="fp-table-list">{draft.tables.map(t => <button key={t.id} className={selected === t.id ? 'active' : ''} onClick={() => setSelected(t.id)}><span>Tisch {t.name}</span><small>{t.capacity} Plätze</small></button>)}</div>
        </aside><section className="fp-canvas-panel" aria-label="Grafischer Raumplan"><div className="fp-toolbar"><div><button className="fp-icon" title="Rückgängig" aria-label="Rückgängig" disabled={busy || !history.past.length} onClick={undo}><Undo2 size={18}/></button><button className="fp-icon" title="Wiederholen" aria-label="Wiederholen" disabled={busy || !history.future.length} onClick={redo}><Redo2 size={18}/></button></div>
            <label><input type="checkbox" checked={snap} onChange={e => setSnap(e.target.checked)}/><Grid2X2 size={15}/>Am Raster ausrichten</label><div><button className="fp-icon" aria-label="Verkleinern" onClick={() => setZoom(z => Math.max(.5,z-.25))}><ZoomOut size={18}/></button><span>{Math.round(zoom*100)} %</span><button className="fp-icon" aria-label="Vergrößern" onClick={() => setZoom(z => Math.min(2,z+.25))}><ZoomIn size={18}/></button></div></div>
            <div className="fp-canvas-scroll"><svg ref={svg} className="fp-canvas" role="group" aria-label={`Raum ${draft.name}. Objekte auswählen und mit den Pfeiltasten bewegen.`} tabIndex={0} viewBox={`0 0 ${draft.width} ${draft.height}`} style={{ width: `${zoom * 100}%`, minWidth: 480 * zoom }} onKeyDown={canvasKeys} onPointerDown={e => { if (e.target === e.currentTarget || e.target.dataset.background) setSelected(null); }}>
                <defs><pattern id="floor-plan-grid" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="1" fill="#cbd5c6"/></pattern></defs>
                <rect data-background="true" width={draft.width} height={draft.height} fill="url(#floor-plan-grid)"/>
                {[...(draft.elements || []), ...draft.tables].map(o => <PlanObject key={o.id} object={o} selected={o.id === selected} onPointerDown={e => startDrag(e,o)} onPointerMove={moveDrag} onPointerUp={endDrag} onPointerCancel={e => endDrag(e,true)} onLostPointerCapture={e => endDrag(e,true)}/>)}
            </svg></div><p className="fp-canvas-help">Ziehen zum Verschieben · Pfeiltasten für Feinkorrektur · Umschalt für 10 Schritte · Strg/⌘ Z für Rückgängig</p>
        </section><aside className="fp-panel"><h2>{chosen ? isTable ? `Tisch ${chosen.name}` : names[chosen.type] : 'Objekteinstellungen'}</h2>
            {!chosen ? <p className="fp-hint">Wähle einen Tisch oder ein Raumelement im Plan aus. Die Tischnamen bleiben beim Verschieben erhalten.</p> : <>
                <Field label={isTable ? 'Tischname / Nummer' : 'Beschriftung'}><input disabled={busy} maxLength={isTable ? 40 : 100} value={isTable ? chosen.name : chosen.label || ''} onChange={e => updateObject(isTable ? { name:e.target.value } : { label:e.target.value })}/></Field>
                {isTable && <><NumberField label="Sitzplätze" value={chosen.capacity} min={1} max={100} disabled={busy} set={capacity => updateObject({ capacity })}/><Field label="Tischform"><select disabled={busy} value={chosen.shape} onChange={e => updateObject({ shape:e.target.value })}><option value="rectangle">Rechteckig / quadratisch</option><option value="round">Rund</option><option value="oval">Oval</option></select></Field></>}
                <div className="fp-pair"><NumberField label="Breite" value={chosen.width} min={isTable ? 30 : 10} max={isTable ? 600 : 2000} disabled={busy} set={width => updateObject({ width })}/><NumberField label="Höhe" value={chosen.height} min={isTable ? 30 : 10} max={isTable ? 600 : 2000} disabled={busy} set={height => updateObject({ height })}/></div>
                <div className="fp-pair"><NumberField label="Position X" value={chosen.x} min={0} max={draft.width} disabled={busy} set={x => updateObject({ x })}/><NumberField label="Position Y" value={chosen.y} min={0} max={draft.height} disabled={busy} set={y => updateObject({ y })}/></div>
                <NumberField label="Drehung (Grad)" value={chosen.rotation} min={0} max={359} disabled={busy} set={rotation => updateObject({ rotation })}/>
                <button className="fp-button" disabled={busy} onClick={() => updateObject({ rotation:(chosen.rotation+45)%360 })}><RotateCw size={15}/>Um 45° drehen</button>
                <button className="fp-button" disabled={busy} onClick={duplicate}><Copy size={15}/>Duplizieren</button><button className="fp-button fp-delete" disabled={busy} onClick={remove}><Trash2 size={15}/>Entfernen</button>
            </>}
        </aside></div><p className="fp-hint">Dieser Builder gestaltet Räume und Tischstammdaten. Live-Belegung, Reservierungsregeln und Tischkombinationen folgen im Reservierungsmodul.</p>
        </>}
    </div></TimeTrackingLayout>;
}

function Field({ label, children }) { return <label className="fp-field"><span>{label}</span>{children}</label>; }
function NumberField({ label, value, set, min, max, disabled }) { return <Field label={label}><input type="number" step="1" min={min} max={max} disabled={disabled} value={value} onChange={e => set(Number(e.target.value))}/></Field>; }
function PlanObject({ object:o, selected, ...events }) {
    const table = 'capacity' in o, rx = -o.width/2, ry = -o.height/2;
    const fill = table ? '#e8efdf' : ({ wall:'#596154', door:'#f0e1bd', bar:'#d9c5af', text:'transparent', plant:'#b6ceb0' }[o.type]);
    const radius = table && o.shape !== 'rectangle' ? (o.shape === 'round' ? Math.min(o.width,o.height)/2 : null) : null;
    return <g {...events} transform={`translate(${o.x} ${o.y}) rotate(${o.rotation})`} className={`fp-object ${selected ? 'selected' : ''}`}>
        {table && <g fill="#fcfcf8" stroke="#9caa90" strokeWidth="2">{Array.from({length:Math.min(o.capacity,12)},(_,i) => {
            if (o.shape !== 'rectangle') { const a = i * Math.PI * 2 / Math.min(o.capacity,12); const w = radius || o.width/2, h = radius || o.height/2; return <circle key={i} cx={Math.cos(a)*(w+10)} cy={Math.sin(a)*(h+10)} r="7"/>; }
            const half = Math.ceil(Math.min(o.capacity,12)/2), row = i < half ? -1 : 1, index = i % half, total = row < 0 ? half : Math.min(o.capacity,12)-half;
            return <rect key={i} x={-o.width/2+(index+1)*o.width/(total+1)-9} y={row<0 ? ry-14 : o.height/2+3} width="18" height="11" rx="4"/>;
        })}</g>}
        {radius ? <circle r={radius} fill={fill} className="fp-object-body"/> : table && o.shape === 'oval' ? <ellipse rx={o.width/2} ry={o.height/2} fill={fill} className="fp-object-body"/> : <rect x={rx} y={ry} width={o.width} height={o.height} rx={o.type === 'plant' ? Math.min(o.width,o.height)/2 : 5} fill={fill} className="fp-object-body"/>}
        {o.type === 'text' && <rect x={rx} y={ry} width={o.width} height={o.height} fill="transparent" stroke="#aaa" strokeDasharray="5 5"/>}
        <g transform={`rotate(${-o.rotation})`} pointerEvents="none"><text textAnchor="middle" dominantBaseline="central" y={table ? -7 : 0} fontSize={table ? 18 : 14} fontWeight="600" fill="#33412c">{table ? o.name : o.label}</text>{table && <text textAnchor="middle" y="18" fontSize="11" fill="#5f6b58">{o.capacity} Plätze</text>}</g>
        {selected && <rect x={rx-6} y={ry-6} width={o.width+12} height={o.height+12} rx="6" fill="none" stroke="#51723b" strokeWidth="3" strokeDasharray="7 4" pointerEvents="none"/>}
    </g>;
}
