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
    assert.throws(() => run('PDF normalisieren', [mail({ from: null })]), /Absender fehlt/);
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
    assert.equal(output.length, 1);
    assert.equal(output[0].pairedItem.item, 1);
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
    assert.deepEqual(JSON.parse(node('01 - Hemingway - Eingangsrechnungen').parameters.options.customEmailConfig), ['UNSEEN', ['!FROM', 'noreply@myvectron.com']]);
    assert.equal(workflow.active, false);
    assert.equal(workflow.nodes.some(n => n.type === 'n8n-nodes-base.merge'), false);
    for (const n of workflow.nodes) assert.equal(n.credentials, undefined);
    const headers = node('Hemingway Portal - Beleg übergeben').parameters.headerParameters.parameters;
    assert.equal(headers.find(h => h.name === 'Authorization').value, 'Bearer HEMINGWAY_ACCOUNTING_IMPORT_TOKEN_EINTRAGEN');
    assert.equal(workflow.connections['JSON + Validierung + Portal Payload'].main[0][0].node, 'Hemingway Portal - Beleg übergeben');
});

test('embedded code matches the reviewable script sources', () => {
    for (const [name, file] of [['PDF normalisieren', 'accounting-attachments.js'], ['Kassenberichte ausschließen', 'accounting-document-filter.js'], ['Gato Original ans Portal', 'accounting-gato-payload.js']]) {
        assert.equal(node(name).parameters.jsCode, fs.readFileSync(new URL(`../../workflows/n8n/scripts/${file}`, import.meta.url), 'utf8'));
    }
});

test('Gato sender is recognized exactly and sends original to deterministic portal profile', () => {
    const [attachment] = run('PDF normalisieren', [mail({ from: 'Rechnung <info@getraenke-gato.de>' })]);
    assert.equal(attachment.json.accounting_supplier, 'gato');
    assert.equal(run('PDF normalisieren', [mail({ from: 'info@getraenke-gato.de.evil.test' })])[0].json.accounting_supplier, null);
    const output = run('Gato Original ans Portal', [], {
        $json: { text: 'extracted text' }, $: () => ({ item: attachment }),
    });
    assert.equal(output.json.portal_payload.extraction_profile, 'gato_layout_v1');
    assert.equal(output.json.portal_payload.supplier_name, 'Getränke Gato GmbH');
    assert.equal(output.binary.eingangsrechnung_0.data, 'PDF_ONE');
    assert.equal(output.json.portal_payload.extracted_text, 'extracted text');
    assert.equal(output.json.portal_payload.lines, undefined);
    assert.equal(workflow.connections['Gato ohne KI?'].main[0][0].node, 'Gato Original ans Portal');
    assert.equal(workflow.connections['Gato ohne KI?'].main[1][0].node, 'Rechnung mit GPT auslesen');
});
