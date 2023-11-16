<?php

class Heizkostenverteiler extends Base {

    public $Preis_Heizung;
    public $Preis_HeizungE;
    public $Preis_Heizung_70Prozent;
    public $Preis_Heizung_70ProzentE;
    public $Messergebnis_Haus;
    public $Preis_pro_Messwert;

    public function __construct( $Preis_Warmwasser ){
        parent::__construct();
        $this->Preis_Heizung = $this->Rechnungsbetrag - $Preis_Warmwasser;
        $this->Preis_HeizungE = $this->euro( $this->Preis_Heizung );
        $this->Preis_Heizung_70Prozent = $this->Preis_Heizung * 0.7;
        $this->Preis_Heizung_70ProzentE = $this->euro( $this->Preis_Heizung_70Prozent );
        $this->Messergebnis_Haus = $this->getMeteredData( $this->Abrechnungsjahr );
        $this->Preis_pro_Messwert = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
    }

    public function meteredHeatingCostPerFlat($year, $Whg_ID, $start, $end){
        return getMeteredData( $year, $Whg_ID, $start, $end ) * $this->Preis_pro_Messwert;
    }
    
    public function getMeteredData( $year, $Whg_ID = "'%'", $movedIn = "'2023-01-01'", $movedOut = "'2023-12-31'" ){   
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
                            AND MONTH(Zeitpunkt) BETWEEN MONTH(STR_TO_DATE($movedIn, '%Y-%m-%d')) AND MONTH(STR_TO_DATE($movedOut, '%Y-%m-%d'))
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