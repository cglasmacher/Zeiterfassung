import React from 'react';

export default function ChecklistInput({items=[],value={},setValue}){
 const answers=value?.payload?.checklist||[];
 const answerFor=id=>answers.find(x=>String(x.id)===String(id));
 const choose=(item,ok)=>{const next=answers.filter(x=>String(x.id)!==String(item.id));next.push({id:String(item.id),ok});setValue('payload',{...(value.payload||{}),checklist:next})};
 if(!items.length)return <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Für diese Checkliste sind noch keine Prüfpunkte hinterlegt.</div>;
 return <div className="space-y-3"><div><p className="text-sm font-semibold">Prüfpunkte</p><p className="mt-1 text-xs text-neutral-500">Jeden Pflichtpunkt mit i.O. oder nicht i.O. bewerten.</p></div>{items.map((item,index)=>{const answer=answerFor(item.id);return <div key={item.id} className={`rounded-xl border p-3 ${answer?.ok===false?'border-red-200 bg-red-50':'bg-neutral-50'}`}><div className="mb-2 flex items-start justify-between gap-2"><span className="text-sm font-medium">{index+1}. {item.label}</span>{item.required!==false&&<span className="rounded-full bg-neutral-200 px-2 py-0.5 text-[11px] font-medium text-neutral-700">Pflicht</span>}</div><div className="grid grid-cols-2 gap-2"><button type="button" onClick={()=>choose(item,true)} className={`min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold ${answer?.ok===true?'border-emerald-500 bg-emerald-50 text-emerald-800':'bg-white text-neutral-700'}`}>✓ i.O.</button><button type="button" onClick={()=>choose(item,false)} className={`min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold ${answer?.ok===false?'border-red-500 bg-red-100 text-red-800':'bg-white text-neutral-700'}`}>✕ Nicht i.O.</button></div></div>})}</div>;
}

export function checklistState(items=[],value={}){
 const answers=value?.payload?.checklist||[];
 const answered=id=>answers.find(x=>String(x.id)===String(id));
 const ready=items.length>0&&items.filter(x=>x.required!==false).every(x=>typeof answered(x.id)?.ok==='boolean');
 const deviates=answers.some(x=>x.ok===false);
 return {ready,deviates};
}
