<?php

class Gas extends Base{
    
    public $Lieferant;
    public $Rechnungsdatum;
    public $Kilowattstunden;
    public $Rechnungsbetrag;
    public $RechnungsbetragE;
    public static $Kilowattstundenpreis;
    public $KilowattstundenpreisE;

    public static function init(){
        parent::__construct();
        
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
        self::$Kilowattstundenpreis     = $gas['kWhPreis'];
        $this->KilowattstundenpreisE    = str_replace( '.', ',', $this->Kilowattstundenpreis ) . ' €';
    }

}