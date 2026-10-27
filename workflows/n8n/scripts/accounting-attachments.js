// n8n Code node, runOnceForAllItems. Preserve item linking for each attachment.
const result = [];
const inputs = $input.all();
for (let index = 0; index < inputs.length; index++) {
    const mail = inputs[index];
    const data = mail.json ?? {};
    const sender = data.from ?? data.metadata?.from ?? data.headers?.from;
    const addresses = [];
    function collect(value) {
        if (Array.isArray(value)) return value.forEach(collect);
        if (value && typeof value === 'object') {
            if (value.address) collect(value.address);
            if (value.value) collect(value.value);
            if (value.text) collect(value.text);
        } else if (typeof value === 'string') {
            addresses.push(...(value.match(/[A-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Z0-9.-]+/gi) ?? []));
        }
    }
    collect(sender);
    if (addresses.some(address => address.toLowerCase() === 'noreply@myvectron.com')) continue;

    const binaries = mail.binary ?? {};
    const pdfKeys = Object.keys(binaries).filter(key => {
        const file = binaries[key];
        return file?.mimeType?.toLowerCase() === 'application/pdf' || /\.pdf$/i.test(file?.fileName ?? '');
    });
    // Other mail and image attachments do not interrupt invoice processing.
    if (!pdfKeys.length) continue;

    const messageId = data.metadata?.['message-id'] ?? data.metadata?.messageId ?? data.messageId ?? data.headers?.['message-id'];
    const uid = data.attributes?.uid;
    const validity = data.attributes?.uidValidity ?? data.attributes?.uidvalidity;
    const base = typeof messageId === 'string' && messageId.trim()
        ? messageId.trim()
        : uid != null && validity != null ? `imap:rechnung@hemingway-hilden.de:${validity}:${uid}` : null;
    if (!base) throw new Error('Stabile Message-ID oder IMAP UIDVALIDITY + UID fehlt.');

    for (let position = 0; position < pdfKeys.length; position++) {
        const key = pdfKeys[position];
        // Keep legacy identity for the first PDF; additional originals are independent.
        const reference = position === 0 ? base : `${base}:attachment:${encodeURIComponent(key)}`;
        if (reference.length > 255) throw new Error('Quellreferenz überschreitet 255 Zeichen.');
        result.push({
            json: {
                ...data,
                ...(data.accounting_mail_uid ? { accounting_mail: { uid: data.accounting_mail_uid, expected_pdf_count: pdfKeys.length } } : {}),
                accounting_attachment: {
                    source_reference: reference,
                    original_filename: binaries[key].fileName ?? `${key}.pdf`,
                    original_binary_key: key,
                },
            },
            binary: { eingangsrechnung_0: binaries[key] },
            pairedItem: { item: index },
        });
    }
}
return result;
