<?php
/*
Meters genrally run up until reset every new year's day. Hence the total units are transmitted
every 31st of December. 

To be able to fairly split between tenants even during a month, we use the readings 
transmitted monthly and deduct the preceeding month's value to get the actual consumption, 
as done by script "getNetValues.php" and stored in column 
"Nettowert".

More intel on Engelmann meters and gateway:  https://konrad.km-it.de/index.php/Engelmann
For the concept of heat cost allocation see: https://konrad.km-it.de/index.php/Heizkostenverteilung

Further notes:
To save battery power, meters pause radio transmission in summer. If someone still used their
radiators it would show up in the first reading in fall. As tenants tend not to do that it
seems acceptable to account for summer with zero consumption.

If however one really wanted to exactly attibute possible summer use to tenants possibly changing 
during summer, one could "retro-"process the historic data stored in the devices up to 15 month back.

Fun fact: 
With legacy evaporation meters, unless mitigated mathematically, a tenant moving in just for 
summer was charged evenly for the whole year's heating cost. Worse, an energy saving one would 
pay for their wasteful predecessor.
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
        $this->Messergebnis_Haus        = $this->getMeteredData( $this->Abrechnungsjahr, $this->Abrechnungsjahr.'-01-01', $this->Abrechnungsjahr.'-12-31' );
        $this->Messergebnis_HausD       = number_format( $this->Messergebnis_Haus, 2, ',', '.');
        $this->Preis_pro_Messwert       = $this->Preis_Heizung_70Prozent / $this->Messergebnis_Haus;
        $this->Preis_pro_MesswertD      = number_format($this->Preis_pro_Messwert, 2, ',', '.');
    }
    
    public function getMeteredData( $year, $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%', $nachname = '%' ){  
        # returns units for whole building or each tenant
        
        # 1. Moved in or out before or after this year? Adjust dates
        if( date('Y', strtotime($movedIn) ) < $year ){
            $movedIn  = $year . '-01-01';
        } 
        if( date('Y', strtotime($movedOut) ) > $year ){
            $movedOut = $year . '-12-31';
        }

        # 2. Loop through months
        $firstMonth = date('n', strtotime($movedIn) );
        $lastMonth  = date('n', strtotime($movedOut) );
        $units = 0;
        $i=0;
        for($i=$firstMonth; $i<=$lastMonth; $i++){
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $i, $year);
            $i < 10 ? $month = '0' . $i : $month = $i;

            # Which days of this month must we account for?
            if( date('n', strtotime($movedIn)) == $month ){             # Moved in this month?
                $accountFrom = $movedIn;                                # Calculate from that date
            } else {                                                    # Moved in before?
                $accountFrom = $year . '-' . $month . '-01';            # Calculate from 1st
            }

            if( date('n', strtotime($movedOut)) == $month ){            # Moved out this month? Use the date
                $accountUntil = $movedOut;                              # Calculate until that date
            } else {                                                    # Moved out after?
                $accountUntil = $year.'-'.$month.'-'.$daysInMonth;      # Calculate until 31st (or 30th, 29th, 28th)
            }

            $daysToAccountFor = date('j', strtotime($accountUntil) ) - date('j', strtotime($accountFrom) ) + 1;
            $units += $this->getUnitsForPartOfAMonth( $month, $daysToAccountFor, $Whg_ID );
        }
        return $units;
    }

    public function getUnitsForPartOfAMonth( $month, $days, $Whg_ID ){
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $this->Abrechnungsjahr);
        $monthFirst    = $this->Abrechnungsjahr . '-' . $month . '-01';
        $monthLast     = $this->Abrechnungsjahr . '-' . $month . '-' . $daysInMonth;
        $monthTotal    = $this->getUnitsForFullMonths( $monthFirst, $monthLast, $Whg_ID );
        $unitsPerDay   = $monthTotal / $daysInMonth;
        $units = $unitsPerDay * $days;
        #echo "and the units for $month in flat $Whg_ID are $units<br>";
        return $units;
    }

    public function getUnitsForFullMonths( $movedIn, $movedOut, $Whg_ID = '%', $zaehlerID = '%' ){
        /*
        - will return total if no meter or apartment given
        - one reading per month, calculate sub-month if necessary 
        - values of heat cost allocators are mathematically corrected by radiator- and meter-characteristics
        */
        $sql = <<<SQL
                SELECT 
                    SUM( Nettowert * h.Kq * h.Kc / 2.288 ) units -- Wert x Leistung x Trägheit : Basisempfindlichkeit */
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
                    DATE(Zeitpunkt) BETWEEN '$movedIn' AND '$movedOut'
            SQL;
            
            $res = $this->conn->query($sql);
            $units = mysqli_fetch_assoc( $res );
        # what if we don't have data?
        if( $units['units'] === null ){ return 0; }
        return $units['units'];
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
