<?php

class Abrechnung{
    public $conn;
    public $Abrechnungsjahr;

    public function __construct(){
        $options = array('options'=>array('min_range'=>2023));
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
    }

    public function getBillReceivers(){ # who gets a bill?
        $sql = "SELECT *, 
        Einzug, Auszug
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID 
        WHERE YEAR(Einzug) = $this->Abrechnungsjahr
        OR YEAR(Auszug) = $this->Abrechnungsjahr
        OR Auszug = '0000-00-00'";

        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){
            if( $row['Einzug'] === '0000-00-00' ){
                $row['Einzug'] = $this->Abrechnungsjahr . '-01-01';
            }
            if( $row['Auszug'] === '0000-00-00' ){
                $row['Auszug'] = $this->Abrechnungsjahr . '-12-31';
            }
            $billReceivers[] = $row;
        }

        return $billReceivers;
    }

    public function loopThroughParties(){

    }


}