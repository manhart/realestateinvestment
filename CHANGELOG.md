# Changelog

Änderungen am Immobilien-Kapitalanlage-Rechner. Neueste Einträge stehen oben.
Version 0.1.0 fasst den ersten dokumentierten Stand zusammen; ältere Commits
werden nicht nachträglich als eigene Releases bezeichnet.

## [Unreleased]

### Neu
- Kleine Versionsanzeige mit einer direkt in der App abrufbaren Änderungshistorie.
- Grundschuld-Kosten wahlweise als Prozentschätzung, Gesamtbetrag oder getrennte Notar- und Grundbuchbeträge erfassen.
- Steuerliche Voraussetzungen und offene Annahmen in einem eigenen Prüfbereich einordnen.
- Angewandte AfA, Vergleichswerte und Buchwerte für einzelne Jahre in den Rechenwegen nachvollziehen.

### Verbessert
- Gesetzlichen linearen Gebäude-AfA-Satz aus dem Fertigstellungsjahr ableiten; abweichende Sätze und Nutzungsdauern erfordern eine ausdrückliche Experteneinstellung.
- Hinweise zur Steuerprognose getrennt von der bestehenden Bewertung der Kennzahlen anzeigen.
- Erwerbskosten und einmalige Finanzierungskosten verständlicher trennen; nicht aktive Eingabearten bleiben erhalten, werden aber nicht mitgerechnet.

### Korrigiert
- Nicht verwendete Darlehensmittel als Kreditüberschuss im Cashflow erhalten, statt sie bei selbst bezahlten Bauzeitzinsen oder Disagio verschwinden zu lassen. Diese Mittel sind kein Ertrag und werden nicht automatisch sondergetilgt.
- Möbel- und separate Stellplatz-AfA auf ihre jeweilige Anschaffungsbasis begrenzen.
- Automatischen Wechsel von degressiver zu linearer AfA anhand von Restbuchwert und verbleibender Nutzungsdauer berechnen und beibehalten.
- Reguläre und besondere AfA im vierjährigen §7b-Zeitraum getrennt führen und anschließend den gemeinsamen Restbuchwert verwenden.
- Für §7b kein Bauantragsdatum mehr aus dem Kaufdatum ableiten; stattdessen tatsächliches Datum oder ausdrücklich gewählten Antragszeitraum verwenden.
- Nicht unterstützte Einkommensteuertarife ausdrücklich zurückweisen statt mit einem anderen Tarif weiterzurechnen.
- Rücksetzbutton „zurück auf auto“ bei der Stellplatz-Wertsteigerung innerhalb der Feldbreite halten, damit benachbarte Eingaben den Klick nicht abfangen.

## [0.1.0] - 2026-10-24

### Neu
- Immobilien mit mehreren Darlehen und Stellplätzen sowie individuellen Miet-, Kosten- und Verkaufsannahmen berechnen.
- Jahresübersicht mit Finanzierung, Abschreibung, steuerlichem Ergebnis, Cashflows und Verkauf erstellen.
- Nominalen Vermögenseffekt, Eigenkapitalkennzahlen, Barwert, Future Value, DSCR und Debt Yield samt Bewertungsskalen anzeigen.
- Grenzsteuersatz oder Einkommensteuertarif 2025/2026 mit Solidaritätszuschlag und optionaler Kirchensteuer verwenden.
- Lineare und degressive Gebäude-AfA, Sonder-AfA nach §7b sowie Möbel- und Stellplatz-AfA abbilden.
- Bauzeitzinsen je Jahr und Disagio getrennt nach Finanzierung und steuerlichem Ansatz erfassen.
- Szenarien im privaten Workspace speichern, gruppieren, laden, kopieren, umbenennen, teilen und löschen.
- Szenarien vergleichen und Eingaben über Formeln, Rechenwege und Info-Hilfen nachvollziehen.
- Optionale Sondertilgung aus verfügbaren Überschüssen und eigene Wertsteigerung für Stellplätze berücksichtigen.

### Verbessert
- Eingaben automatisch neu berechnen und Kennzahlen in der mitlaufenden Seitenleiste anzeigen.
- Automatische und manuelle Eingabewerte kenntlich machen; Eingabeblöcke einklappbar und Jahresübersicht kompakter darstellen.
- Laden, Speichern und Kopieren verständlich trennen sowie ungespeicherte Änderungen vor unbeabsichtigtem Verwerfen schützen.
- Workspace-Link sichtbar zum Kopieren und zum manuellen Speichern als Browser-Lesezeichen anbieten.
- Schalter und Info-Icons gegen versehentliche Zustandswechsel und überlappende Bedienelemente verbessern.

### Korrigiert
- Für Bauzeitzinsen und Disagio reservierte Darlehensmittel nicht zugleich für den Kauf verwenden.
- Anfangs-Eigenkapital zur Deckung von Finanzierungslücken verwenden und bereits erfasste Ausgaben nicht doppelt abziehen.
- Ausdrücklich eingegebene 0 % Stellplatz-Wertsteigerung beim Übernehmen von Standardwerten erhalten.
