Heizkostenabrechnung für heizkostenverteiler, Wasseruhr und Gateway vom Hersteller Engelmann.

Vorbereitung:
- Alle Radioatoren, Handtuchheizkörper etc. mit Heizkostenverteilern HCAe2 ausstatten
- Kq und Kc-Werte der Heizkörper ermitteln (lassen)
- Gateway mit IoT-SIM-Karte für ftp-Server parametrieren und an funkgünstiger Stelle über Handbereich (2,50 Höhe) anbringen
- HKVs in Tabelle Heizkostenverteiler eintragen
- Heizkörper mit Kc- und Kq-Werten in Tabelle Heizkörper eintragen
- Mieter und deren Wechsel Tabelle Mieter eintragen
- parseAndMoveCSVs.php regelmäßig z.B. per Cron ausführen (Theoretisch reicht einmal pro Jahr, besser aber jeden Monat)

Die Heizkostenabrechnung funktioniert in zwei Schritten:


1. Heizkostenverteiler HCAe2 von Engelmann an den Heizkörpern senden ihre Daten an einen Engelmann-Gateway im Treppenhaus
2. Dieser Gateway läd jeden Monat eine CSV-Datei per FTP hoch
3. parseAndMoveCSVs.php in "scripts" scannt das Verzeichnis nach CSV-Dateien, schreibt ihren Inhalt in die Datenbank und legt sie in Unterverzeichnisse nach Jahreszahl
4. Am Jahresende Gasrechnung eintragen
5. Verschiedene Seiten zeigen Übersicht und druckbare Einzelabrechnungen



