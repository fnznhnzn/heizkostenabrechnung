<?php

class Warmwasser extends Base {
    
    public CONST TW = 45; # Temperatur Warmwasser
    public CONST Hi = 10; # Heizwert Erdgas H
    public CONST WARMWASSERKUBIKMETER = 123; # <= to be changed soon!
    
    public $kWh_Gas_fuer_Warmwasser;
    public $Preis_Warmwasser;
    public $Preis_WarmwasserE;
    public $Preis_Warmwasser_pro_Quadratmeter;
    public $Preis_Warmwasser_pro_QuadratmeterE;

    public function __construct(){
        parent::__construct();
        $this->kWh_Gas_fuer_Warmwasser = 2.5 * self::WARMWASSERKUBIKMETER * self::TW / self::Hi; # see HeizkostenV
        $this->Preis_Warmwasser = $this->kWh_Gas_fuer_Warmwasser * $this->Kilowattstundenpreis; # money spent
        $this->Preis_WarmwasserE = $this->euro( $this->Preis_Warmwasser );
        $this->Preis_Warmwasser_pro_Quadratmeter = $this->Preis_Warmwasser / $this->Gesamtwohnflaeche;
        $this->Preis_Warmwasser_pro_QuadratmeterE = $this->euro( $this->Preis_Warmwasser_pro_Quadratmeter );
    }
    
    public function preis_pro_Wohnung( $Quadratmeter ){
        return $this->euro( $this->Preis_Warmwasser_pro_Quadratmeter * $Quadratmeter );
    }

}
    