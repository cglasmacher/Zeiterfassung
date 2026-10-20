import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const workflow = JSON.parse(fs.readFileSync(new URL('../../workflows/n8n/IONOS-Vectron-Z-Bericht-QNAP.json', import.meta.url)));
function run(name, context) {
    const node = workflow.nodes.find(node => node.name === name);
    return vm.runInNewContext(`(function () { ${node.parameters.jsCode}\n })()`, context);
}
const original = {json: {qnap_base_path: '/share/Buchhaltung/Kassendoku/'}, binary: {data: {data: 'binary-reference'}}};
const dated = (id, date, extra = {}) => ({id, business_date: date, report_id: '291', archive_filename: `Z-Bericht_${date}_Z-291.pdf`, ...extra});
const archive = json => run('QNAP Ablagepfad', {$json: json, $: () => ({item: original})});

test('archive uses the platform business day including previous month and year', () => {
    const a = archive(dated(1, '2026-10-16'));
    assert.equal(a.json.qnap_path, '/share/Buchhaltung/Kassendoku/2026/10/Z-Bericht_2026-10-16_Z-291.pdf');
    assert.equal(a.binary.data.data, 'binary-reference');
    assert.equal(archive(dated(12, '2025-12-31')).json.qnap_path,
        '/share/Buchhaltung/Kassendoku/2025/12/Z-Bericht_2025-12-31_Z-291.pdf');
    assert.equal(archive(dated(1, '2026-10-16', {duplicate: true})).json.qnap_path, a.json.qnap_path);
});
test('missing date is kept unassigned, never guessed from receipt time', () => {
    assert.equal(archive({id: 9, business_date: null, archive_filename: 'Z-Bericht_unzugeordnet_Beleg-aabbccddeeff.pdf'}).json.qnap_path,
        '/share/Buchhaltung/Kassendoku/Unzugeordnet/Z-Bericht_unzugeordnet_Beleg-aabbccddeeff.pdf');
});
test('invalid platform response and path injection stop the upload', () => {
    assert.throws(() => archive({id: '../report', business_date: '2026-10-16'}));
    assert.throws(() => archive({id: 1, business_date: '2026-02-31'}));
    assert.throws(() => archive({id: 1, business_date: '../../folder'}));
    assert.throws(() => run('QNAP Ablagepfad', {$json: {id: 1}, $: () => ({item: {json: {qnap_base_path: '/share/../outside'}, binary: original.binary}})}));
});
test('the whole workflow preserves the PDF and archive configuration across multiple attachments', () => {
    const input = [{json: {messageId: 'mail-1'}, binary: {a: {fileName: 'first.pdf'}, b: {fileName: 'second.pdf'}}}];
    const config = run('Konfiguration', {$input: {all: () => input}});
    const split = run('PDF-Anhaenge', {$input: {all: () => config}});
    assert.equal(split.length, 2);
    for (const item of split) {
        assert.equal(item.json.qnap_base_path, '/share/Buchhaltung/Kassendoku');
        const recovery = run('Original und Metadaten', {$json: {text: 'report'}, $: () => ({item})});
        assert.equal(recovery.json.qnap_base_path, '/share/Buchhaltung/Kassendoku');
        assert.equal(recovery.binary.data.fileName, item.binary.data.fileName);
    }
});
test('SFTP uses binary uploads with retries and only after platform acknowledgment', () => {
    assert.equal(workflow.active, false);
    assert.ok(workflow.nodes.every(node => !node.credentials));
    const ftp = workflow.nodes.find(node => node.name === 'Auf QNAP speichern');
    assert.equal(ftp.parameters.protocol, 'sftp');
    assert.equal(ftp.parameters.operation, 'upload');
    assert.equal(ftp.parameters.binaryPropertyName, 'data');
    assert.equal(ftp.parameters.binaryData, true);
    assert.equal(ftp.retryOnFail, true);
    assert.equal(workflow.connections['An Zeiterfassung uebergeben'].main[0][0].node, 'QNAP Ablagepfad');
    assert.equal(workflow.connections['QNAP Ablagepfad'].main[0][0].node, ftp.name);
    for (const connection of Object.values(workflow.connections)) {
        for (const target of connection.main.flat()) assert.ok(workflow.nodes.some(node => node.name === target.node));
    }
});
