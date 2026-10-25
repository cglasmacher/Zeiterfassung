import {test} from 'node:test';
import assert from 'node:assert/strict';
import {selectionSummary} from '../../resources/js/Components/Reservations/tableSelection.js';
const tables=Array.from({length:8},(_,i)=>({id:String(i),room_id:'r',capacity:2,available:true}));
test('fifteen guests need three more seats after selecting six two-seat tables',()=>{
    const ids=tables.slice(0,6).map(t=>t.id);const s=selectionSummary(ids,tables,[],15);
    assert.equal(s.capacity,12);assert.equal(s.missing,3);assert.equal(s.valid,false);
    assert.equal(selectionSummary(tables.map(t=>t.id),tables,[],15).valid,true);
});
test('exact configured combination overrides the sum, in either selection order',()=>{
    const combos=[{table_ids:['0','1'],capacity:5}];
    assert.equal(selectionSummary(['1','0'],tables,combos,5).valid,true);
    assert.equal(selectionSummary(['0','1','2'],tables,combos,7).missing,1);
});
test('empty, missing, unavailable and cross-room selections cannot save',()=>{
    assert.equal(selectionSummary([],tables,[],2).valid,false);
    assert.equal(selectionSummary(['gone'],tables,[],2).unavailable,true);
    assert.equal(selectionSummary(['0'],[{...tables[0],available:false}],[],2).valid,false);
    assert.equal(selectionSummary(['0','1'],[tables[0],{...tables[1],room_id:'other'}],[],2).mixedRooms,true);
});
