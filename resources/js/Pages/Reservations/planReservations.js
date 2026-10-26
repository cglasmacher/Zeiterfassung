import { activeStatuses, clock, localParts } from './reservationUi.js';
import { extents } from './floorPlanGeometry.js';

export const planColors = {
    free: { fill:'#e5efdc', stroke:'#7c9b60', text:'#405b2e' },
    unconfirmed: { fill:'#fff3cf', stroke:'#c29a2e', text:'#785b0b' },
    confirmed: { fill:'#e0edfa', stroke:'#5d8ebe', text:'#27557f' },
    arrived: { fill:'#d83838', stroke:'#a91e1e', text:'#ffffff' },
    seated: { fill:'#d83838', stroke:'#a91e1e', text:'#ffffff' },
    finished: { fill:'#f0f1ed', stroke:'#b3b8ac', text:'#6b7462' },
    blocked: { fill:'#dcdfd8', stroke:'#949c89', text:'#535e48' },
};

export function reservationsForTable(table, bookings, date) {
    return bookings.filter(b => (activeStatuses.includes(b.status) || b.status==='finished')
        && b.tables.some(t=>t.id===table.id)
        && localParts(b.starts_at).date<=date
        && (localParts(b.ends_at).date>date || (localParts(b.ends_at).date===date && clock(b.ends_at)!=='00:00')))
        .sort((a,b)=>new Date(a.starts_at)-new Date(b.starts_at)||a.id.localeCompare(b.id));
}

// Small start-time chips stay attached to the left/right edge of their own table.
// Never move a table's times to a distant shared gutter.
export function planTimeLayout(room, bookings, date) {
    const boxes=room.tables.map(t=>{const e=extents(t);return {id:t.id,x:t.x-e.x,y:t.y-e.y,width:e.x*2,height:e.y*2};});
    const labels=[];
    const overlap=(a,b)=>Math.max(0,Math.min(a.x+a.width,b.x+b.width)-Math.max(a.x,b.x))*Math.max(0,Math.min(a.y+a.height,b.y+b.height)-Math.max(a.y,b.y));
    for(const table of [...room.tables].sort((a,b)=>a.y-b.y||a.x-b.x||a.id.localeCompare(b.id))) {
        const reservations=reservationsForTable(table,bookings,date);
        if(!reservations.length)continue;
        const e=extents(table),width=40,height=reservations.length*16-2,candidates=[];
        const offsets=[0,...Array.from({length:Math.floor(e.y/8)},(_,i)=>(i+1)*8).flatMap(n=>[-n,n])];
        for(const dy of offsets)for(const side of ['right','left']) {
            const rect={x:side==='right'?table.x+e.x+3:table.x-e.x-width-3,y:table.y-height/2+dy,width,height,side};
            const collisions=boxes.filter(b=>b.id!==table.id).reduce((n,b)=>n+overlap(rect,b),0)+labels.reduce((n,l)=>n+overlap(rect,l),0);
            // Prefer a clear adjacent side; keep vertical shifts small and avoid walls where possible.
            const decorations=(room.elements||[]).filter(o=>o.type!=='text').reduce((n,o)=>{const d=extents(o);return n+overlap(rect,{x:o.x-d.x,y:o.y-d.y,width:d.x*2,height:d.y*2});},0);
            candidates.push({...rect,score:collisions*100+decorations*5+Math.abs(dy)+(side==='left'?0.1:0)});
        }
        candidates.sort((a,b)=>a.score-b.score);
        labels.push({...candidates[0],table,reservations});
    }
    const minX=Math.min(0,...labels.map(l=>l.x-4)),minY=Math.min(0,...labels.map(l=>l.y-4));
    const width=Math.max(room.width,...labels.map(l=>l.x+l.width+4))-minX;
    const height=Math.max(room.height,...labels.map(l=>l.y+l.height+4))-minY;
    return {labels,minX,minY,width,height};
}
