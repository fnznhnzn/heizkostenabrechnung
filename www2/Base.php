<?php  
  
public $conn;
public $Abrechnungsjahr;
public $Gesamtwohnflaeche;

class Base{

    public function __construct(){  
        # db
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        
        # year (from GET )
        $options = array('options'=>array('min_range'=>2023));
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
        if(!$this->Abrechnungsjahr >= 2023){ echo 'give us y=2023 or later'; die(); }
        
        # total heatet area
        $res = mysqli_query($this->conn, "SELECT SUM(qm) AS Gesamtwohnflaeche FROM Wohnungen");
        $row = mysqli_fetch_assoc($res);
        $this->$Gesamtwohnflaeche = $row['Gesamtwohnflaeche'];
    }

    public function euro( $x ){
        $fmt = new NumberFormatter( 'de_DE', NumberFormatter::CURRENCY );
        return $fmt->formatCurrency( $x, "euro" );
    } 

}
