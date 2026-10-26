import React,{useEffect,useState} from 'react';
import axios from 'axios';
import {api,errorText} from '@/Pages/Reservations/reservationUi';
const labels={pending:'Vorgemerkt',processing:'Wird gesendet',sent:'An Mailserver übergeben',failed:'Fehlgeschlagen',skipped:'Durch neueren Stand ersetzt'};
export default function MailOutbox(){
    const [data,setData]=useState(null),[error,setError]=useState(''),[notice,setNotice]=useState(''),[busy,setBusy]=useState(false);
    const load=async()=>{setError('');try{setData((await axios.get(`${api}/mails`)).data);}catch(e){setError(errorText(e));}};
    useEffect(()=>{load();},[]);
    return <section className="rb-card"><h2>E-Mails an Reservierungsgäste</h2><p className="rb-hint">Website-Buchungen erhalten Eingangs-, Bestätigungs-, Änderungs- und Stornomails. Warteliste und manuelle Anfrage werden ausdrücklich als unbestätigt bezeichnet. Resmio-Importe lösen hier keine zusätzlichen Mails aus.</p>
        {error&&<div className="rb-alert rb-error" role="alert">{error}</div>}{notice&&<p role="status">{notice}</p>}
        {data&&<><div className={`rb-alert ${!data.enabled||!data.configured?'rb-warning':''}`}>{!data.enabled?'Mailversand ist serverseitig ausgeschaltet (RESERVATION_MAIL_ENABLED).':!data.configured?'Mailversand ist eingeschaltet, aber ein echter Mailtransport und gültiger Absender fehlen. Vorgemerkte Mails warten.':'Mailversand ist eingeschaltet. Der Scheduler verarbeitet den Mailausgang jede Minute.'}</div><p className="rb-hint">{data.counts.pending||0} vorgemerkt · {data.counts.processing||0} im Versand · {data.counts.sent||0} übergeben · {data.counts.failed||0} fehlgeschlagen</p><p className="rb-hint">„An Mailserver übergeben“ bestätigt die Annahme durch den Mailserver. Die Zustellung ins Postfach wird damit nicht geprüft. Erinnerungen und automatische No-Show-Mails sind noch nicht aktiv.</p></>}
        <button type="button" className="rb-btn" disabled={busy} onClick={load}>Mailausgang aktualisieren</button>
        {data?.recent.map(m=><div className="rb-block" key={m.id}><div><strong>{m.subject}</strong><p>{m.reference} · {m.recipient}</p><p className="rb-hint">{labels[m.status]||m.status} · {m.attempts} Versandversuche{m.sent_at?` · ${new Intl.DateTimeFormat('de-DE',{dateStyle:'short',timeStyle:'short',timeZone:'Europe/Berlin'}).format(new Date(m.sent_at.replace(' ','T')+'Z'))}`:''}</p>{m.last_error&&<p className="rb-hint">{m.last_error}</p>}</div>{m.status==='failed'&&<button type="button" className="rb-btn" disabled={busy} onClick={async()=>{setBusy(true);setError('');try{const r=await axios.post(`${api}/mails/${m.id}/retry`);setNotice(r.data.message);await load();}catch(e){setError(errorText(e));}finally{setBusy(false);}}}>Erneut vormerken</button>}</div>)}
    </section>;
}
