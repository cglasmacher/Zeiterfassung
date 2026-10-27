// The done output is reached only after every queued PDF has produced an outcome.
const groups = new Map();
for (const item of $input.all()) {
    const row = item.json;
    if (!row.imap_uid || !row.attachment_key || !Number.isInteger(row.expected_pdf_count)) {
        throw new Error('Unvollständiges Verarbeitungsergebnis; keine Mail verschieben.');
    }
    const group = groups.get(row.imap_uid) ?? { expected: row.expected_pdf_count, outcomes: new Map(), invalid: false };
    if (group.expected !== row.expected_pdf_count || group.outcomes.has(row.attachment_key)) group.invalid = true;
    group.outcomes.set(row.attachment_key, row.status);
    groups.set(row.imap_uid, group);
}
const result = [];
for (const [uid, group] of groups) {
    const statuses = [...group.outcomes.values()];
    if (!group.invalid && statuses.length === group.expected
        && statuses.every(status => status === 'accepted' || status === 'skipped')
        && statuses.includes('accepted')) {
        result.push({ json: { imap_uid: uid } });
    }
}
return result;
