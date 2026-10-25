export const statusLabels = { waiting:'Wartet', unconfirmed:'Unbestätigt', confirmed:'Bestätigt', arrived:'Angekommen', seated:'Platziert', finished:'Abgeschlossen', cancelled:'Storniert', noshow:'No-Show' };
export const kindLabels = { reservation:'Reservierung', walkin:'Walk-in', waitlist:'Warteliste' };
export const activeStatuses = ['unconfirmed','confirmed','arrived','seated'];
export const api = '/api/reservations';
export const errorText = e => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Die Verbindung ist fehlgeschlagen. Bitte erneut versuchen.';
export function localParts(value = new Date()) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date(value)).map(p => [p.type,p.value]));
    return {date:`${parts.year}-${parts.month}-${parts.day}`,time:`${parts.hour}:${parts.minute}`};
}
export const clock = value => localParts(value).time;
export function shiftDay(date, n) { const d = new Date(`${date}T12:00:00Z`); d.setUTCDate(d.getUTCDate()+n); return d.toISOString().slice(0,10); }
export function durationFor(size, config) { let result=config?.default_duration || 90; for (const rule of [...(config?.durations || [])].sort((a,b)=>a.from-b.from)) if (size>=rule.from) result=rule.minutes; return result; }
export function defaultForm(kind, date, config, booking = null) {
    const now=localParts(), start=booking ? localParts(booking.starts_at) : {date:kind==='walkin'?now.date:date,time:now.time};
    return {kind:booking?.kind || kind,date:start.date,time:start.time,party_size:booking?.party_size || 2,duration_minutes:booking ? Math.round((new Date(booking.ends_at)-new Date(booking.starts_at))/60000) : durationFor(2,config),guest_name:booking?.guest_name || '',phone:booking?.phone || '',email:booking?.email || '',notes:booking?.notes || '',source:booking?.source || (kind==='walkin'?'walkin':'phone'),room_id:booking?.tables?.[0]?.room_id || '',table_ids:booking?.tables?.map(t=>t.id) || [],version:booking?.version,status:booking?.status};
}
export const sameTables = (a,b) => [...a].sort().join(',') === [...b].sort().join(',');
export function tableState(table, bookings, blocks, date, time, now = new Date()) {
    const matches = value => {const p=localParts(value);return `${p.date} ${p.time}`;};
    const at=`${date} ${time}`, nowParts=localParts(now), viewingAtOrAfterNow=at>=`${nowParts.date} ${nowParts.time}`;
    const booking = bookings.find(b=>activeStatuses.includes(b.status) && b.tables.some(t=>t.id===table.id) && matches(b.starts_at)<=at && (matches(b.ends_at)>at || (viewingAtOrAfterNow && b.status==='seated' && new Date(b.ends_at)<=now)));
    const block = blocks.find(b=>matches(b.starts_at)<=at && matches(b.ends_at)>at && (b.table_ids?.length ? b.table_ids.includes(table.id) : !b.room_id || b.room_id===table.room_id));
    return {booking,block};
}
