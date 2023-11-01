<?php
    
class Gasrechnung{
    public $conn;
    public $Abrechnungsjahr;
    public CONST TW = 45; # Temperatur Warmwasser
    public CONST Hi = 10; # Heizwert Erdgas H
    public CONST WARMWASSERKUBIKMETER = 123; # <= to be changed soon! (method getWasseverbrauch)

    # Gasrechnung
    public $Rechnungsdatum;
    public $Lieferant;
    public $Kubikmeter;
    public $Kilowattstunden;
    public $Rechnungsbetrag;
    public $RechnungsbetragE;
    public $Kubikmeterpreis;
    public $KubikmeterpreisE;
    public $Kilowattstundenpreis;
    public $KilowattstundenpreisE;

    # Heizung
    public $PreisHeizung;
    public $PreisHeizungE;
    public $PreisHeizung30Prozent;
    public $PreisHeizung30ProzentE;
    public $PreisHeizung70Prozent;
    public $PreisHeizung70ProzentE;
    public $PreisHeizungNetto;
    public $PreisHeizungNettoE;
    public $seventyPercent;
    public $seventyPercentE;
    public $VerbrauchWarmwasser;
    public $PreisWarmwasser;
    public $PreisWarmwasserE;
    public $gasNachFlaeche;
    public $gasNachFlaecheE;
    public $preisProQuadratmeter;

    function __construct(){
        # check input
        $options = array('options'=>array('min_range'=>2023));
        $this->Abrechnungsjahr = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $options);
        if(!$this->Abrechnungsjahr >= 2023){ echo 'give us y=2023 or later'; die(); }

        $this->conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");

        # get all gas related data
        $res = mysqli_query($this->conn, 
        "SELECT Betrag, DATE_FORMAT(Datum,'%e.%m.%x') AS Datum, Lieferant, Kubikmeter, kWh, Betrag/Kubikmeter AS qPreis, Betrag/kWh AS kWhPreis
         FROM Gasrechnungen 
         WHERE Abrechnungsjahr = '" . $this->Abrechnungsjahr . "'"
        );
        $gas = mysqli_fetch_assoc($res);
        # Gasrechnung
        $this->Lieferant                = $gas['Lieferant'];
        $this->Rechnungsdatum           = $gas['Datum'];
        $this->Kubikmeter               = $gas['Kubikmeter'];
        $this->Kilowattstunden          = $gas['kWh'];
        $this->KilowattstundenD         = number_format( $this->Kilowattstunden, 0, ',', '.');
        $this->Rechnungsbetrag          = $gas['Betrag'];
        $this->RechnungsbetragE         = $this->euro( $this->Rechnungsbetrag);
        $this->Kubikmeterpreis          = $gas['qPreis'];
        $this->KubikmeterpreisE         = $this->euro( $this->Kubikmeterpreis );
        $this->Kilowattstundenpreis     = $gas['kWhPreis'];
        $this->KilowattstundenpreisE    = str_replace( '.', ',', $this->Kilowattstundenpreis ) . ' €';

        # Warmwasser
        $this->VerbrauchWarmwasser      = 2.5 * self::WARMWASSERKUBIKMETER * self::TW / self::Hi; # vgl. HeizkostenV
        $this->VerbrauchWarmwasserD     = number_format( $this->VerbrauchWarmwasser, 2, ',', '.' );
        $this->PreisWarmwasser          = $this->VerbrauchWarmwasser * $this->Kilowattstundenpreis;
        $this->PreisWarmwasserE         = $this->euro( $this->PreisWarmwasser );

        # Heizung
        $this->PreisHeizung             = $this->Rechnungsbetrag - $this->PreisWarmwasser;
        $this->PreisHeizungE            = $this->euro( $this->PreisHeizung );
        $this->PreisHeizungNetto        = $this->PreisHeizung * 0.7; # nach Verbrauch
        $this->PreisHeizungNettoE       = $this->euro( $this->PreisHeizungNetto );
        
        # Verteilung
        $this->PreisHeizung30Prozent    = $this->PreisHeizung * 0.3; # nach Fläche
        $this->PreisHeizung30ProzentE   = $this->euro( $this->PreisHeizung30Prozent ); 
        $this->PreisHeizung70Prozent    = $this->PreisHeizung * 0.7;
        $this->PreisHeizung70ProzentE   = $this->euro( $this->PreisHeizung70Prozent );
        $this->preisProQuadratmeter     = $this->PreisHeizung30Prozent / $this->getWohnflaeche();
        $this->gasNachFlaeche           = $gas['Betrag'] - $this->PreisHeizungNetto;
        $this->gasNachFlaecheE          = $this->euro( $this->gasNachFlaeche );
        $this->seventyPercent           = $gas['Betrag'] * 0.7;
        $this->seventyPercentE          = $this->euro( $this->seventyPercent );
    }

    public function euro( $x ){
        $fmt = new NumberFormatter( 'de_DE', NumberFormatter::CURRENCY );
        return $fmt->formatCurrency( $x, "euro" );
    } 

    public function getWarmwasserVerbrauch(){
        return WASSERVERBRAUCH; # dummie value!
    }

    public function getWohnflaeche(){
        $res = mysqli_query($this->conn, "SELECT SUM(qm) AS Gesamtflaeche FROM Wohnungen");
        $row = mysqli_fetch_assoc($res);
        return $row['Gesamtflaeche'];
    }

    public function gaspreisNachWohnflaeche(){
        foreach ($this->conn->query("SELECT Nachname, qm, qm * ". $this->preisProQuadratmeter . " AS gpnw 
            FROM Wohnungen w LEFT JOIN Mieter m ON w.ID = m.Whg_ID ORDER BY w.ID") as $index => $row) {
            $gpnw[] = $row;
        }
        return $gpnw;
    }
}