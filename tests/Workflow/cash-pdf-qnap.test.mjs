import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const workflow = JSON.parse(fs.readFileSync(new URL('../../workflows/n8n/Hemingway-Schicht-Tag-QNAP.json', import.meta.url)));
function run(name, context) {
    const node = workflow.nodes.find(node => node.name === name);
    return vm.runInNewContext(`(function () { ${node.parameters.jsCode}\n })()`, context);
}
const config = {platform_url: 'https://platform.example.test', qnap_base_path: '/share/Buchhaltung/Kassendoku'};
const context = documents => ({$json: {documents}, $: () => ({first: () => ({json: config})})});
function doc(kind, id, filename) {
    return {document_key: `${kind}-${id}`, relative_path: `2026/10/${filename}`, download_path: `/api/integrations/cash-documents/${kind}/${id}/pdf`, ack_path: `/api/integrations/cash-documents/${kind}/${id}/ack`};
}
test('archive paths preserve requested daily names and multiple handovers', () => {
    const docs = [doc('handover', 1, '2026.10.16-Schicht.pdf'), doc('handover', 2, '2026.10.16-Schicht-02.pdf'), doc('day', 1, '2026.10.16-Tag.pdf')];
    const result = run('Dokumente aufteilen', context(docs));
    assert.equal(result.length, 3);
    for (let i=0; i<docs.length; i++) assert.equal(result[i].json.qnap_path, `${config.qnap_base_path}/${docs[i].relative_path}`);
    const file = {data: 'binary-ref-2'};
    const recovered = run('PDF und Ablagepfad', {$binary: {data: file}, $: () => ({item: result[1]})});
    assert.equal(recovered.binary.data.data, 'binary-ref-2');
    assert.equal(recovered.json.document_key, 'handover-2');
});
test('empty lists stop cleanly and invalid endpoints or paths stop before transfer', () => {
    assert.equal(run('Dokumente aufteilen', context([])).length, 0);
    assert.throws(() => run('Dokumente aufteilen', context(null)));
    assert.throws(() => run('Dokumente aufteilen', context([{...doc('day',1,'2026.10.16-Tag.pdf'), download_path: 'https://outside.example.test/secret'}])));
    assert.throws(() => run('Dokumente aufteilen', context([{...doc('day',1,'2026.10.16-Tag.pdf'), relative_path: '../escape.pdf'}])));
});
test('acknowledgment follows successful SFTP and every HTTP call uses header auth', () => {
    assert.equal(workflow.active, false);
    const http = workflow.nodes.filter(node => node.type === 'n8n-nodes-base.httpRequest');
    assert.equal(http.length, 3);
    assert.ok(http.every(node => node.parameters.genericAuthType === 'httpHeaderAuth' && !node.credentials));
    assert.equal(workflow.connections['Auf QNAP ablegen'].main[0][0].node, 'Ablage bestaetigen');
    const download = workflow.nodes.find(node => node.name === 'PDF herunterladen');
    assert.equal(download.parameters.options.response.response.responseFormat, 'file');
    assert.equal(download.parameters.options.response.response.outputPropertyName, 'data');
});
