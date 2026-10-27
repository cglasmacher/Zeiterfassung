// The community IMAP node is the same one used by the working Z-report poller.
const source = $('IMAP - Ungelesene Mails suchen').item.json;
const uid = String(source.uid ?? '');
if (!/^[1-9]\d*$/.test(uid)) throw new Error('IMAP UID fehlt.');
return {
    json: {
        ...source,
        from: source.envelope?.from ?? source.from ?? source.headers?.from,
        messageId: typeof source.headers?.['message-id'] === 'string'
            ? source.headers['message-id'] : source.envelope?.messageId ?? source.messageId ?? null,
        accounting_mail_uid: uid,
    },
    binary: $binary,
};
