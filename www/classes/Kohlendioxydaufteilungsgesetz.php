<?php

class Kohlendioxydaufteilungsgesetz {
    public $kWh;
    public $brennstoff;
    public $wohnflaeche;
    public $gebaeudeart;
    public $abrechnungsjahr;

    public function getEmissionsfaktor( $brennstoff ){
        switch( $brennstoff ) {
            case 'Erdgas':     $ef = 0.20088; break;
            case 'Heizöl':     $ef = 0.2664;  break;
            case 'Flüssiggas': $ef = 0.23580; break;
            case 'Kohle':      $ef = 0.3571;  break;
        }
    }

    public function getAufteilungsverhaeltnis( $co2Emsission ){
        switch( $co2Emission ){
            case $co2Emission < 12:  $av = 100; break;
            case $co2Emission < 17:  $av = 90;  break;
            case $co2Emission < 22:  $av = 80;  break;
            case $co2Emission < 27:  $av = 70;  break;
            case $co2Emission < 32:  $av = 60;  break;
            case $co2Emission < 37:  $av = 50;  break;
            case $co2Emission < 42:  $av = 40;  break;
            case $co2Emission < 47:  $av = 30;  break;
            case $co2Emission < 52:  $av = 20;  break;
            case $co2Emission >=52:  $av = 5;   break;
        }
    }

    public function getCo2perSqmAndYearb
}




