import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { extents, fitObject, fitsRoom, validateRoom, roomPayload, nextTableName } from '../../resources/js/Pages/Reservations/floorPlanGeometry.js';

const room = () => ({ name: 'Restaurant', width: 1200, height: 800, revision: 1, tables: [1,2,3].map((name,i) => ({id:randomUUID(),name:String(name),capacity:2,shape:'rectangle',x:140+i*220,y:140,width:80,height:70,rotation:0})), elements: [] });
test('new table names skip existing names without changing table ids', () => {
    const tables=room().tables;const ids=tables.map(t=>t.id);
    assert.equal(nextTableName(tables),'4');
    assert.deepEqual(tables.map(t=>t.id),ids);
    assert.equal(validateRoom(room()),null);
});
test('rotated tables stay inside the room and snap correctly at scaled pointer coordinates', () => {
    const r = room(), o = {...r.tables[0],rotation:45,x:-100,y:9999};
    const fitted = fitObject(o,r,true), e = extents(o);
    assert.ok(fitted.x >= e.x && fitted.y+e.y <= r.height);
    assert.equal(fitted.id,o.id);
    assert.equal(fitObject({...o,x:223,y:332},r,true).x,220);
    assert.equal(fitsRoom({...o,width:2000},r),false);
});
test('save validation rejects duplicate names, invalid capacity and room shrink with objects outside', () => {
    let r = room(); r.tables[1].name = ' 1 '; assert.match(validateRoom(r),/eindeutige/);
    r = room(); r.tables[0].capacity = 0; assert.match(validateRoom(r),/Plätze/);
    r = room(); r.width = 400; assert.match(validateRoom(r),/außerhalb/);
});
test('request payload strips persistence metadata and keeps object ids', () => {
    const r = room(); r.tables[0].room_id='unexpected'; r.tables[0].created_at='yesterday';
    const p = roomPayload(r);
    assert.equal(p.tables[0].room_id,undefined);
    assert.equal(p.tables[0].created_at,undefined);
    assert.equal(p.tables[0].id,r.tables[0].id);
});
