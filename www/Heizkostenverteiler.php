<?php

class Heizkostenverteiler{
    public $conn;
    public $Abrechnungsjahr;
    private Gasrechnung $gas;

    public function __construct(Gasrechnung $gas){
        # check input
        $options = array('options'=>array('min_range'=>2023));
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
        if(!$this->Abrechnungsjahr >= 2023){ echo 'give us y=2023 or later'; die(); }
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        $this->gas = $gas;
    }

    public function summeAllerZaehlerwerte(){
        # Werte aller Zähler zusammen
        $sql = "SELECT Zaehler_ID, ( MAX(m.Wert) * h.Kq * h.Kc / 2.288 ) w
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
        LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        WHERE YEAR(Zeitpunkt) = '" . $this->Abrechnungsjahr . "'
        GROUP BY Zaehler_ID
        ORDER BY Zaehler_ID";

        $gesamtSumme = 0;
        foreach ($this->conn->query( $sql ) as $index => $row) {
            $gesamtSumme += $row['w'];
        }

        # Zählerwerte aus Vorjahr
        $sql = "SELECT Zaehler_ID, ( MAX(m.Wert) * h.Kq * h.Kc / 2.288 ) w
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
        LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        WHERE YEAR(Zeitpunkt) = '" . ($this->Abrechnungsjahr - 1) . "'
        GROUP BY Zaehler_ID
        ORDER BY Zaehler_ID";

        $vorjahresSumme = 0;
        foreach ($this->conn->query( $sql ) as $index => $row) {
            $vorjahresSumme += $row['w'];
        }

        # Gesamt Abrechungsjahr - Gesamt Vorjahr = Werte für laufendes Jahr
        return $gesamtSumme - $vorjahresSumme;
    }

    public function einzelneZaehlerwerte(){
        # Gesamtzählerwerte pro Wohnung ermitteln - Achtung: Zähler zählen immer weiter, deshalb die letzen Werte aus dem Vorjahr abziehen! 
        $sql = "SELECT MAX(Wert) AS whgTotal, Nachname
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        LEFT JOIN Mieter mi ON w.ID = mi.Whg_ID
        WHERE YEAR(Zeitpunkt) = '" . $this->Abrechnungsjahr . "'
        GROUP BY w.ID";

        # hier die Werte in ein Array speichern, die Vorjahreswerte auf gleiche Weise ermitteln und dann abziehen
        foreach ($this->conn->query( $sql ) as $index => $row) {
            $messwerteMinusVorperiode[] = $row['whgTotal'];
        }

        # Werte aus dem Vorjahr ermitteln und abziehen, damit ein Jahresverbrauch herauskommt
        $sql2 = "SELECT MAX(Wert) AS whgTotal, Nachname
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        LEFT JOIN Mieter mi ON w.ID = mi.Whg_ID
        WHERE YEAR(Zeitpunkt) = " . ($this->Abrechnungsjahr-1) . "
        GROUP BY w.ID";

        $i=0;
        foreach ($this->conn->query( $sql2 ) as $index => $row) {
            $messwerteMinusVorperiode[$i] -= $row['whgTotal'];
            $i++;
        }
        return $messwerteMinusVorperiode;
    }

    public function zaehlerwerteGesamtProWohnung(){
        $sql = "SELECT MAX(Wert) AS whgTotal, Nachname
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        LEFT JOIN Mieter mi ON w.ID = mi.Whg_ID
        WHERE YEAR(Zeitpunkt) = '" . $this->Abrechnungsjahr . "'
        GROUP BY w.ID";
        return $sql;
    }
    
}