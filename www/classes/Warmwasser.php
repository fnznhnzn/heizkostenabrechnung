<?php

class Warmwasser extends Base {
    
    public CONST TW = 56; # Temperatur Warmwasser
    public CONST Hi = 10; # Heizwert Erdgas H
    public $Wasserzaehler = 30015984; # Wasserzähler im Zulauf zum Boiler
    
    public $Wasserverbrauch;
    public $WasserverbrauchD;
    public $kWh_Gas_fuer_Warmwasser;
    public $kWh_Gas_fuer_WarmwasserD;
    public $kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor;
    public $Preis_Warmwasser;
    public $Preis_WarmwasserE;
    public $Preis_Warmwasser_BrunataStyle;
    public $Preis_Warmwasser_pro_Quadratmeter;
    public $Preis_Warmwasser_pro_QuadratmeterE;
    public $Preis_Warmwasser_pro_QuadratmeterD;

    public function __construct(){
        parent::__construct();
        $this->Wasserverbrauch = $this->getWaterConsumption();
        $this->WasserverbrauchD = number_format( $this->Wasserverbrauch, 3, ',', '.' );
        $this->kWh_Gas_fuer_Warmwasser = 2.5 * $this->Wasserverbrauch * ( self::TW - self::Hi ); # see HeizkostenV
        $this->kWh_Gas_fuer_WarmwasserD = number_format( $this->kWh_Gas_fuer_Warmwasser, 0, ',', '.');
        $this->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor = $this->kWh_Gas_fuer_Warmwasser * 1.11;
        $this->Preis_Warmwasser = $this->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor * $this->Kilowattstundenpreis; # money spent
        $this->Preis_WarmwasserE = $this->euro( $this->Preis_Warmwasser );
        $this->Preis_Warmwasser_BrunataStyle = $this->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor / $this->Kilowattstunden * $this->Rechnungsbetrag;
        $this->Preis_Warmwasser_pro_Quadratmeter = $this->Preis_Warmwasser / $this->Gesamtwohnflaeche;
        $this->Preis_Warmwasser_pro_QuadratmeterE = $this->euro( $this->Preis_Warmwasser_pro_Quadratmeter );
        $this->Preis_Warmwasser_pro_QuadratmeterD = number_format( $this->Preis_Warmwasser_pro_Quadratmeter, 4, ',', '.' );
    }
    
    public function preis_pro_Wohnung( $Quadratmeter, $Abrechnungsbeginn, $Abrechnungsende ){
        $TageImJahr = $this->daysInYear( $this->Abrechnungsjahr );
        $Abrechnungstage = $this->ComputeDays($Abrechnungsbeginn, $Abrechnungsende);
        $Jahreskosten = $this->Preis_Warmwasser_pro_Quadratmeter * $Quadratmeter;
        $Anteil = $Jahreskosten / $TageImJahr * $Abrechnungstage;
        return $Anteil;
    }

    public function preis_pro_WohnungE( $Quadratmeter, $Abrechnungsbeginn, $Abrechnungsende ){
        return $this->euro( $this->Preis_pro_Wohnung( $Quadratmeter, $Abrechnungsbeginn, $Abrechnungsende ) );
    }

    public function getWaterConsumption(){
        $sql = "
        SELECT
            MAX(Wert) AS Verbrauch
        FROM
            Messwerte
        WHERE
            YEAR(Zeitpunkt) = '$this->Abrechnungsjahr'
        AND
            Zaehler_ID = '$this->Wasserzaehler'
        ";
        $res = $this->conn->query($sql);
        $row = $res->fetch_assoc();
        return $row['Verbrauch'];
    }

}
    
