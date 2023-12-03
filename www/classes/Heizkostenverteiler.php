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
        $this->Messergebnis_Haus = $this->getMeteredData( $this->Abrechnungsjahr, '0000-00-00', '0000-00-00' ); # 0000-00-00 give us full year
        $this->Preis_pro_Messwert = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
    }
    
    public function getMeteredData( $year, $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%' ){  
        if( substr($movedIn,0,4) != $year ){
            $movedIn = $year . '-01-01';
        } 
        if( substr($movedOut,0,4) != $year ){
            $movedOut = $year . '-12-31';
        }
        # meters keep counting, so take each one's last (=highest) reading and subtract last year's.
        # will return total if no apartment given
        # values of heat cost allocators are mathematically corrected by radiator characteristics
        $sql = <<<SQL
                    SELECT 
                    (
                        SELECT SUM(val) FROM (
                            SELECT MAX( Wert * h.Kq * h.Kc / 1.181 ) val /* Wert x Leistung x Trägheit : Basisempfindlichkeit */
                            FROM Wohnungen w
                            LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
                            LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
                            LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
                            LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
                            WHERE w.ID LIKE '$Whg_ID'
                            AND MONTH(Zeitpunkt) 
                                BETWEEN MONTH(STR_TO_DATE('$movedIn', '%Y-%m-%d')) 
                                AND MONTH(STR_TO_DATE('$movedOut', '%Y-%m-%d'))
                            AND Zaehler_ID LIKE '$zaehlerID'
                            GROUP BY Zaehler_ID
                        ) totalThisYearsMeters
                    ) - 
                    (
                        SELECT SUM(val) FROM (
                            SELECT MAX( Wert * h.Kq * h.Kc / 1.181 ) val 
                            FROM Wohnungen w
                            LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
                            LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
                            LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
                            LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
                            WHERE w.ID LIKE '$Whg_ID'
                            AND YEAR(Zeitpunkt) < $year
                            AND Zaehler_ID LIKE '$zaehlerID'
                            GROUP BY Zaehler_ID
                        ) totalMetersBefore
                    ) consumption
                SQL;
            
        $res = $this->conn->query($sql);
        $consumption = mysqli_fetch_assoc( $res );
        return $consumption['consumption'];
    }

    public function getMeteredDataByMonth($movedIn, $movedOut, $Whg_ID){
        $sql = <<<SQL
            SELECT 
                CONCAT( SUBSTRING( MONTHNAME( Zeitpunkt ), 1 ,3 ), ' ', YEAR( Zeitpunkt ) ) d, 
                MAX( Wert * h.Kq * h.Kc / 1.181 ) v
            FROM Wohnungen w
            LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
            LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
            LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
            LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
            WHERE w.ID LIKE '$Whg_ID'
            AND MONTH(Zeitpunkt) 
                BETWEEN MONTH(STR_TO_DATE('$movedIn', '%Y-%m-%d')) 
                AND MONTH(STR_TO_DATE('$movedOut', '%Y-%m-%d'))
            GROUP BY MONTH(Zeitpunkt)
        SQL;

        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){
            $resArray[] = $row;
        }
        return $resArray;
    }

    private function parkedSQL(){
        $sql = <<<SQL
            SELECT 
                CONCAT(SUBSTRING(MONTHNAME(Zeitpunkt),1,3),' ',YEAR(Zeitpunkt)) d, 
                /*MAX( Wert * h.Kq * h.Kc / 1.181 ) v*/
                z.Raum,
                z.ID,
                Wert * h.Kq * h.Kc / 1.181 v
            FROM Wohnungen w
            LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
            LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
            LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
            LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
            WHERE w.ID LIKE '1'
            AND MONTH(Zeitpunkt) 
                BETWEEN MONTH(STR_TO_DATE('2023-01-01', '%Y-%m-%d')) 
                AND MONTH(STR_TO_DATE('2023-12-31', '%Y-%m-%d'))
            GROUP BY z.ID, MONTH(Zeitpunkt)
            ORDER BY z.Raum, z.ID, Zeitpunkt
            SQL;

        # this one subtracts the previous value of each line as if the meter started fresh after each mesurement
        $sql = <<<SQL
                SELECT 
                Zaehler_ID,
                Zeitpunkt,
                Wert,
                Wert - LAG(Wert) OVER ( ORDER BY m.Zeitpunkt) Entwicklung
                FROM Wohnungen w
                LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
                LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
                LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
                LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
                WHERE m.Zaehler_ID = 21116054
        SQL;
    }
                
}         