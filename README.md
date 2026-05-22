Heizkostenabrechnung für Heizkostenverteiler, Wasseruhr und Gateway² von Hersteller Engelmann.

Vorbereitung:
- Alle Radioatoren, Handtuchheizkörper etc. mit Heizkostenverteilern HCAe2 ausstatten (x Mitte + y 75%)
- Kq- (Leistung in kW) und Kc- (Trägheit Messfühler) Werte der Heizkörper ermitteln (lassen bzw. Liste v. EM)
- Gateway mit IoT-SIM-Karte für ftp-Server parametrieren und an funkgünstiger Stelle über Handbereich (2,50 Höhe) anbringen
- HKVs in Tabelle Heizkostenverteiler eintragen
- Heizkörper mit Kc- und Kq- (Leistung in kW) Werten in Tabelle Heizkörper eintragen
- Mieter und deren Wechsel in Tabelle Mieter pflegen
- parseAndMoveCSVs.php regelmäßig z.B. per Cron ausführen (Theoretisch reicht einmal pro Jahr, besser aber jeden Monat)

Die Heizkostenabrechnung funktioniert in zwei Schritten:


1. Heizkostenverteiler HCAe2 von Engelmann an den Heizkörpern senden ihre Daten an einen Engelmann-Gateway im Treppenhaus
2. Dieser Gateway läd jeden Monat eine CSV-Datei per FTP hoch
3. parseAndMoveCSVs.php in "scripts" scannt das Verzeichnis nach CSV-Dateien, schreibt ihren Inhalt in die Datenbank und legt sie in Unterverzeichnisse nach Jahreszahl
4. Am Jahresende Gasrechnung eintragen
5. Verschiedene Seiten zeigen Übersicht und druckbare Einzelabrechnungen

<sup>²Nach drei Jahren Betrieb Gateway defekt *und* EOL (2.2026), Integration Lobaro next</sup>

