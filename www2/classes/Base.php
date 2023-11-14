<?php  

class Base {
    
    public $conn;
    public $Abrechnungsjahr;
    public $Gesamtwohnflaeche;

    public $Lieferant;
    public $Rechnungsdatum;
    public $Kilowattstunden;
    public $Rechnungsbetrag;
    public $RechnungsbetragE;
    public $Kilowattstundenpreis;
    public $KilowattstundenpreisE;

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
        $this->Gesamtwohnflaeche = $row['Gesamtwohnflaeche'];

        # gas bill
        $sql = <<<SQL
            SELECT 
                Betrag, 
                DATE_FORMAT( Datum, '%e.%m.%x' ) AS Datum, 
                Lieferant, 
                Kubikmeter, 
                kWh, 
                Betrag / kWh AS kWhPreis
            FROM 
                Gasrechnungen 
            WHERE 
                Abrechnungsjahr = $this->Abrechnungsjahr
        SQL;

        $res = mysqli_query( $this->conn, $sql );
        $gas = mysqli_fetch_assoc( $res );

        $this->Lieferant                = $gas['Lieferant'];
        $this->Rechnungsdatum           = $gas['Datum'];
        $this->Kilowattstunden          = $gas['kWh'];
        $this->Rechnungsbetrag          = $gas['Betrag'];
        $this->RechnungsbetragE         = $this->euro( $this->Rechnungsbetrag);
        $this->Kilowattstundenpreis     = $gas['kWhPreis'];
        $this->KilowattstundenpreisE    = str_replace( '.', ',', $this->Kilowattstundenpreis ) . ' €';
    }

    public function euro( $x ){
        $fmt = new NumberFormatter( 'de_DE', NumberFormatter::CURRENCY );
        return $fmt->formatCurrency( $x, "euro" );
    } 

}
