<?php # Kohlendioxydkostenaufteilungsgesetz

class CO2AufG extends Base {
    public $Kilowattstunden; # Energieverbrauch p.a.
    public $Brennstoff;
    public $Gesamtwohnflaeche;
    public $Gebaeudeart;
    public $Abrechnungsjahr;

    public function Emissionsfaktor(){
        $ef = 0;
        switch( $this->Brennstoff ) {
            case 'Erdgas':     $ef = 0.20088; break;
            case 'Heizöl':     $ef = 0.2664;  break;
            case 'Flüssiggas': $ef = 0.23580; break;
            case 'Kohle':      $ef = 0.3571;  break;
        }
        return $ef;
    }

    public function Emission(){ # kg/a
        return $this->Kilowattstunden * $this->Emissionsfaktor();
    }

    public function EmissionTons(){
        $E = $this->Emission() / 1000;
        return number_format( $E, 3, ',', '.' );
    }

    public function co2proQm(){
        return $this->Kilowattstunden * $this->Emissionsfaktor() / $this->Gesamtwohnflaeche;
    }

    public function co2proQmTenant(){
        return $this->Mieterkosten() / $this->Gesamtwohnflaeche;
    }

    public function co2proQmLandlord(){
        return $this->Vermieterkosten() / $this->Gesamtwohnflaeche;
    }

    public function Vermieteranteil(){
        $co2 = $this->co2proQm();
        switch( $co2 ){
            case $co2 < 12:  $vm = 0;  break;
            case $co2 < 17:  $vm = 10; break;
            case $co2 < 22:  $vm = 20; break;
            case $co2 < 27:  $vm = 30; break;
            case $co2 < 32:  $vm = 40; break;
            case $co2 < 37:  $vm = 50; break;
            case $co2 < 42:  $vm = 60; break;
            case $co2 < 47:  $vm = 70; break;
            case $co2 < 52:  $vm = 80; break;
            case $co2 >=52:  $vm = 95; break;
        }

        if( $this->Gebaeudeart === 'Denkmalgeschützter Altbau'){
            $vm = $vm / 2;
        }
        return $vm;
    }

    public function Verteilung(){
        $vermieter = $this->Vermieteranteil();
        $mieter = 100 - $this->Vermieteranteil();
        return $mieter . '/' . $vermieter;
    }

    public function Kohlendioxydpreis($proKg = false){
        switch( $this->Abrechnungsjahr ){ # Preis pro Tonne
            case '2024': $kp = 45; break;
            case '2025': $kp = 55; break;
            default: $kp = 30; break;
        }
        if( $proKg === true ){
            $kp /= 1000;
        }
        return $kp;
    }

    public function Emissionspreis(){
        return $this->Emission( $this->Kilowattstunden, $this->Brennstoff ) * $this->Kohlendioxydpreis( $this->Abrechnungsjahr ) / 1000;
    }

    public function Vermieterkosten(){
        return $this->Emissionspreis( $this->Kilowattstunden, $this->Brennstoff, $this->Abrechnungsjahr ) * $this->Vermieteranteil() / 100;
    }

    public function Mieterkosten(){
        return $this->Emissionspreis( $this->Kilowattstunden, $this->Brennstoff, $this->Abrechnungsjahr, $this->Gebaeudeart) - 
        $this->Vermieterkosten( $this->Kilowattstunden, $this->Brennstoff, $this->Abrechnungsjahr, $this->Gebaeudeart );
    }

    public function carbonPerTenant( $qm, $fromDate, $toDate ){ # tons
        $daysBilled = $this->computeDays( $fromDate, $toDate );
        $carbon = $this->co2proQm() * $qm;
        $part = $carbon / $this->daysInYear() * $daysBilled;
        return $part / 1000 ;
    }

    public function costPerTenant( $qm, $fromDate, $toDate, $euroFormatted = false ){
        $daysBilled = $this->computeDays( $fromDate, $toDate );
        $cost = $this->co2proQmTenant() * $qm;
        $part = $cost / $this->daysInYear() * $daysBilled;
        if( $euroFormatted === true ){
            return $this->euro( $part );
        } else {
            return $part;
        }
    }

    public function landlordCostPerTenant( $qm, $fromDate, $toDate, $euroFormatted = false ){
        $daysBilled = $this->computeDays( $fromDate, $toDate );
        $cost = $this->co2proQmLandlord() * $qm;
        $part = $cost / $this->daysInYear() * $daysBilled;
        if( $euroFormatted === true ){
            return $this->euro( $part );
        } else {
            return $part;
        }
    }


}




