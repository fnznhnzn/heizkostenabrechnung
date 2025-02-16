<?php
/*
Meters run up until reset every new year's day. Hence they will show the total consumption
on the 31st of December. To be able to split between tenants during a year, one could simply
divide that by 365.

Alas, if an energy saving tenant followed a wasteful one during mid-year, they would pay part
of the excessive heating. Worse, if a tenant moved in just for summer, they would sitll be charged
almost half of the year's total cost.

Unless mititgated mathematically, this must have been the case with legacy evaporation meters read 
only once a year. Today's meters store (and transmit) readings monthly, so calculations can be 
based on actual consumption.

For the actual consumption, we must subtract the preceeding month, though not in January.
The auxilary script "getNetValues.php" stores the net values in column "Nettowert". 

To save battery power, meters pause radio transmission in summer. If someone still used their
radiators it would show up in the first reading in fall. As tenants tend not to do that it
seems acceptable for now to account for summer with zero consumption.

If however one wanted to exactly attibute possible summer use to tenants possibly changing during 
summer, one could "retro-"process the historic data stored in the devices up to 15 month back.
*/
class Heizkostenverteiler extends Base {

    public $Preis_Heizung;
    public $Preis_HeizungE;
    public $Preis_Heizung_70Prozent;
    public $Preis_Heizung_70ProzentE;
    public $Messergebnis_Haus;
    public $Messergebnis_HausD;
    public $Preis_pro_Messwert;
    public $Preis_pro_MesswertD;
    public $efq = 2.288; # Engelmann-Fühler-Quotient (1-Fühler = 1.181, 2-Fühler = 2.288, Fernfühler = 1.097)

    public function __construct( $Preis_Warmwasser ){
        parent::__construct();
        $this->Preis_Heizung            = $this->Rechnungsbetrag - $Preis_Warmwasser;
        $this->Preis_HeizungE           = $this->euro( $this->Preis_Heizung );
        $this->Preis_Heizung_70Prozent  = $this->Preis_Heizung * 0.7;
        $this->Preis_Heizung_70ProzentE = $this->euro( $this->Preis_Heizung_70Prozent );
        $this->Messergebnis_Haus        = $this->getMeteredData( $this->Abrechnungsjahr, $this->Abrechnungsjahr.'-01-01 00:00:00', $this->Abrechnungsjahr.'-12-31 23:59:59' );
        $this->Messergebnis_HausD       = number_format( $this->Messergebnis_Haus, 2, ',', '.');
        $this->Preis_pro_Messwert       = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
        $this->Preis_pro_MesswertD      = number_format($this->Preis_pro_Messwert, 2, ',', '.');
    }
    
    public function getMeteredData( $year, $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%' ){  
        # return units for each tenant or building total

        # Problem: Behandelt ungeraden Ein- und Auszug nur richtig, wenn im selben Monat 
        # wieder aus- bzw. eingezogen wird. Die Erkennung von ungeraden Ein- und Auszügen
        # in unterschiedlichen Monaten ist noch nicht implementiert. Wir müssen doch durch
        # jeden Monat iterieren und die Tage zählen, die der Mieter in diesem Monat da war.
        # Vorteil: Dann kann für alles die gleiche Funktion genutzt werden.

        # 1. schreibe alle Monate mit Anfang und Ende in ein Array
        # 2. bestimme und ergänze die Tage für jeden Monat
        # 3. schicke jeden Monat an getUnitsForPartOfAMonth()
        # 4. gebe die Summe zurück

        strlen($movedIn)  == 10 ? $movedIn  .= ' 00:00:00' : null; # tenant dates lack time
        strlen($movedOut) == 10 ? $movedOut .= ' 23:59:59' : null;
        # returns units used by tenant or building total
        $tsMovedIn  = strtotime( $movedIn );
        $tsMovedOut = strtotime( $movedOut );
        $partMonthsConsumption = 0;

        # set all earlier and later dates to first and last of this year
        if( substr($movedIn,0,4)  != $year || $movedIn == '0000-00-00' ){
            $movedIn  = $year . '-01-01 00:00:00';
        } 
        if( substr($movedOut,0,4) != $year || $movedOut == '0000-00-00' ){
            $movedOut = $year . '-12-31 23:59:59';
        }

        # need we split within a month?
        if( date('j',$tsMovedIn) != 1 ){ # didn't move in on the month's first?
            $days  = date('t', $tsMovedIn ) - date('j', $tsMovedIn) + 1; # +1 because move-in-day counts
            $month = date('n',$tsMovedIn);
            $partMonthsConsumption += $this->getUnitsForPartOfAMonth( $month, $days, $Whg_ID );
            # done, set movedIn to next for month further calculation
            $movedIn = date('Y-m-', strtotime( $movedIn . ' +1 month' )). '01'; 
            if( substr($movedIn,0,4) != $year ){
                return $partMonthsConsumption; # they moved in in December, we are done here
            }
        }
        if( date('j',$tsMovedOut) != date('t', $tsMovedOut ) ){ # didn't move out on month's last?
            $days  = date('j',$tsMovedOut);
            $month = date('n',$tsMovedOut);
            $partMonthsConsumption += $this->getUnitsForPartOfAMonth( $month, $days, $Whg_ID );
            # done, set movedOut to previous month
            $movedOut = date('Y-m-', strtotime( $movedOut . ' -1 month' ) ) . $this->daysInMonth( $movedOut ); 
            if( substr($movedOut,0,4) != $year ){
                return $partMonthsConsumption; # out in January, no need to go further
            }
        }

        # send all else to getUnitsForFullMonths()
        $fullMonthsConsumption = $this->getUnitsForFullMonths( $movedIn, $movedOut, $Whg_ID, $zaehlerID );

        # if not already done above, return sum
        return $fullMonthsConsumption + $partMonthsConsumption;
    }

    public function getUnitsForFullMonths( $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%' ){
        /*
        - will return total if no meter or apartment given
        - one reading per month, calculate sub-month if necessary 
        - values of heat cost allocators are mathematically corrected by radiator- and meter-characteristics
        */
        $sql = <<<SQL
                SELECT 
                    SUM( Nettowert * h.Kq * h.Kc / 2.288 ) consumption -- Wert x Leistung x Trägheit : Basisempfindlichkeit */
                FROM 
                    Wohnungen w
                LEFT JOIN 
                    Zaehler z     ON w.ID = z.Whg_ID
                LEFT JOIN 
                    Heizkoerper h ON h.ID = z.Heizkoerper_ID -- needed for Kq and Kc
                LEFT JOIN 
                    Messwerte m   ON z.ID = m.Zaehler_ID
                WHERE 
                    w.ID 
                LIKE 
                    '$Whg_ID'
                AND 
                    Zeitpunkt BETWEEN '$movedIn' AND '$movedOut'
            SQL;
            
        $res = $this->conn->query($sql);
        $consumption = mysqli_fetch_assoc( $res );
        if( $consumption['consumption'] === null ){ return 0; }
        return $consumption['consumption'];
    }

    public function getUnitsForPartOfAMonth( $month, $days, $Whg_ID ){
        # what if we don't have data?
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $this->Abrechnungsjahr);
        $monthFirst    = $this->Abrechnungsjahr . '-' . $month . '-01 00:00:00';
        $monthLast     = $this->Abrechnungsjahr . '-' . $month . '-' . $daysInMonth . ' 23:59:59';
        $monthTotal    = $this->getUnitsForFullMonths( $monthFirst, $monthLast, $Whg_ID );
        $unitsPerDay   = $monthTotal / $daysInMonth;
        $units = $unitsPerDay * $days;
        return $units;
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

    public function getLastMeteredValue( $zaehlerID, $lastOnly = false ){ # not used yet
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
