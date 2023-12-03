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
        $this->preisProMesswert = $gas->PreisHeizung70Prozent / $this->totalMeteredConsumption($gas->Abrechnungsjahr);
    }

    public function totalMeteredConsumption($year){
        # How much for all allocators combined for a year? 
        # As meters keep counting, use each one's last (highest) reading and subtract last year's.
        $sql = <<<SQL
SELECT 
(
    SELECT SUM(w) FROM (
        SELECT MAX(Wert) w FROM Messwerte m
        LEFT JOIN Zaehler z ON z.ID = m.Zaehler_ID
        WHERE YEAR(Zeitpunkt) = $year
        GROUP BY Zaehler_ID
    ) totalMeteredThisYear
) - /* minus */
(
    SELECT SUM(w) FROM (
        SELECT MAX(Wert) w FROM Messwerte m
        LEFT JOIN Zaehler z ON z.ID = m.Zaehler_ID
        WHERE YEAR(Zeitpunkt) < $year
        GROUP BY Zaehler_ID
    ) totalMeteredEarlier
) thisYearsConsumption
SQL;

        $res = $this->conn->query($sql);
        $consumption = mysqli_fetch_assoc( $res );
        return $consumption['thisYearsConsumption'];
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
        AND MONTH(mw.Zeitpunkt) BETWEEN $monthNumberStart AND $monthNumberEnd
        GROUP BY Wohnung, Monat";
        
        foreach ($this->conn->query( $sql ) as $index => $row) {
            $monatswerte[] = $row;
        }
        return $monatswerte;
    }

    public function meteredConsumption( $year, $apartment, $startDate, $endDate ){ # get allocated value for a flat for the year or a part of it
        if( substr( $startDate,0,4 < $year) ){ 
            $startDate  = $year . '-01-01';
        }
  
        $sql = "SELECT MAX(mw.Wert) - 
        ( /* deduct everything measured before start date */
            SELECT MAX(mwb.Wert) 
            FROM Mieter m
            LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID
            LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
            LEFT JOIN Messwerte mwb ON z.ID = mwb.Zaehler_ID
            WHERE mwb.Zeitpunkt < STR_TO_DATE('$startDate', '%Y-%m-%d %H:%i:%s')
        ) AS consumption
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID
        LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
        LEFT JOIN Messwerte mw ON z.ID = mw.Zaehler_ID
        WHERE YEAR(mw.Zeitpunkt) = $year
        AND MONTH(mw.Zeitpunkt) BETWEEN MONTH('$startDate') AND MONTH('$endDate')
        AND w.ID = $apartment";

        $res = $this->conn->query( $sql ); 
        $consumption = mysqli_fetch_assoc( $res );                       
        return $consumption['consumption'];
    }

    public function formatDate($date){
        $date = strtotime( $date . '00:00:00' );
        $date = date('d.m.Y', $date);
        return $date;
    }

    public function nf($n){
        return number_format($n, 16, ',', '.');
    }
    
}
