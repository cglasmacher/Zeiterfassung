// Laravel parses the exact native PDF, without AI amounts.
const attachment = $('PDF normalisieren').item;
const metadata = attachment.json.accounting_attachment;
if ($json.supplier !== 'isb' || $json.profile !== 'isb_layout_v1' || !metadata?.source_reference || !attachment.binary?.eingangsrechnung_0) {
    throw new Error('ISB-Lieferant, Referenz oder Original fehlt.');
}
return {
    json: {
        portal_payload: {
            source: 'n8n',
            source_reference: metadata.source_reference,
            supplier_name: 'ISB',
            extraction_profile: 'isb_layout_v1',
            original_filename: metadata.original_filename,
        },
    },
    binary: { eingangsrechnung_0: attachment.binary.eingangsrechnung_0 },
};
