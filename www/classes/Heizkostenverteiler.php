<?php
/*
Meters run up until reset every new year's day. They will hence show the total consumption
on the 31st of December. To be able to split between tenants during a year, one could simply
divide the cost by 365.

Alas, if an energy saving tenant followed a wasteful one during mid-year, they would pay part
of the excessive heating. Worse, if a tenant moved in just for summer, they would sitll be charged
almost half of the year's total cost.

Unless mititgated mathematically, this must have been the case with legacy evaporation meters read 
only once a year. Today's meters store (and transmit) readings monthly, so calculations can be 
based on actual consumption.

For the actual consumption, we must subtract that of the preceeding month, though not in January.
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
        $this->Messergebnis_Haus        = $this->getMeteredData( $this->Abrechnungsjahr ); # 0000-00-00 give us full year
        $this->Messergebnis_HausD       = number_format( $this->Messergebnis_Haus, 2, ',', '.');
        $this->Preis_pro_Messwert       = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
        $this->Preis_pro_MesswertD      = number_format($this->Preis_pro_Messwert, 2, ',', '.');
    }
    
    public function getMeteredData( $year, $movedIn = '0000-00-00', $movedOut = '0000-00-00', $Whg_ID = '%', $zaehlerID = '%' ){ # Jahreswerte pro Wohnung oder Zähler 
        # give us back all units used this year between the dates
        # will return entire building if no tenant given
        
        $tsMovedIn  = strtotime( $movedIn );
        $tsMovedOut = strtotime( $movedOut );
        $partMonthConsumption = 0;

        # set all earlier and later dates to first and last of this year
        if( substr($movedIn,0,4)  != $year || $movedIn == '0000-00-00' ){
            $movedIn  = $year . '-01-01';
        } 
        if( substr($movedOut,0,4) != $year || $movedOut == '0000-00-00' ){
            $movedOut = $year . '-12-31';
        }

        # must we split within a month?
        if( date('j',$tsMovedIn) != 1 ){ # moved in not on the first?
            $days  = $this->daysInMonth( $movedIn ) - date('j', $tsMovedIn) + 1; # +1 because move-in-day counts
            $month = date('n',$tsMovedIn);
            $partMonthConsumption += $this->getUnitsForPartOfAMonth( $month, $days, $Whg_ID );
            # done, set movedIn to next for month further calculation
            $movedIn = date('Y-m-', strtotime( $movedIn . ' +1 month' )). '01'; 
            if( substr($movedIn,0,4) != $year ){
                return $partMonthConsumption; # they moved in in December, we are done here
            }
        }
        if( date('j',$tsMovedOut) != $this->daysInMonth( $movedOut ) ){ # moved out not on the last?
            $days  = date('j',$tsMovedOut);
            $month = date('n',$tsMovedOut);
            $partMonthConsumption += $this->getUnitsForPartOfAMonth( $month, $days, $Whg_ID );
            # done, set movedOut to previous month
            $movedOut = date('Y-m-', strtotime( $movedOut . ' -1 month' ) ) . $this->daysInMonth( $movedOut ); 
            if( substr($movedOut,0,4) != $year ){
                return $partMonthConsumption; # out in January, done
            }
        }

        # send all else to getConsumption()
        $straightMonthsConsumption = $this->getConsumption( $movedIn, $movedOut, $Whg_ID, $zaehlerID );

        # if not already done above, return sum
        return $straightMonthsConsumption + $partMonthConsumption;
    }

    public function getConsumption( $movedIn = '0000-00-00', $movedOut = '0000-00-00', $Whg_ID = '%', $zaehlerID = '%' ){
        /*
        - will return total if no meter or apartment given
        - one reading per month, calculate sub-month if necessary 
        - values of heat cost allocators are mathematically corrected by radiator- and meter-characteristics
        */
        $sql = <<<SQL
                SELECT 
                (
                    SELECT SUM(val) FROM (
                        SELECT SUM( Nettowert * h.Kq * h.Kc / $this->efq ) val /* Wert x Leistung x Trägheit : Basisempfindlichkeit */
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

    public function getUnitsForPartOfAMonth( $month, $days, $Whg_ID ){
        # get full month's consumption (what if we don't have the data?)
        $monthFirst  = $this->Abrechnungsjahr . '-' . $month . '-01';
        $monthLast   = $this->Abrechnungsjahr . '-' . $month . '-' . $this->daysInMonth( $month );
        $monthTotal  = $this->getConsumption( $monthFirst, $monthLast, $Whg_ID );
        $unitsPerDay = $monthTotal / $this->daysInMonth( $month );
        return $unitsPerDay * $days;
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
