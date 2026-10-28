// Laravel extracts the embedded Factur-X from the exact original, without AI amounts.
const attachment = $('PDF normalisieren').item;
const metadata = attachment.json.accounting_attachment;
if ($json.supplier !== 'handelshof' || $json.profile !== 'handelshof_facturx_v1' || !metadata?.source_reference || !attachment.binary?.eingangsrechnung_0) {
    throw new Error('Handelshof-Lieferant, Referenz oder Original fehlt.');
}
return {
    json: {
        portal_payload: {
            source: 'n8n',
            source_reference: metadata.source_reference,
            supplier_name: 'Handelshof',
            extraction_profile: 'handelshof_facturx_v1',
            original_filename: metadata.original_filename,
        },
    },
    binary: { eingangsrechnung_0: attachment.binary.eingangsrechnung_0 },
};
