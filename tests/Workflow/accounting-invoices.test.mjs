import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const workflow = JSON.parse(fs.readFileSync(new URL('../../workflows/n8n/Hemingway-Eingangsrechnungen.json', import.meta.url)));
const node = name => workflow.nodes.find(n => n.name === name);
const run = (name, items, extra = {}) => vm.runInNewContext(`(function () { ${node(name).parameters.jsCode}\n})()`, {
    $input: { all: () => items }, ...extra,
});
const mail = (changes = {}) => ({
    json: { from: 'Gato <rechnung@gato.example>', messageId: '<invoice-1@example.test>', ...changes },
    binary: {
        eingangsrechnung_0: { fileName: 'logo.png', mimeType: 'image/png', data: 'logo' },
        eingangsrechnung_1: { fileName: 'one.pdf', mimeType: 'application/pdf', data: 'PDF_ONE' },
        eingangsrechnung_2: { fileName: 'two.PDF', mimeType: 'application/octet-stream', data: 'PDF_TWO' },
    },
});

test('every PDF has its own reference, original bytes and source link; image is excluded', () => {
    const output = run('PDF normalisieren', [mail()]);
    assert.equal(output.length, 2);
    assert.equal(output[0].binary.eingangsrechnung_0.data, 'PDF_ONE');
    assert.equal(output[1].binary.eingangsrechnung_0.data, 'PDF_TWO');
    assert.equal(output[0].json.accounting_attachment.source_reference, '<invoice-1@example.test>');
    assert.notEqual(output[0].json.accounting_attachment.source_reference, output[1].json.accounting_attachment.source_reference);
    for (const item of output) {
        assert.equal(item.pairedItem.item, 0);
        assert.equal(Object.keys(item.binary).length, 1);
    }
    assert.equal(run('PDF normalisieren', [mail()])[1].json.accounting_attachment.source_reference, output[1].json.accounting_attachment.source_reference);
});

test('Vectron is excluded for string and parsed sender formats, unrelated addresses remain', () => {
    for (const from of ['Vectron <NOREPLY@MYVECTRON.COM>', { value: [{ address: 'noreply@myvectron.com' }] }]) {
        assert.equal(run('PDF normalisieren', [mail({ from })]).length, 0);
    }
    assert.equal(run('PDF normalisieren', [mail({ from: 'noreply@myvectron.com.evil.test' })]).length, 2);
});

test('mixed mail batch preserves original item index and ignores attachment-free mail', () => {
    const empty = mail(); empty.binary = {};
    const items = run('PDF normalisieren', [mail({ from: 'noreply@myvectron.com' }), empty, mail()]);
    assert.equal(items.length, 2);
    assert.equal(items[0].pairedItem.item, 2);
});

test('missing identity/sender fails instead of creating unstable duplicate keys', () => {
    assert.throws(() => run('PDF normalisieren', [mail({ messageId: null })]), /Stabile Message-ID/);
    assert.equal(run('PDF normalisieren', [mail({ from: null })]).length, 2);
    const output = run('PDF normalisieren', [mail({ messageId: null, attributes: { uid: 8, uidValidity: 42 } })]);
    assert.equal(output[0].json.accounting_attachment.source_reference, 'imap:rechnung@hemingway-hilden.de:42:8');
    assert.throws(() => run('PDF normalisieren', [mail({ messageId: 'a'.repeat(256) })]), /255/);
});

test('forwarded cash reports are filtered before AI, normal invoices preserve pairing', () => {
    const output = run('Kassenberichte ausschließen', [
        { json: { text: 'Finanzbericht\n<Z1>\nFinanzbericht 1 291' } },
        { json: { text: 'Gato Rechnung 100 Altbier 119,00 EUR' } },
        { json: { text: 'Bediener (erweitert) <X1>' } },
    ]);
    assert.equal(output.length, 3);
    assert.deepEqual(Array.from(output, x => x.json.skip_accounting), [true, false, true]);
    assert.equal(output[1].pairedItem.item, 1);
    assert.throws(() => run('Kassenberichte ausschließen', [{ json: { text: '' } }]), /keinen auslesbaren Text/);
});

test('portal payload retrieves the exact linked PDF, including the second original', () => {
    const attachments = run('PDF normalisieren', [mail()]);
    const invoice = { supplier_name: 'Gato', invoice_number: '100', invoice_date: '2026-10-27',
        net_cents: 10000, tax_cents: 1900, gross_cents: 11900,
        lines: [{ description: 'Altbier', article_number: '00123', net_cents: 10000, tax_cents: 1900, gross_cents: 11900, tax_rate: 19, confidence: 99 }],
    };
    for (const attachment of attachments) {
        const output = run('JSON + Validierung + Portal Payload', [], {
            $json: { text: JSON.stringify(invoice) },
            $: name => {
                if (name === 'Kassenberichte ausschließen') return { item: { json: { text: 'original invoice text' } } };
                assert.equal(name, 'PDF normalisieren'); return { item: attachment };
            },
        });
        assert.equal(output.binary.eingangsrechnung_0.data, attachment.binary.eingangsrechnung_0.data);
        assert.equal(output.json.portal_payload.original_filename, attachment.json.accounting_attachment.original_filename);
        assert.equal(output.json.portal_payload.source_reference, attachment.json.accounting_attachment.source_reference);
        assert.equal(output.json.portal_payload.validation_errors.length, 0);
    }
});

test('import template is inactive, has no credentials/secrets and no positional merge', () => {
    assert.equal(workflow.nodes.some(n => n.type === 'n8n-nodes-base.emailReadImap'), false);
    assert.equal(node('Alle 2 Minuten').parameters.rule.interval[0].minutesInterval, 2);
    assert.equal(node('IMAP - Ungelesene Mails suchen').type, 'n8n-nodes-imap.imap');
    assert.equal(node('IMAP - Ungelesene Mails suchen').parameters.emailFlags.seen, false);
    assert.equal(workflow.active, false);
    assert.equal(workflow.nodes.some(n => n.type === 'n8n-nodes-base.merge'), false);
    for (const n of workflow.nodes) assert.equal(n.credentials, undefined);
    const headers = node('Hemingway Portal - Beleg übergeben').parameters.headerParameters.parameters;
    assert.equal(headers.find(h => h.name === 'Authorization').value, 'Bearer HEMINGWAY_ACCOUNTING_IMPORT_TOKEN_EINTRAGEN');
    assert.equal(workflow.connections['JSON + Validierung + Portal Payload'].main[0][0].node, 'Hemingway Portal - Beleg übergeben');
});

test('embedded code matches the reviewable script sources', () => {
    for (const [name, file] of [['PDF normalisieren', 'accounting-attachments.js'], ['Kassenberichte ausschließen', 'accounting-polling-document-filter.js'], ['Gato Original ans Portal', 'accounting-gato-payload.js'], ['Original für Lieferantenprüfung', 'accounting-inspect-pdf.js']]) {
        assert.equal(node(name).parameters.jsCode, fs.readFileSync(new URL(`../../workflows/n8n/scripts/${file}`, import.meta.url), 'utf8'));
    }
});

test('forwarding sender never determines supplier; PDF inspection chooses the Gato branch', () => {
    for (const from of ['chris@example.test', 'buchhaltung@example.test', 'info@getraenke-gato.de', null]) {
        const [attachment] = run('PDF normalisieren', [mail({ from })]);
        assert.equal(attachment.json.accounting_supplier, undefined);
        const output = run('Gato Original ans Portal', [], {
            $json: { supplier: 'gato', profile: 'gato_layout_v1' },
            $: name => ({ item: name === 'PDF normalisieren' ? attachment : { json: { text: 'original text' } } }),
        });
        assert.equal(output.json.portal_payload.extraction_profile, 'gato_layout_v1');
        assert.equal(output.binary.eingangsrechnung_0.data, 'PDF_ONE');
        assert.equal(output.json.portal_payload.extracted_text, 'original text');
        assert.equal(output.json.portal_payload.lines, undefined);
    }
    assert.equal(workflow.connections['Kassenberichte ausschließen'].main[0][0].node, 'Kassenbericht überspringen?');
    assert.equal(workflow.connections['Kassenbericht überspringen?'].main[1][0].node, 'Original für Lieferantenprüfung');
    assert.equal(workflow.connections['Original für Lieferantenprüfung'].main[0][0].node, 'Lieferant aus PDF erkennen');
    assert.equal(workflow.connections['Lieferant aus PDF erkennen'].main[0][0].node, 'Gato ohne KI?');
    assert.equal(workflow.connections['Gato ohne KI?'].main[0][0].node, 'Gato Original ans Portal');
    assert.equal(workflow.connections['Gato ohne KI?'].main[1][0].node, 'Rechnung mit GPT auslesen');
});

test('poller downloads invoice mails but never selects direct Vectron mail for download', () => {
    const items = run('Vectron vor Download ausschließen', [
        { json: { uid: 1, envelope: { from: [{ address: 'noreply@myvectron.com' }] } } },
        { json: { uid: 2, envelope: { from: [{ address: 'buchhaltung@example.test' }] } } },
    ]);
    assert.equal(items.length, 1);
    assert.equal(items[0].json.uid, 2);
    assert.equal(items[0].pairedItem.item, 1);
});

test('community IMAP metadata is linked to both originals and retains expected attachment count', () => {
    const source = { uid: 12, headers: { 'message-id': '<poll@example.test>' }, envelope: { from: [{ address: 'chris@example.test' }] } };
    const canonical = run('Mail mit Anlagen', [], { $: () => ({ item: { json: source } }), $binary: mail().binary });
    const originals = run('PDF normalisieren', [canonical]);
    assert.equal(originals.length, 2);
    for (const item of originals) {
        assert.equal(item.json.accounting_mail.uid, '12');
        assert.equal(item.json.accounting_mail.expected_pdf_count, 2);
    }
    assert.equal(originals[0].json.accounting_attachment.source_reference, '<poll@example.test>');
});

test('only confirmed portal storage is accepted; duplicate responses are also safe', () => {
    const original = { json: { accounting_mail: { uid: '12', expected_pdf_count: 2 }, accounting_attachment: { original_binary_key: 'pdf_0' } } };
    for (const data of [{ document: { id: 7, file_path: 'original.pdf' } }, { duplicate: true, document: { id: 7, file_path: 'original.pdf' } }]) {
        assert.equal(run('PDF-Ergebnis prüfen', [], { $: () => ({ item: original }), $json: data }).json.status, 'accepted');
    }
    for (const data of [{}, { document: { id: 7 } }, { _polling_result: 'failed' }]) {
        assert.equal(run('PDF-Ergebnis prüfen', [], { $: () => ({ item: original }), $json: data }).json.status, 'failed');
    }
});

test('partial failure leaves its mail in INBOX while other complete mails may move', () => {
    const row = (uid, key, status, count = 2) => ({ json: { imap_uid: uid, attachment_key: key, expected_pdf_count: count, status } });
    const output = run('Vollständige Mails bestätigen', [
        row('12', 'a', 'accepted'), row('12', 'b', 'failed'),
        row('13', 'a', 'accepted'), row('13', 'b', 'accepted'),
        row('14', 'a', 'skipped', 1), row('15', 'a', 'accepted'),
    ]);
    assert.deepEqual(Array.from(output, x => x.json.imap_uid), ['13']);
    assert.equal(run('Vollständige Mails bestätigen', [row('12', 'a', 'accepted'), row('12', 'a', 'accepted')]).length, 0);
});

test('one PDF loop finishes before mail move; processing errors continue to the next PDF', () => {
    assert.equal(node('PDFs einzeln verarbeiten').parameters.batchSize, 1);
    assert.equal(workflow.connections['PDFs einzeln verarbeiten'].main[0][0].node, 'Vollständige Mails bestätigen');
    assert.equal(workflow.connections['PDFs einzeln verarbeiten'].main[1][0].node, 'Extract from File');
    assert.equal(workflow.connections['PDF-Ergebnis prüfen'].main[0][0].node, 'PDFs einzeln verarbeiten');
    assert.equal(workflow.connections['Vollständige Mails bestätigen'].main[0][0].node, 'IMAP - Erfolgreiche Mail archivieren');
    for (const name of ['Extract from File', 'Lieferant aus PDF erkennen', 'Rechnung mit GPT auslesen', 'Hemingway Portal - Beleg übergeben']) {
        assert.equal(node(name).onError, 'continueErrorOutput');
        assert.equal(workflow.connections[name].main[1][0].node, 'PDF fehlgeschlagen');
    }
    assert.equal(node('IMAP - Erfolgreiche Mail archivieren').parameters.destinationMailbox.value, 'INBOX/Eingangsrechnungen');
});
