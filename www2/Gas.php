<?php
    public $Lieferant;
    public $Rechnungsdatum;
    public $Kilowattstunden;
    public $Rechnungsbetrag;
    public $RechnungsbetragE;
    public $Kilowattstundenpreis;
    public $KilowattstundenpreisE;

class Gas{

    public function __construct(){
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
                Abrechnungsjahr = $Base->Abrechnungsjahr
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
    
}