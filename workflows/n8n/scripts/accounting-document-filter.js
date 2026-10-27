// n8n Code node, runOnceForAllItems, immediately after PDF extraction.
// Also catches forwarded Vectron reports whose sender is no longer myVectron.
const result = [];
for (const [index, item] of $input.all().entries()) {
    const text = item.json?.text;
    if (typeof text !== 'string' || !text.trim()) {
        throw new Error('PDF enthält keinen auslesbaren Text; Original manuell prüfen.');
    }
    const normalized = text.replace(/\s+/g, ' ');
    const isCashReport = /(?:Finanzbericht|Bediener\s*\(erweitert\))\s*<\s*[ZX]\d+\s*>/i.test(normalized)
        || (/\bVectron\b/i.test(normalized) && /\bZ[-\s]?(?:Bericht|Zähler|Zaehler)\b/i.test(normalized));
    if (isCashReport) continue;
    result.push({ ...item, pairedItem: { item: index } });
}
return result;
