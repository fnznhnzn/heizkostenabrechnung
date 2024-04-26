<?php # Kohlendioxydkostenaufteilungsgesetz

class CO2AufG {
    public $kWh; # Energieverbrauch p.a.
    public $brennstoff;
    public $wohnflaeche;
    public $gebaeudeart;
    public $abrechnungsjahr;

    public function Emissionsfaktor( $brennstoff ){
        switch( $brennstoff ) {
            case 'Erdgas':     $ef = 0.20088; break;
            case 'Heizöl':     $ef = 0.2664;  break;
            case 'Flüssiggas': $ef = 0.23580; break;
            case 'Kohle':      $ef = 0.3571;  break;
        }
        return $ef;
    }

    public function Emission( $kWh, $brennstoff ){ # gesamt in Tonnen p.a.
        return $kWh * $this->Emissionsfaktor( $brennstoff ) / 1000;
    }

    public function Aufteilungsverhaeltnis( $co2PerSqm, $gebaeudeart ){
        switch( $co2PerSqm ){
            case $co2PerSqm < 12:  $av = 100; break;
            case $co2PerSqm < 17:  $av = 90;  break;
            case $co2PerSqm < 22:  $av = 80;  break;
            case $co2PerSqm < 27:  $av = 70;  break;
            case $co2PerSqm < 32:  $av = 60;  break;
            case $co2PerSqm < 37:  $av = 50;  break;
            case $co2PerSqm < 42:  $av = 40;  break;
            case $co2PerSqm < 47:  $av = 30;  break;
            case $co2PerSqm < 52:  $av = 20;  break;
            case $co2PerSqm >=52:  $av = 5;   break;
        }
        return $av;
    }

    public function co2perQm( $kWh, $brennstoff, $wohnflaeche ){
        return $kWh * $this->Emissionsfaktor( $brennstoff ) / $wohnflaeche;
    }

    public function Kohlendioxydpreis( $abrechnungsjahr ){
        switch( $abrechnungsjahr ){
            case '2024': $ep = 45; break;
            default: $ep = 30; break;
        }
        return $ep;
    }

    public function Gesamtemissionspreis( $kWh, $brennstoff, $abrechnungsjahr ){
        return $this->Emission( $kWh, $brennstoff ) * $this->Kohlendioxydpreis( $abrechnungsjahr );
    }

    public function getAnteile( $kWh, $brennstoff, $abrechnungsjahr, $gebaeudeart ){

    }
}




