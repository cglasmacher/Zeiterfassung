// Execute once per PDF after a portal response, intentional skip or error output.
const original = $('PDF normalisieren').item;
const mail = original.json.accounting_mail;
if (!mail?.uid || !Number.isInteger(mail.expected_pdf_count)) throw new Error('Mail-/Anhangszuordnung fehlt.');
const kind = $json._polling_result;
const accepted = kind !== 'failed' && kind !== 'skipped'
    && Number.isInteger($json.document?.id) && $json.document.id > 0
    && typeof $json.document.file_path === 'string' && $json.document.file_path.length > 0;
return {
    json: {
        imap_uid: mail.uid,
        expected_pdf_count: mail.expected_pdf_count,
        attachment_key: original.json.accounting_attachment.original_binary_key,
        status: kind === 'skipped' ? 'skipped' : accepted ? 'accepted' : 'failed',
    },
};
