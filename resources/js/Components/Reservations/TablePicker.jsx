import React, { useState } from 'react';
import { Armchair, Check, ArrowDownUp } from 'lucide-react';
import Field from './Field';
import { selectionSummary } from './tableSelection';

export default function TablePicker({rooms,tables,combinations=[],selected,onChange,size,busy=false,error='',roomId:controlledRoom,onRoomChange,automatic=false,onAutomatic}) {
    const [view,setView]=useState('available'),[ownRoom,setOwnRoom]=useState(''),[sort,setSort]=useState('name');
    const roomId=controlledRoom??ownRoom;
    const summary=selectionSummary(selected,tables,combinations,size);
    const selectedRoom=tables.find(t=>selected.includes(t.id))?.room_id;
    const visible=tables.filter(t=>(!roomId||t.room_id===roomId)&&(view==='all'||t.available||selected.includes(t.id)))
        .sort((a,b)=>Number(selected.includes(b.id))-Number(selected.includes(a.id))||(sort==='capacity'?a.capacity-b.capacity:0)||a.name.localeCompare(b.name,'de',{numeric:true}));
    const choose=id=>onChange(selected.includes(id)?selected.filter(v=>v!==id):[...selected,id]);
    const bulk=()=>{const room=selectedRoom||visible.find(t=>t.available)?.room_id;onChange([...new Set([...selected,...visible.filter(t=>t.available&&t.room_id===room).map(t=>t.id)])].slice(0,20));};
    return <section className="rb-picker" aria-label="Tischzuordnung">
        <div className="rb-picker-toolbar"><Field label="Räume filtern"><select value={roomId} disabled={busy} onChange={e=>onRoomChange?onRoomChange(e.target.value):setOwnRoom(e.target.value)}><option value="">Alle Räume</option>{rooms.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select></Field>
            <div className="rb-picker-tabs" role="group" aria-label="Tischanzeige"><button type="button" aria-pressed={view==='available'} onClick={()=>setView('available')}>Verfügbar</button><button type="button" aria-pressed={view==='all'} onClick={()=>setView('all')}>Alle</button></div>
        </div>
        <div className="rb-picker-heading"><strong>Tische auswählen</strong><div className="rb-actions"><button type="button" className="rb-btn" disabled={busy||!!error||!visible.some(t=>t.available)} onClick={bulk}>Alle verfügbaren auswählen</button><button type="button" className="rb-icon" aria-label={sort==='name'?'Nach Plätzen sortieren':'Nach Tischnummer sortieren'} onClick={()=>setSort(sort==='name'?'capacity':'name')}><ArrowDownUp size={16}/></button></div></div>
        {busy&&<p className="rb-hint" role="status">Verfügbarkeit wird geprüft …</p>}{error&&<p className="rb-overdue" role="alert">{error}</p>}
        <div className="rb-table-tiles" aria-busy={busy}>{visible.map(t=>{const chosen=selected.includes(t.id),otherRoom=selectedRoom&&t.room_id!==selectedRoom;
            return <button type="button" key={t.id} aria-pressed={chosen} aria-label={`Tisch ${t.name}, ${t.capacity} Plätze${!t.available?', '+t.reason:''}`} disabled={busy||!!error||(!chosen&&(!t.available||otherRoom||selected.length>=20))} onClick={()=>choose(t.id)} className={`rb-table-tile ${chosen?'is-selected':''} ${!t.available?'is-unavailable':''}`} title={otherRoom?'Bitte zunächst die Auswahl im anderen Raum leeren.':t.reason||'Für den gesamten Zeitraum frei'}>
                <span className="rb-tile-top"><strong>{t.name}</strong>{chosen&&<Check size={16}/>}</span><span className="rb-tile-seats"><Armchair size={17}/>{t.capacity} Plätze</span><small>{!t.available?t.reason:rooms.find(r=>r.id===t.room_id)?.name}</small>
            </button>;
        })}</div>
        {!busy&&!error&&!visible.length&&<p className="rb-hint">Keine verfügbaren Tische in dieser Ansicht. Unter „Alle“ sind belegte und gesperrte Tische sichtbar.</p>}
        <div className={`rb-picker-summary ${!automatic&&!summary.valid?'needs-seats':''}`} role="status" aria-live="polite">
            {automatic?'Automatische Tischzuordnung aktiv.':<><strong>{selected.length} {selected.length===1?'Tisch':'Tische'} · {summary.capacity} Plätze für {size} Gäste.</strong> {summary.mixedRooms?'Bitte Tische innerhalb desselben Raumes auswählen.':summary.unavailable?'Mindestens ein ausgewählter Tisch ist nicht verfügbar.':summary.missing?`${summary.missing} ${summary.missing===1?'weiterer Platz benötigt':'weitere Plätze benötigt'}. Wähle weitere Tische aus.`:'Die Gruppe hat ausreichend Platz.'}</>}
        </div>
        <div className="rb-actions">{selected.length>0&&<button type="button" className="rb-btn" disabled={busy} onClick={()=>onChange([])}>Auswahl leeren</button>}{onAutomatic&&<button type="button" className="rb-btn" disabled={busy||!!error} aria-pressed={automatic} onClick={onAutomatic}>Automatisch zuordnen</button>}</div>
        <p className="rb-hint">Mehrere Tische können innerhalb eines Raumes ausgewählt werden. Hinterlegte Kombinationen verwenden ihre gemeinsame Kapazität. Beim Speichern wird die gesamte Aufenthaltsdauer erneut geprüft.</p>
    </section>;
}
