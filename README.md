Heizkostenabrechnung für Heizkostenverteiler, Wasseruhr und Gateway² von Hersteller Engelmann zum Betrieb auf privatem Host

Vorbereitung:
- Alle Radiatoren, Handtuchheizkörper etc. mit Heizkostenverteiler HCAe2 ausstatten (x = Mitte / y = 75%)
- Kq- (Leistung in kW) und Kc- (Trägheit Messfühler) Werte der Heizkörper ermitteln (Liste v. EM oder "Heizkörperbestimmung" per Diensleister)
- Gateway mit IoT-SIM-Karte für ftp-Server parametrieren und an funkgünstiger Stelle, z.B. (Fensterleibung Treppenhaus) außerhalb Handbereich (>2,50 Höhe) anbringen
- HKVs in Tabelle Heizkostenverteiler eintragen
- Heizkörper mit Kc- und Kq-Werten in Tabelle Heizkörper eintragen
- Mieter und -wechsel in Tabelle Mieter pflegen
- parseAndMoveCSVs.php regelmäßig z.B. per Cron ausführen (Theoretisch reicht einmal pro Jahr, besser aber jeden Monat)

Die Abrechnung funktioniert wie folgt:

1. HCAe2 senden ihre Daten an den Gateway
2. Gateway läd monatlich CSV-Datei hoch
3. parseAndMoveCSVs.php in "scripts" scannt das Verzeichnis nach CSV-Dateien, schreibt ihren Inhalt in die Datenbank und legt sie in Unterverzeichnisse nach Jahreszahl
4. Am Jahresende Gasrechnung eintragen
5. UI zeigt Übersicht und druckbare Einzelabrechnungen

Seit jahren hier einwandfrei laufend aber keine Garantie für nix! Kein Login, nicht öffentlich betreiben!

<sup>²Gateway defekt *und* EOL (2.2026), Integration Lobaro next</sup>

