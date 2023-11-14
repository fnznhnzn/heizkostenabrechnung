<?php

class Flaechenverteilung extends Base {
    
    public $PreisHeizung30Prozent;
    public $Preis_pro_Quadratmeter;

    public function __construct( $PreisHeizung ){
        parent::__construct();
        $this->PreisHeizung30Prozent = $PreisHeizung * 0.3;
        $Preis_pro_Quadratmeter = $this->PreisHeizung30Prozent / $this->Gesamtwohnflaeche;
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