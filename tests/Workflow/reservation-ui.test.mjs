import { test } from 'node:test';
import assert from 'node:assert/strict';
import { localParts, shiftDay, durationFor, defaultForm, tableState, sameTables, tableLabel, reservationStatuses, statusLabels, editableStatuses } from '../../resources/js/Pages/Reservations/reservationUi.js';

test('Berlin date and time are independent of device timezone including DST',()=>{
    assert.deepEqual(localParts('2026-10-25T23:30:00Z'),{date:'2026-10-26',time:'00:30'});
    assert.deepEqual(localParts('2026-07-01T12:00:00Z'),{date:'2026-07-01',time:'14:00'});
    assert.equal(shiftDay('2026-10-25',1),'2026-10-26');
});
test('group duration chooses highest applicable threshold and edited bookings keep their actual duration',()=>{
    const config={default_duration:90,durations:[{from:6,minutes:150},{from:2,minutes:105},{from:4,minutes:135}]};
    assert.deepEqual([1,2,4,6].map(n=>durationFor(n,config)),[90,105,135,150]);
    const form=defaultForm('reservation','2026-10-26',config,{kind:'reservation',starts_at:'2026-10-26T11:00:00Z',ends_at:'2026-10-26T12:00:00Z',tables:[]});
    assert.equal(form.time,'12:00');assert.equal(form.duration_minutes,60);
});
test('occupancy observes exact intervals even for seated guests',()=>{
    const table={id:'a',room_id:'r'},booking={id:'b',status:'confirmed',starts_at:'2026-10-26T11:00:00Z',ends_at:'2026-10-26T12:00:00Z',tables:[table]};
    const now=new Date('2026-10-26T11:00:00Z');
    assert.equal(tableState(table,[booking],[],'2026-10-26','12:30',now).booking.id,'b');
    assert.equal(tableState(table,[booking],[],'2026-10-26','13:00',now).booking,undefined);
    booking.status='seated';
    assert.equal(tableState(table,[booking],[],'2026-10-27','18:00',new Date('2026-10-26T14:00:00Z')).booking,undefined);
    assert.equal(tableState(table,[booking],[],'2026-10-26','12:30',new Date('2026-10-26T14:00:00Z')).booking.id,'b');
    assert.equal(tableState(table,[booking],[],'2026-10-26','13:00',new Date('2026-10-26T14:00:00Z')).booking,undefined);
    booking.status='finished';
    assert.equal(tableState(table,[booking],[],'2026-10-27','18:00',new Date('2026-10-26T14:00:00Z')).booking,undefined);
});
test('table sets ignore order and a room block does not affect another room',()=>{
    assert.ok(sameTables(['b','a'],['a','b']));assert.ok(!sameTables(['a'],['a','b']));
    const block={room_id:'x',table_ids:null,starts_at:'2026-10-26T10:00:00Z',ends_at:'2026-10-26T20:00:00Z'};
    assert.equal(tableState({id:'a',room_id:'r'},[],[block],'2026-10-26','12:00').block,undefined);
});

test('table labels list single and combined tables without rooms or source IDs',()=>{
    assert.equal(tableLabel([{name:'2',room_id:'restaurant'}]),'Tisch 2');
    assert.equal(tableLabel([{name:'12'},{name:'5'},{name:'3'},{name:'2'}]),'Tisch 2, 3, 5, 12');
    assert.equal(tableLabel([]),'Kein Tisch');
});

test('reservation menus expose six statuses and a single arrival state',()=>{
    assert.deepEqual(reservationStatuses.map(s=>statusLabels[s]),['Angefragt','Bestätigt','Eingetroffen','Fertig','Storno','No-Show']);
    assert.equal(statusLabels.arrived,'Eingetroffen');
    assert.deepEqual(editableStatuses({status:'confirmed',kind:'reservation',source:'resmio',resmio_status:'confirmed'}),reservationStatuses);
    assert.deepEqual(editableStatuses({status:'waiting',kind:'waitlist'}),[]);
});
