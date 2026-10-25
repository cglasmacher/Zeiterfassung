import { activeStatuses, clock, localParts } from './reservationUi.js';
import { extents } from './floorPlanGeometry.js';

export const planColors = {
    free: { fill:'#e5efdc', stroke:'#7c9b60', text:'#405b2e' },
    unconfirmed: { fill:'#fff3cf', stroke:'#c29a2e', text:'#785b0b' },
    confirmed: { fill:'#e0edfa', stroke:'#5d8ebe', text:'#27557f' },
    arrived: { fill:'#ffead1', stroke:'#cc8b43', text:'#82521c' },
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

// Keep readable time labels horizontal and away from rotated tables and other labels.
export function planTimeLayout(room, bookings, date) {
    const obstacles=room.tables.map(t=>{const e=extents(t);return {x:t.x-e.x,y:t.y-e.y,width:e.x*2,height:e.y*2};});
    const labels=[];
    const intersects=(a,b)=>a.x<b.x+b.width+6&&a.x+a.width+6>b.x&&a.y<b.y+b.height+6&&a.y+a.height+6>b.y;
    for(const table of [...room.tables].sort((a,b)=>a.y-b.y||a.x-b.x||a.id.localeCompare(b.id))) {
        const reservations=reservationsForTable(table,bookings,date);
        if(!reservations.length)continue;
        const e=extents(table),width=116,height=reservations.length*22+8;
        const candidates=[
            {x:table.x+e.x+10,y:table.y-height/2},
            {x:table.x-e.x-width-10,y:table.y-height/2},
            {x:table.x-width/2,y:table.y-e.y-height-10},
            {x:table.x-width/2,y:table.y+e.y+10},
        ].map(p=>({...p,width,height}));
        let rect=candidates.find(r=>r.x>=8&&r.y>=8&&r.x+width<=room.width-8&&r.y+height<=room.height-8&&!obstacles.some(o=>intersects(r,o))&&!labels.some(o=>intersects(r,o)));
        if(!rect) {
            rect={x:room.width+20,y:12,width,height};
            while(labels.some(o=>intersects(rect,o))) rect.y=Math.max(...labels.filter(o=>intersects(rect,o)).map(o=>o.y+o.height+10));
        }
        labels.push({...rect,table,reservations});
    }
    const width=Math.max(room.width,...labels.map(l=>l.x+l.width+12));
    const height=Math.max(room.height,...labels.map(l=>l.y+l.height+12));
    return {labels,width,height};
}
