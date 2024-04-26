<?php  

class Base {
    # immutables
    public $conn;
    public $Abrechnungsjahr;
    public $Gesamtwohnflaeche;
    public $GesamtwohnflaecheD;
    public $Gebaeudeart;
    public $Brennstoff;
    # gas bill
    public $Lieferant;
    public $Rechnungsdatum;
    public $Kilowattstunden;
    public $KilowattstundenD;
    public $Rechnungsbetrag;
    public $RechnungsbetragE;
    public $Kilowattstundenpreis;
    public $KilowattstundenpreisE;

    public function __construct(){  
        # db
        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
        $this->conn->query("SET lc_time_names = 'de_DE'");

        # building type
        $this->Gebaeudeart = 'Denkmalgeschützter Altbau';

        # fossil type
        $this->Brennstoff = 'Erdgas';
        
        # year (from GET )
        $options = array('options'=>array('min_range'=>2023));
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'heizkosten', FILTER_VALIDATE_INT, $options);
        if(!$this->Abrechnungsjahr){ $this->Abrechnungsjahr = date('Y') -1; } # if none given, do last year
            # maybe some year chooser in the future?
        
        # total heatet area
        $res = mysqli_query($this->conn, "SELECT SUM(qm) AS Gesamtwohnflaeche FROM Wohnungen");
        $row = mysqli_fetch_assoc($res);
        $this->Gesamtwohnflaeche = $row['Gesamtwohnflaeche'];
        $this->GesamtwohnflaecheD = number_format( $this->Gesamtwohnflaeche, 2, ',', '.');

        # gas bill
        $sql = <<<SQL
            SELECT 
                Betrag, 
                DATE_FORMAT( Datum, '%e.%m.%Y' ) AS Datum, 
                Lieferant, 
                Kubikmeter, 
                kWh
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
        $this->KilowattstundenD         = number_format( $gas['kWh'], 0, ',', '.' );
        $this->Rechnungsbetrag          = $gas['Betrag'];
        $this->RechnungsbetragE         = $this->euro( $this->Rechnungsbetrag);
        $this->Kilowattstundenpreis     = $this->Rechnungsbetrag / $this->Kilowattstunden;
        $this->KilowattstundenpreisE    = str_replace( '.', ',', $this->Kilowattstundenpreis ) . ' €';
    }

    public function euro( $warmwasserkosten ){
        $fmt = new NumberFormatter( 'de_DE', NumberFormatter::CURRENCY );
        return $fmt->formatCurrency( $warmwasserkosten, "euro" );
    } 

    public function getBillReceivers(){ # who gets a bill?
        $sql =
        "SELECT
            *, Einzug, Auszug FROM Mieter m
        LEFT JOIN
            Wohnungen w ON m.Whg_ID = w.ID
        WHERE
            YEAR(Auszug) = $this->Abrechnungsjahr /* moved out this year */
            OR
        ( /* moved in before or during year, moved out after (or never), so still lives here! */
            YEAR(Einzug) <= $this->Abrechnungsjahr
                AND
                (
                    Auszug = '0000-00-00'
                    OR
                    YEAR(Auszug) > $this->Abrechnungsjahr
                )
        )";

        $res = $this->conn->query($sql);
        while($row = $res->fetch_assoc()){ # adjust in and out date for calculation
            if( substr($row['Einzug'],0,4) < $this->Abrechnungsjahr ){
                $row['Abrechnungsbeginn'] = $this->Abrechnungsjahr . '-01-01';
            } else {
                $row['Abrechnungsbeginn'] = $row['Einzug'];
            }
            if( $row['Auszug'] === '0000-00-00' || substr($row['Auszug'],0,4) > $this->Abrechnungsjahr ){ // not set or moves out some time after this year
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
        return number_format($n, 10, ',', '.');
    }

    public function percentage($warmwasserkosten, $heizkosten){
        $percent[0] = round( $warmwasserkosten / ($warmwasserkosten + $heizkosten) * 100, 2);
        $percent[1] = round( 100 - $percent[0], 2);
        return $percent;
    }
}
