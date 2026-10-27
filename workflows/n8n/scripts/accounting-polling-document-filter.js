// In the polling loop, skipped cash reports must still complete their attachment iteration.
return $input.all().map((item, index) => {
    const text = item.json?.text;
    if (typeof text !== 'string' || !text.trim()) throw new Error('PDF enthält keinen auslesbaren Text; Mail bleibt im Posteingang.');
    const normalized = text.replace(/\s+/g, ' ');
    const skip = /(?:Finanzbericht|Bediener\s*\(erweitert\))\s*<\s*[ZX]\d+\s*>/i.test(normalized)
        || (/\bVectron\b/i.test(normalized) && /\bZ[-\s]?(?:Bericht|Zähler|Zaehler)\b/i.test(normalized));
    return { ...item, json: { ...item.json, skip_accounting: skip }, pairedItem: { item: index } };
});
