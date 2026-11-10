import {useEffect,useState} from 'react';
import axios from 'axios';

export default function PhotoEvidenceInput({file,onChange,label='Foto-Nachweis',required=false,compact=false}){
 const[preview,setPreview]=useState('');
 useEffect(()=>{if(!file){setPreview('');return}const url=URL.createObjectURL(file);setPreview(url);return()=>URL.revokeObjectURL(url)},[file]);
 return <div className={`rounded-xl border border-dashed border-neutral-300 bg-neutral-50 ${compact?'p-2':'p-3'}`}>
  <div className="flex items-center gap-3">
   {preview&&<img src={preview} alt="Foto-Vorschau" className="h-16 w-16 rounded-lg object-cover"/>}
   <label className="flex-1 cursor-pointer"><span className="block text-sm font-medium">📷 {label}{required&&<span className="text-red-600"> *</span>}</span><span className="mt-1 block text-xs text-neutral-500">Auf dem iPhone direkt Kamera öffnen oder vorhandenes Bild auswählen.</span><input className="sr-only" type="file" accept="image/*" capture="environment" onChange={e=>onChange(e.target.files?.[0]||null)}/><span className="mt-2 inline-block rounded-lg border bg-white px-3 py-2 text-sm font-medium">{file?'Foto ändern':'Foto aufnehmen'}</span></label>
   {file&&<button type="button" className="text-xs text-neutral-500 underline" onClick={()=>onChange(null)}>Entfernen</button>}
  </div>
  {file&&<div className="mt-2 truncate text-xs text-neutral-500">{file.name} · {Math.max(1,Math.round(file.size/1024))} KB</div>}
 </div>
}

export async function uploadHaccpPhoto(type,id,file){
 if(!file||!id)return null;
 const form=new FormData();form.append('photo',file);form.append('captured_at',new Date().toISOString());
 return (await axios.post(`/api/haccp/evidence/${type}/${id}`,form,{headers:{'Content-Type':'multipart/form-data'}})).data;
}
