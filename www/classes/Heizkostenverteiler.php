<?php

class Heizkostenverteiler extends Base {

    public $Preis_Heizung;
    public $Preis_HeizungE;
    public $Preis_Heizung_70Prozent;
    public $Preis_Heizung_70ProzentE;
    public $Messergebnis_Haus;
    public $Messergebnis_HausD;
    public $Preis_pro_Messwert;
    public $Preis_pro_MesswertD;
    public $efq = 2.288; # Engelmann-Fühler-Quotient, 1-Fühler 1.181, 2-Fühler 2.288, Fernfühler 1.097

    public function __construct( $Preis_Warmwasser ){
        parent::__construct();
        $this->Preis_Heizung = $this->Rechnungsbetrag - $Preis_Warmwasser;
        $this->Preis_HeizungE = $this->euro( $this->Preis_Heizung );
        $this->Preis_Heizung_70Prozent = $this->Preis_Heizung * 0.7;
        $this->Preis_Heizung_70ProzentE = $this->euro( $this->Preis_Heizung_70Prozent );
        $this->Messergebnis_Haus = $this->getMeteredData( $this->Abrechnungsjahr, '0000-00-00', '0000-00-00' ); # 0000-00-00 give us full year
        $this->Messergebnis_HausD = number_format( $this->Messergebnis_Haus, 2, ',', '.');
        $this->Preis_pro_Messwert = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
        $this->Preis_pro_MesswertD = number_format($this->Preis_pro_Messwert, 2, ',', '.');
    }
    
    public function getMeteredData( $year, $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%' ){ # Jahreswerte pro Wohnung oder Zähler 
        $tsMovedIn  = strtotime( $movedIn );
        $tsMovedOut = strtotime( $movedOut );
        /*
        Problem: Bei untermonatigem Mieterwechsel wird der Verbrauch beiden Mietern für den gesamten Monat berechnet.
        Lösung: Verbrauch tagesgenau abgrenzen
        Aufgabe: Verbrauch pro Tag berechnen, obwohl nur Monatswerte vorliegen
        Prozedur:
        - Ein oder Auszug untermonatig?
        - Wenn ja:
            1. jeweils Verbrauch für den Monat berechnen
            2. Verbrauch pro Tag = (Verbrauch des Monats / Anzahl Tage im Monat)
                * Sommermonate werden nicht gemessen, Nullwerte abfangen
            3. Verbrauch = (Verbrauch pro Tag * Anzahl Tage anwesend)
            4. nur restliche Monate voll berechnen
        */

        # Wenn Einzug oder Auszug nicht im Abrechnungsjahr, dann auf 1.1. bzw. 31.12. des Abrechnungsjahres setzen
        if( date('Y',$tsMovedIn) != $year ){
            $movedIn = $year . '-01-01';
        } 
        if( date('Y',$tsMovedOut) != $year ){
            $movedOut = $year . '-12-31';
        }

        # Einzug untermonatig?
        if( date('j',$tsMovedIn) != 1 ){
            $days = $this->daysInMonth( $movedIn ) - date('j',$tsMovedIn);
            # Anteiligen Verbrauch für den Monat berechnen
            $this->getUnitsByDaysOfAMonth( date('n',$tsMovedIn), $days, $Whg_ID );
        }

        # Auszug untermonatig?
        if( date('j',$tsMovedOut) != $this->daysInMonth( $movedOut ) ){
            $days = date('j',$tsMovedOut);
            # Anteiligen Verbrauch für den Monat berechnen
            $this->getUnitsByDaysOfAMonth( date('n',$tsMovedOut), $days, $Whg_ID );
        }

        # wenn untermonatiger Ein- oder Auszug, die verbleibenden Monate normal berechnen

        # will return total if no apartment given
        # values of heat cost allocators are mathematically corrected by radiator- and meter-characteristics
        $sql = <<<SQL
                    SELECT 
                    (
                        SELECT SUM(val) FROM (
                            SELECT MAX( Wert * h.Kq * h.Kc / $this->efq ) val /* Wert x Leistung x Trägheit : Basisempfindlichkeit */
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
                    ) consumption
                SQL;
            
        $res = $this->conn->query($sql);
        $consumption = mysqli_fetch_assoc( $res );
        if( $consumption['consumption'] === null ){ return 0; }
        return round( $consumption['consumption'], 2 );
    }

    public function getUnitsByDaysOfAMonth( $month, $days, $Whg_ID ){

    }

    public function getMeteredDataByMonth($movedIn, $movedOut, $Whg_ID){ # momentan nur von public/monatswerte.php genutzt
        $sql = <<<SQL
            SELECT 
                CONCAT( SUBSTRING( MONTHNAME( Zeitpunkt ), 1 ,3 ), ' ', YEAR( Zeitpunkt ) ) d, 
                MAX( Wert * h.Kq * h.Kc / $this->efq ) v
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

    public function getLastMeteredValue( $zaehlerID, $lastOnly = false ){
        # subtracts every previous value (as if meter started fresh)
        $sql = <<<SQL
                SELECT 
                Zaehler_ID,
                Zeitpunkt,
                Wert * h.Kq * h.Kc / $this->efq AS Wert,
                ( Wert - LAG(Wert) OVER ( ORDER BY m.Zeitpunkt) ) * h.Kq * h.Kc / $this->efq AS LetzterWert
                FROM Wohnungen w
                LEFT JOIN Zaehler z     ON w.ID = z.Whg_ID
                LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
                LEFT JOIN Messwerte m   ON z.ID = m.Zaehler_ID
                LEFT JOIN Mieter mi     ON w.ID = mi.Whg_ID
                WHERE m.Zaehler_ID = '$zaehlerID'
        SQL;
        if( $lastOnly === true ){ 
            $sql = $sql . 'ORDER BY Zeitpunkt DESC LIMIT 1'; 
        }
        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){
            $resArray[] = $row;
        }
        return $resArray;
    }

    public function getRawData( $zaehlerID ){
        $sql = <<<SQL
                SELECT Zeitpunkt d, Wert v, Wert * h.Kq * h.Kc / $this->efq cv
                FROM Messwerte
                JOIN Zaehler z ON Messwerte.Zaehler_ID = z.ID
                JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
                WHERE Zaehler_ID = '$zaehlerID'
                ORDER BY Zeitpunkt
        SQL;
        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){
            $resArray[] = $row;
        }
        return $resArray;            
    } 
    
}
