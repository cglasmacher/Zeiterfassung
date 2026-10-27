# Eingangsrechnungen: Mail und PDF-Anhänge

Basis ist der vom Betreiber am 27.10.2026 exportierte Workflow „01 - Hemingway - Eingangsrechnungen - STABIL“. Ein direkter Zugriff auf den n8n-Live-Stand besteht weiterhin nicht.

## Import

1. Zuerst Portaländerungen aus PR #62 ausrollen und Migrationen ausführen.
2. Bestehenden n8n-Workflow sichern. Die Datei `workflows/n8n/Hemingway-Eingangsrechnungen.json` als **inaktiven** Workflow importieren.
3. Im IMAP- und OpenAI-Node die vorhandenen Zugangsdaten auswählen. Im HTTP-Node den Platzhalter durch den vorhandenen Portal-Bearer-Token ersetzen (nicht ins Repository schreiben).
4. Mit einer neuen Testmail und zwei PDF-Rechnungen testen. Es müssen zwei Belege mit jeweils passendem Dateinamen, Inhalt und eigener Quellreferenz entstehen. Erneuter Import muss dieselben Belege liefern.
5. Eine myVectron-Mail, eine weitergeleitete Finanzbericht-PDF, eine Mail ohne PDF und eine Mail mit Logo vor der PDF testen. Kassenberichte dürfen nicht bei GPT oder im Rechnungsportal ankommen.
6. Den alten Workflow deaktivieren, bevor der neue aktiviert wird. Beide verwenden dasselbe UNSEEN-Postfach; paralleler Betrieb ist ungeeignet. Während des Wechsels keine alten Mails pauschal als ungelesen markieren.

## Änderungen

Absender `noreply@myvectron.com` wird bereits im IMAP-Suchfilter und zusätzlich vor der Analyse ausgeschlossen. Suchkriterium: `["UNSEEN",["!FROM","noreply@myvectron.com"]]` (node-imap-Suchsyntax: https://github.com/mscdex/node-imap#connection-instance-methods). Damit wird diese Mail vom Rechnungsworkflow nicht zum Lesen ausgewählt. Jede PDF wird einzeln mit n8n-Item-Verknüpfung weitergereicht. Ein zusätzlicher Textfilter entfernt erkennbare Finanz-/Bedienerberichte vor GPT. Ein ungeklärter Absender, fehlende stabile Mailidentität oder eine textlose PDF stoppt die Ausführung mit Fehlermeldung.

Der Payload-Node holt das Original über das verknüpfte Anhangsitem. Der bisherige Merge nach Position entfällt. Die erste PDF behält die bisherige Message-ID als Quellreferenz; weitere PDFs erhalten einen Anhangsschlüssel als Zusatz. Ein IMAP-Fallback ist nur mit UIDVALIDITY und UID zulässig. Schlüssel über 255 Zeichen werden nicht gekürzt, sondern abgewiesen.

Original-Prompt, Modell und vorhandene Summenprüfung bleiben für andere Lieferanten erhalten. Erkannte Gato-Mails werden ohne GPT an die deterministische Portalverarbeitung übergeben; siehe `docs/accounting-gato.md`. Der Textfilter ist eine zusätzliche Barriere, kein allgemeiner Belegtypklassifikator. Nichtrechnungen anderer Absender sind damit noch nicht vollständig ausgeschlossen.

## Grenzen und Tests

`node --test tests/Workflow/accounting-invoices.test.mjs`

Die Tests führen den eingebetteten JavaScript-Code mit n8n-Eingaben und verknüpften Items als Testdoubles aus. Sie prüfen mehrere Anhänge, beide Absenderformate, stabile Referenzen, Kassenberichtfilter und die Identität zwischen analysiertem und hochgeladenem PDF. Der echte n8n-Lauf einschließlich Item-Verknüpfung und IMAP-Verhalten muss vor Aktivierung bestätigt werden.

OCR und Änderungen des Gelesen-Status nach erfolgreichem Import sind nicht Teil dieses Schrittes. Gato-Analysefehler werden mit Original im Portal gespeichert; andere Fehlerausführungen müssen weiterhin in n8n geprüft und gezielt erneut ausgeführt werden. Zwei Mails mit unterschiedlichen Message-IDs können weiterhin dieselbe Rechnung enthalten; eine inhaltliche Dublettenerkennung folgt separat.
