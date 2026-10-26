import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const workflow = JSON.parse(fs.readFileSync(new URL('../../workflows/n8n/IONOS-Vectron-Z-Bericht.json', import.meta.url)));
function run(name, context) {
    const node = workflow.nodes.find(node => node.name === name);
    return vm.runInNewContext(`(function () { ${node.parameters.jsCode}\n })()`, context);
}
function plain(value) { return JSON.parse(JSON.stringify(value)); }

test('multiple PDFs keep their original binary references and item links', () => {
    const items = [{json: {messageId: 'mail-1', from: {value: [{address: 'sender@example.test'}]}}, binary: {
        attachment_0: {fileName: 'report1.pdf', mimeType: 'application/pdf', data: 'ref-1'},
        attachment_1: {fileName: 'image.png', mimeType: 'image/png', data: 'ref-image'},
        attachment_2: {fileName: 'report2.PDF', mimeType: 'application/octet-stream', data: 'ref-2'},
    }}, {json: {messageId: 'mail-2'}, binary: {attachment_0: {fileName: 'report3.pdf', mimeType: 'application/pdf', data: 'ref-3'}}}];
    const configured = run('Konfiguration', {$input: {all: () => items}});
    configured.forEach(item => item.json.platform_url = 'https://platform.example.test');
    const split = run('PDF-Anhaenge', {$input: {all: () => configured}});
    assert.equal(split.length, 3);
    assert.deepEqual(plain(split.map(item => item.binary.data.data)), ['ref-1', 'ref-2', 'ref-3']);
    assert.deepEqual(plain(split.map(item => item.pairedItem.item)), [0, 0, 1]);
    assert.equal(split[2].json.message_id, 'mail-2');
});

test('sender filter ignores unexpected senders and non-PDF emails', () => {
    const items = [{json: {allowed_sender: 'trusted@example.test', from: 'Other <other@example.test>'}, binary: {a: {fileName: 'z.pdf'}}}, {json: {}, binary: {a: {fileName: 'logo.png'}}}];
    assert.equal(run('PDF-Anhaenge', {$input: {all: () => items}}).length, 0);
});

test('PDF extraction failure retains the original but never authorizes upload', () => {
    const original = {json: {platform_url: 'https://platform.example.test', message_id: 'mail-1'}, binary: {data: {data: 'original-pdf'}}};
    const result = run('Original und Metadaten', {$json: {error: 'PDF has no text'}, $: () => ({item: original})});
    assert.equal(result.json.extracted_text, '');
    assert.equal(result.json.z_report_verified, false);
    assert.equal(result.binary.data.data, 'original-pdf');
    assert.equal(result.json.message_id, 'mail-1');
    const withText = run('Original und Metadaten', {$json: {text: 'Finanzbericht <Z1>'}, $: () => ({item: original})});
    assert.equal(withText.json.extracted_text, 'Finanzbericht <Z1>');
    assert.equal(withText.json.z_report_verified, false);
});

test('workflow is inactive, credential-free, and uploads multipart PDFs with retries', () => {
    assert.equal(workflow.active, false);
    assert.ok(workflow.nodes.every(node => !node.credentials));
    const upload = workflow.nodes.find(node => node.name === 'An Zeiterfassung uebergeben');
    assert.equal(upload.parameters.genericAuthType, 'httpHeaderAuth');
    assert.equal(upload.parameters.contentType, 'multipart-form-data');
    assert.equal(upload.retryOnFail, true);
    assert.equal(upload.maxTries, 3);
    assert.equal(upload.parameters.bodyParameters.parameters[0].inputDataFieldName, 'data');
    const extract = workflow.nodes.find(node => node.name === 'PDF-Text lesen');
    assert.equal(extract.parameters.options.keepSource, 'both');
    for (const connection of Object.values(workflow.connections)) {
        for (const target of connection.main.flat()) assert.ok(workflow.nodes.some(node => node.name === target.node));
    }
});
