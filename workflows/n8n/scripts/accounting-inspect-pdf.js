// Reattach the exact original before the read-only content inspection request.
const attachment = $('PDF normalisieren').item;
if (!attachment.binary?.eingangsrechnung_0) throw new Error('Original-PDF fehlt.');
return { json: $json, binary: { eingangsrechnung_0: attachment.binary.eingangsrechnung_0 } };
