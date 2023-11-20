<?php  

class Base {
    # immutables
    public $conn;
    public $Abrechnungsjahr;
    public $Gesamtwohnflaeche;
    # gas bill
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

    public function getBillReceivers(){ # who gets a bill?
        $sql = "SELECT *, Einzug, Auszug
        FROM Mieter m
        LEFT JOIN Wohnungen w ON m.Whg_ID = w.ID 
        WHERE YEAR(Auszug) = $this->Abrechnungsjahr
        OR Auszug = '0000-00-00'"; /* either moved (in and) out this year or still lives here */

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

    public function formatDate($date){
        $date = strtotime( $date . '00:00:00' );
        $date = date('d.m.Y', $date);
        return $date;
    }

    public function nf($n){
        return number_format($n, 16, ',', '.');
    }

}
