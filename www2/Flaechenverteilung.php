<?php

public $Preis_Heizung_30_Prozent;
public $Preis_pro_Quadratmeter;

class Flaechenverteilung{

    public function __construct(){
        $this->$Preis_Heizung_30_Prozent = $Heizkostenverteilung->PreisHeizung * 0.3;
        $Preis_pro_Quadratmeter = $this->Preis_Heizung_30_Prozent / $Base->Gesamtwohnflaeche;
    }

    public function calculatedHeatingCostPerFlat( $year, $Whg_ID, $start, $end ){
        return getArea( $Whg_ID ) * $this->Preis_pro_Quadratmeter;
    }

    public function getArea( $Whg_ID ){
        $sql = <<<SQL
                    SELECT qm FROM Wohnungen WHERE Whg_ID = $Whg_ID
                SQL;

        $res = $this->conn->query($sql);
        $qm = mysqli_fetch_assoc( $res );
        return $qm['qm'];
    }

}