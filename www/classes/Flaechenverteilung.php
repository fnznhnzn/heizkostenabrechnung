<?php

class Flaechenverteilung extends Base {
    
    public $PreisHeizung30Prozent;
    public $PreisHeizung30ProzentE;
    public $Preis_pro_Quadratmeter;
    public $Preis_pro_QuadratmeterE;
    public $Preis_pro_QuadratmeterD;

    public function __construct( $PreisHeizung ){
        parent::__construct();
        $this->PreisHeizung30Prozent = $PreisHeizung * 0.3;
        $this->PreisHeizung30ProzentE = $this->euro( $this->PreisHeizung30Prozent);
        $this->Preis_pro_Quadratmeter = $this->PreisHeizung30Prozent / $this->Gesamtwohnflaeche;
        $this->Preis_pro_QuadratmeterE = $this->euro($this->Preis_pro_Quadratmeter);
        $this->Preis_pro_QuadratmeterD = number_format($this->Preis_pro_Quadratmeter, 10, ',', '.');
    }

    public function calculatedHeatingCostPerFlat( $year, $Whg_ID, $movedIn, $movedOut ){
        $kostenNachFlaeche = $this->getArea( $Whg_ID ) * $this->Preis_pro_Quadratmeter;
        $daysStayed = $this->getDaysStayed( $movedIn, $movedOut );
        $proportionateCost = $kostenNachFlaeche / $this->daysInYear( $year ) * $daysStayed;
        return $proportionateCost;
    }

    public function getArea( $Whg_ID ){
        $sql = <<<SQL
                    SELECT qm FROM Wohnungen WHERE ID = $Whg_ID
                SQL;

        $res = $this->conn->query($sql);
        $qm = mysqli_fetch_assoc( $res );
        return $qm['qm'];
    }

    public function getDaysStayed( $movedIn, $movedOut ){
        if( substr($movedIn,0,4) != $this->Abrechnungsjahr ){ # if out of scope, set to first and/or last of the year
            $movedIn = $year . '-01-01';
        } 
        if( substr($movedOut,0,4) != $this->Abrechnungsjahr ){
            $movedOut = $year . '-12-31';
        }
        $in  = strtotime($movedIn);
        $out = strtotime($movedOut);
        $datediff = $out - $in;
        $daysStayed = round($datediff / (60 * 60 * 24) ) + 1; # first day counts, so + 1
        return $daysStayed;
    }

}
