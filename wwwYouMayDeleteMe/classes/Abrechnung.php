<?php

class Abrechnung{
    public $conn;
    public $Abrechnungsjahr;
    public $Gesamtflaeche;
    
    public function __construct(){
        $options = array('options'=>array('min_range'=>2023));
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
        
        # total area
        $sql = "SELECT SUM(qm) FROM Wohnungen";
        $res = $this->conn->query($sql);
        $row = $res->fetch_assoc();
        $this->Gesamtflaeche = $row['SUM(qm)'];
    }

    public function getBillReceivers(){ # who gets a bill?
        $sql = "SELECT *, Einzug, Auszug
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID 
        WHERE Auszug = '0000-00-00' /* either moved (in and) out this year or still lives here */
        OR YEAR(Auszug) = $this->Abrechnungsjahr"; 

        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){ # adjust in and out date for calculation
            if( substr($row['Einzug'],0,4) < $this->Abrechnungsjahr ){
                $row['Abrechnungsbeginn'] = $this->Abrechnungsjahr . '-01-01';
            } else {
                $row['Abrechnungsbedinn'] = $row['Einzug'];
            }
            if( $row['Auszug'] === '0000-00-00' ){
                $row['Abrechnungsende'] = $this->Abrechnungsjahr . '-12-31';
            } else {
                $row['Abrechnungsende'] = $row['Auszug'];
            }
            $billReceivers[] = $row;
        }

        return $billReceivers;
    }

    public function loopThroughParties(){

    }


}