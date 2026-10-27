// Gato branch bypasses AI. Laravel parses the original PDF and owns all accounting.
const attachment = $('PDF normalisieren').item;
const metadata = attachment.json.accounting_attachment;
if (attachment.json.accounting_supplier !== 'gato' || !metadata?.source_reference || !attachment.binary?.eingangsrechnung_0) {
    throw new Error('Gato-Lieferant, Referenz oder Original fehlt.');
}
return {
    json: {
        portal_payload: {
            source: 'n8n',
            source_reference: metadata.source_reference,
            supplier_name: 'Getränke Gato GmbH',
            extraction_profile: 'gato_layout_v1',
            original_filename: metadata.original_filename,
            extracted_text: $json.text ?? null,
        },
    },
    binary: { eingangsrechnung_0: attachment.binary.eingangsrechnung_0 },
};
