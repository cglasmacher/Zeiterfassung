import { test } from 'node:test';
import assert from 'node:assert/strict';
import { reservationsForTable, planTimeLayout } from '../../resources/js/Pages/Reservations/planReservations.js';
import { tableState } from '../../resources/js/Pages/Reservations/reservationUi.js';
import { extents } from '../../resources/js/Pages/Reservations/floorPlanGeometry.js';

const table={id:'a',name:'2',room_id:'r',x:100,y:100,width:80,height:70,rotation:0};
const booking=(id,start,end,status='confirmed',tables=[table])=>({id,status,starts_at:start,ends_at:end,tables});

test('daily time lists include all bookings in order and omit cancellations, no-shows and waiting guests',()=>{
    const list=[booking('later','2026-10-26T19:00:00Z','2026-10-26T20:30:00Z'),booking('early','2026-10-26T11:00:00Z','2026-10-26T12:00:00Z','finished'),
        ...['cancelled','noshow','waiting'].map(s=>booking(s,'2026-10-26T10:00:00Z','2026-10-26T11:00:00Z',s)),
        booking('tomorrow','2026-10-27T11:00:00Z','2026-10-27T12:00:00Z'),booking('other-table','2026-10-26T11:00:00Z','2026-10-26T12:00:00Z','confirmed',[{id:'b'}])];
    assert.deepEqual(reservationsForTable(table,list,'2026-10-26').map(b=>b.id),['early','later']);
});

test('cross-midnight reservations appear on affected days but an exact midnight end does not occupy the next day',()=>{
    const cross=booking('cross','2026-10-25T22:30:00Z','2026-10-26T00:00:00Z');
    const midnight=booking('midnight','2026-10-25T21:00:00Z','2026-10-25T23:00:00Z');
    assert.deepEqual(reservationsForTable(table,[cross,midnight],'2026-10-25').map(b=>b.id),['midnight','cross']);
    assert.deepEqual(reservationsForTable(table,[cross,midnight],'2026-10-26').map(b=>b.id),['cross']);
    assert.equal(reservationsForTable(table,[cross],'2026-10-27').length,0);
});

test('seated guests take precedence over an overlapping imported reservation only inside their time interval',()=>{
    const confirmed=booking('confirmed','2026-10-26T17:00:00Z','2026-10-26T21:00:00Z');
    const seated=booking('seated','2026-10-26T18:00:00Z','2026-10-26T19:00:00Z','seated');
    assert.equal(tableState(table,[confirmed,seated],[],'2026-10-26','19:30').booking.id,'seated');
    assert.equal(tableState(table,[confirmed,seated],[],'2026-10-26','20:00').booking.id,'confirmed');
});

test('combination reservations get a time list at every member table',()=>{
    const other={...table,id:'b',name:'3',x:320};
    const b=booking('combo','2026-10-26T18:00:00Z','2026-10-26T19:00:00Z','seated',[table,other]);
    const layout=planTimeLayout({width:600,height:400,tables:[table,other]},[b],'2026-10-26');
    assert.equal(layout.labels.length,2);
    assert.ok(layout.labels.every(l=>l.reservations[0].id==='combo'));
});

test('compact time labels stay immediately left or right of each table, even in a dense rotated plan',()=>{
    const tables=Array.from({length:8},(_,i)=>({...table,id:`table-${i}`,x:60+(i%4)*90,y:60+Math.floor(i/4)*100,rotation:45}));
    const bookings=tables.flatMap(t=>Array.from({length:3},(_,i)=>booking(`${t.id}-${i}`,`2026-10-26T${String(10+i*2).padStart(2,'0')}:00:00Z`,`2026-10-26T${String(11+i*2).padStart(2,'0')}:00:00Z`,'confirmed',[t])));
    const layout=planTimeLayout({width:400,height:300,tables},bookings,'2026-10-26');
    assert.equal(layout.labels.length,8);
    for(const label of layout.labels){
        const e=extents(label.table);
        assert.equal(label.width,40);
        assert.ok(Math.abs(label.x-(label.table.x+e.x+3))<0.001 || Math.abs(label.x+label.width-(label.table.x-e.x-3))<0.001);
        assert.ok(Math.abs(label.y+label.height/2-label.table.y)<=e.y);
        assert.ok(label.x>=layout.minX&&label.y>=layout.minY&&label.x+label.width<=layout.minX+layout.width&&label.y+label.height<=layout.minY+layout.height);
    }
});
