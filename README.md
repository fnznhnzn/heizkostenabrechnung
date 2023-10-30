Die Heizkostenabrechnung funktioniert in zwei Schritten:

a) Datenerfassung
1. Heizkostenverteiler an den Heizkörpern in der Dasselstraße senden ihre Daten an einen Gateway im Keller
2. Der Gateway läd jeden Monat eine CSV-Datei per FTP hierhin hoch
3. processCSVs.php in "scripts" scannt das Verzeichnis nach CSV-Dateien, schreibt ihren Inhalt in eine Datenbank und legt sie in Unterverzeichnisse nach Jahreszahl
4  Ist die Batterie eines Verteilers oder des Gateways erschöpft schickt das Script eine E-Mail

b) Abrechnung
Nach Ablauf eines Jahres kann man eine Website aufrufen, den Gas-Gesamtverbrauch eintragen und für alle Mieter eine Heizkostenabrechnung erzeugen.

further reading: https://konrad.km-it.de/index.php/Heizkostenverteilung
