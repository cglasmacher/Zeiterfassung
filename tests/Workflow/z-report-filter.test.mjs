import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Structure from the supplied Z-294: invoices are mentioned inside genuine reports.
const report = `Hemingway Gastro & Events GmbH
Finanzbericht <Z1>
Tagesumsatzebene
Z-Zähler
Finanzbericht 1                        294
Letzte Nullst.              18.10.26 21:38
Backup Z-Zähler                        253
Umsatz auf Rechnung                2.702,25€
Kasse #1 1
19.10.26 21:55
Bediener (erweitert) <Z1>
1 Frühschicht`;

for (const filename of ['IONOS-Vectron-Z-Bericht.json', 'IONOS-Vectron-Z-Bericht-QNAP.json']) {
    const workflow = JSON.parse(fs.readFileSync(new URL(`../../workflows/n8n/${filename}`, import.meta.url)));
    const nodes = new Map(workflow.nodes.map(node => [node.name, node]));
    function run(name, context) {
        return vm.runInNewContext(`(function () { ${nodes.get(name).parameters.jsCode}\n })()`, context);
    }
    function verify(text, extra = {}, original = {json: {platform_url: 'https://platform.test', qnap_base_path: '/share/Buchhaltung/Kassendoku', message_id: 'mail-294'}, binary: {data: {data: 'original-pdf'}}}) {
        return run('Original und Metadaten', {$json: {text, ...extra}, $: name => {
            assert.equal(name, 'PDF-Anhaenge');
            return {item: original};
        }});
    }
    test(`${filename}: recognizes supplied Z-294 layout despite invoice wording`, () => {
        const result = verify(report);
        assert.equal(result.json.z_report_verified, true);
        assert.equal(result.json.z_report_number, '294');
        assert.equal(result.json.extracted_text, report);
        assert.equal(result.binary.data.data, 'original-pdf');
        assert.equal(result.json.message_id, 'mail-294');
    });
    test(`${filename}: tolerates PDF line breaks, NBSP, zero-width characters and repeated headers`, () => {
        const result = verify(report.replace('<Z1>', '< Z\n1 >').replace('Finanzbericht 1                        294', 'Finanzbericht\u200b\u00a01\f294'));
        assert.equal(result.json.z_report_verified, true);
        assert.equal(verify(report.replace('Backup Z-Zähler', 'Finanzbericht 1 294 Backup Z-Zähler')).json.z_report_verified, true);
    });
    test(`${filename}: rejects invoices, X reports, operator-only reports and ambiguous numbers`, () => {
        for (const text of [
            'Rechnung 294 vom 19.10.26 Gesamtbetrag 2.702,25 EUR',
            report.replaceAll('<Z1>', '<X1>'),
            'Bediener (erweitert) <Z1> Finanzbericht 1 294',
            'Finanzbericht <Z1> Z-Zähler',
            'Finanzbericht 1 294',
            'Finanzbericht <Z1> Bediener <Z1> Finanzbericht 1 294',
            report.replace('294', '294a'),
            report.replace('294', '123456789012345678901'),
            report.replace('294', '0'),
            report.replace('Backup Z-Zähler', 'Finanzbericht 1 295 Backup Z-Zähler'),
        ]) {
            const result = verify(text);
            assert.equal(result.json.z_report_verified, false, text);
            assert.equal(result.json.z_report_number, null);
            assert.ok(result.json.z_report_filter_reason);
        }
    });
    test(`${filename}: rejects missing text, extractor errors and oversized output`, () => {
        for (const text of ['', '   ', null, undefined, {text: report}]) {
            assert.equal(verify(text).json.z_report_verified, false);
        }
        assert.equal(verify(report, {error: 'Extraction failed'}).json.z_report_verified, false);
        assert.equal(verify(report + ' '.repeat(1000001)).json.z_report_verified, false);
        // Caller-provided flags never bypass classification.
        assert.equal(verify('Rechnung', {z_report_verified: true}).json.z_report_verified, false);
    });
    test(`${filename}: each attachment in a mixed mail needs its own positive proof`, () => {
        const input = [{json: {subject: 'Z-Bericht 294', messageId: 'mixed-mail'}, binary: {
            a: {fileName: 'Z-Bericht.pdf', mimeType: 'application/pdf', data: 'invoice-bytes'},
            b: {fileName: 'Rechnung.pdf', mimeType: 'application/pdf', data: 'report-bytes'},
            c: {fileName: 'scan.pdf', mimeType: 'application/pdf', data: 'scan-bytes'},
        }}];
        const configured = run('Konfiguration', {$input: {all: () => input}});
        const split = run('PDF-Anhaenge', {$input: {all: () => configured}});
        const texts = ['Rechnung 123', report, ''];
        const results = split.map((item, i) => {
            const result = verify(texts[i], {}, item);
            assert.strictEqual(result.binary, item.binary);
            if (filename.includes('QNAP')) assert.equal(result.json.qnap_base_path, item.json.qnap_base_path);
            return result;
        });
        assert.equal(results.filter(item => item.json.z_report_verified).length, 1);
        assert.equal(results.find(item => item.json.z_report_verified).binary.data.data, 'report-bytes');
    });
    test(`${filename}: only strict true reaches upload and false ends in a saved review branch`, () => {
        const gate = nodes.get('Nur bestaetigte Z-Berichte');
        assert.equal(gate.type, 'n8n-nodes-base.if');
        const condition = gate.parameters.conditions.conditions[0];
        assert.equal(gate.parameters.conditions.options.typeValidation, 'strict');
        assert.equal(condition.leftValue, '={{ $json.z_report_verified }}');
        assert.equal(condition.operator.type, 'boolean');
        assert.equal(condition.operator.operation, 'true');
        assert.equal(workflow.connections['Original und Metadaten'].main[0][0].node, gate.name);
        assert.equal(workflow.connections[gate.name].main[0][0].node, 'An Zeiterfassung uebergeben');
        const rejected = workflow.connections[gate.name].main[1][0].node;
        assert.equal(nodes.get(rejected).type, 'n8n-nodes-base.noOp');
        assert.equal(workflow.connections[rejected], undefined);
        const uploadSources = Object.entries(workflow.connections).filter(([, connection]) =>
            connection.main.flat().some(target => target.node === 'An Zeiterfassung uebergeben')).map(([name]) => name);
        assert.deepEqual(uploadSources, [gate.name]);
        assert.equal(workflow.settings.saveDataSuccessExecution, 'all');
        assert.equal(workflow.active, false);
    });
}
