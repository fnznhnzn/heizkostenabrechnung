<?php

class Warmwasser{
    
    public CONST TW = 45; # Temperatur Warmwasser
    public CONST Hi = 10; # Heizwert Erdgas H
    public CONST WARMWASSERKUBIKMETER = 123; # <= to be changed soon!
    
    public $Preis_Warmwasser;
    public $Preis_Warmwasser_pro_Quadratmeter;

    public function __construct(){
        $kWh_Gas_fuer_Warmwasser = 2.5 * self::WARMWASSERKUBIKMETER * self::TW / self::Hi;
        $this->Preis_Warmwasser = $kWh_Gas_fuer_Warmwasser * $gas->Kilowattstundenpreis;
    }
    
    public function warmwasser_nach_flaeche( $Quadratmeter ){
        $this->Preis_Warmwasser_pro_Quadradmeter = $this->Preis_Warmwasser / $Base->Gesamtwohnflaeche;
        return $this->Preis_Warmwasser_pro_Quadratmeter * $Quadratmeter;
    }

}
    