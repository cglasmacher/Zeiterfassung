import React from 'react';
export default function TagPicker({tags=[],selected=[],onChange}) {
    return <fieldset className="rb-tag-picker"><legend>Reservierungs-Tags</legend><div className="rb-checks">{tags.filter(t=>t.active||selected.includes(t.id)).map(t=><label key={t.id}><input type="checkbox" checked={selected.includes(t.id)} onChange={e=>onChange(e.target.checked?[...selected,t.id]:selected.filter(id=>id!==t.id))}/>{t.name}{!t.active?' (archiviert)':''}</label>)}</div></fieldset>;
}
