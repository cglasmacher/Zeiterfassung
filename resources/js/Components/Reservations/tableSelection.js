export const sameIds = (a,b) => [...a].sort().join(',') === [...b].sort().join(',');
export function selectionSummary(ids, tables, combinations, size) {
    const selected=ids.map(id=>tables.find(t=>t.id===id));
    const combo=combinations.find(c=>sameIds(c.table_ids,ids));
    const capacity=combo?Number(combo.capacity):selected.reduce((n,t)=>n+Number(t?.capacity||0),0);
    const unavailable=selected.some(t=>!t?.available);
    const mixedRooms=new Set(selected.filter(Boolean).map(t=>t.room_id)).size>1;
    const missing=Math.max(0,Number(size)-capacity);
    return {capacity,missing,unavailable,mixedRooms,valid:ids.length>0&&ids.length<=20&&!unavailable&&!mixedRooms};
}
