<?php

class Verteilung{
    public $conn;
    public $Abrechnungsjahr;
    public $preisProMesswert;
    private Gasrechnung $gas;

    public function __construct(Gasrechnung $gas){
        # check input
        $options = array('options'=>array('min_range'=>2023));
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
        if(!$this->Abrechnungsjahr >= 2023){ echo 'give us y=2023 or later'; die(); }
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        $this->gas = $gas;
        $this->preisProMesswert = $gas->PreisHeizung70Prozent / $this->summeAllerZaehlerwerte();  
    }

    public function summeAllerZaehlerwerte(){
        # Werte aller Zähler zusammen
        $sql = "SELECT Zaehler_ID, ( MAX(m.Wert) * h.Kq * h.Kc / 1.181 ) w
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
        $sql = "SELECT Zaehler_ID, ( MAX(m.Wert) * h.Kq * h.Kc / 1.181 ) w
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
        foreach ($this->conn->query( $sql ) as $index => $row){
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

    # what methods do we need, I ask!
    # monatswerteListe(): nicely list values by apartment and month for any part of the year 
    # totalMeteredConsumption(): sum up all metered values for the year (there is only one gas bill per year) summeAllerZaehlerwerte()
    # consumptionByMeter(): get measured value for an apartment for whole or part of a year
    # consumptionByArea(): get allocated cost by square meters for whole or part of a year
    public function monatswerteListe( $year, $monthNumberStart, $monthNumberEnd ){
        # this one subtracts the previous month with a self join, so we get actual consumption per month
        $sql = "SELECT w.ID Wohnung, YEAR(mw.Zeitpunkt) Jahr, MONTH(mw.Zeitpunkt) Monat,  MAX(mw.Wert) - MAX(mwb.Wert) Wert 
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID
        LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
        LEFT JOIN Messwerte mw ON z.ID = mw.Zaehler_ID
        INNER JOIN Messwerte mwb ON mw.Zaehler_ID = mwb.Zaehler_ID /* self join to get... */
            AND mwb.Zeitpunkt < mw.Zeitpunkt /* ...all preceding values of... */
            AND MONTH(mwb.Zeitpunkt) != MONTH(mw.Zeitpunkt) /* ...all months (before the current one) */
        WHERE YEAR(mw.Zeitpunkt) = $year
        AND MONTH(mw.Zeitpunkt) BETWEEN $start AND $end
        GROUP BY Wohnung, Monat";
        
        foreach ($this->conn->query( $sql ) as $index => $row) {
            $monatswerte[] = $row;
        }
        
        return $monatswerte;
    }

    public function consumptionByMeter( $year, $apartment, $monthNumberStart, $monthNumberEnd ){ # get allocated value for a flat for a year or a part of it
        if($monthNumberStart < 10 ){
            $monthNumberStart = '0' . $monthNumberStart;
        }
        $sql = "SELECT MAX(mw.Wert) - 
        (
            SELECT MAX(mwb.Wert) 
            FROM Mieter m
            LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID
            LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
            LEFT JOIN Messwerte mwb ON z.ID = mwb.Zaehler_ID
            WHERE STR_TO_DATE(mwb.Zeitpunkt, '%Y-%m-%d %H:%i:%s') < STR_TO_DATE('$year-$monthNumberStart-01 00:00:00', '%Y-%m-%d %H:%i:%s')
        ) AS consumption
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID
        LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
        LEFT JOIN Messwerte mw ON z.ID = mw.Zaehler_ID
        WHERE YEAR(mw.Zeitpunkt) = $year
        AND MONTH(mw.Zeitpunkt) BETWEEN $monthNumberStart AND $monthNumberEnd
        AND w.ID = $apartment";
    }

    public function consumptionByArea( $year, $apartment, $monthNumberStart, $monthNumberEnd ){

    }

    public function nf($n){
        return number_format($n, 16, ',', '.');
    }
    
}
