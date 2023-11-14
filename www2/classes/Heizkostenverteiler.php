<?php

class Heizkostenverteiler extends Base {
    
    public $Preis_Heizung;
    public $Preis_Heizung_70Prozent;
    public $Messergebnnis_Haus;
    public $Preis_pro_Messwert;

    public function __construct(){
        parent::__construct();

        $this->Preis_Heizung = $Gas->Rechnungsbetrag - $Warmwasser->PreisWarmwasser;
        $this->Preis_Heizung_70Prozent = $this->Preis_Heizung * 0.7;
        $this->Messergebnis_Haus = totalMeteredConsumption( $this->Abrechnungsjahr );
        $this->Preis_pro_Messwert = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
    }

    public function meteredHeatingCostPerFlat($year, $Whg_ID, $start, $end){
        return getMeteredData( $year, $Whg_ID, $start, $end ) * $this->Preis_pro_Messwert;
    }
    
    public function getMeteredData( $year, $Whg_ID = '%', $firstMonth = 1, $lastMonth = 12 ){
        
        # meters keep counting, so take each one's last (=highest) reading and subtract last year's.
        # will return year's total if only that is given
        $sql = <<<SQL
                    SELECT 
                    (
                        SELECT SUM(w) FROM (
                            SELECT MAX(Wert) w FROM Wohnungen w
                            LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
                            LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
                            LEFT JOIN Mieter mi ON w.ID = mi.Whg_ID
                            WHERE w.ID LIKE $Whg_ID
                            AND MONTH(Zeitpunkt) BETWEEN $firstMonth AND $lastMonth
                            GROUP BY Zaehler_ID
                        ) totalThisYearsMeters
                    ) - 
                    (
                        SELECT SUM(w) FROM (
                            SELECT MAX(Wert) w FROM Wohnungen w
                            LEFT JOIN Zaehler z ON w.ID = z.Whg_ID
                            LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
                            LEFT JOIN Mieter mi ON w.ID = mi.Whg_ID
                            WHERE w.ID LIKE $Whg_ID
                            AND YEAR(Zeitpunkt) < $year
                            GROUP BY Zaehler_ID
                        ) totalMetersBefore
                    ) consumption
                SQL;
            
        $res = $this->conn->query($sql);
        $consumption = mysqli_fetch_assoc( $res );
        return $consumption['consumption'];
    }

}