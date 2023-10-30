/* hier stimmt noch nix, debug!!! Am besten erstmal Kc-Werte ermitteln und in 
Tabelle "Heizkörper" eintragen */

SET @year = 2023;
SET @gasbill = ( SELECT Betrag FROM Gasrechnungen WHERE Abrechnungsjahr = @year );

/* Summe aller Messwerte aller Zähler (jeder Heizkörper 
jeweils mit K-Werten und "Engelmann-Quotient" bereinigt) */
SET @totalValues = (
    SELECT 
        SUM( m.Wert * h.Kq * h.Kc / 2.228 )
    FROM
        Zaehler AS z
    LEFT JOIN
        Heizkoerper AS h
    ON
        (z.Heizkoerper_ID = h.ID)
    LEFT JOIN
        Messwerte AS m
    ON
        (z.ID = m.Zaehler_ID)
    WHERE 
        YEAR( m.Zeitpunkt ) = @year
);

/* Gasrechnung durch Gesamtzahl bereinigte Messeinheiten ergibt Faktor */
SET @share = (SELECT @gasbill / @totalValues);

/* Faktor mal bereinigte Messeinheiten pro Wohnung = deren Heizkostenanteil */
SELECT 
    Nachname, w.Etage, w.Lage, ROUND( SUM( m.Wert * h.Kq * h.Kc / 2.228 ) * @share, 2 )
FROM 
    Wohnungen AS w 
LEFT JOIN 
    Zaehler AS z 
ON
    (w.ID = z.Whg_ID)
LEFT JOIN
	Messwerte AS m
ON
	(z.ID = m.Zaehler_ID)
LEFT JOIN
        Heizkoerper AS h
ON
    (z.Heizkoerper_ID = h.ID)
LEFT JOIN
    Mieter
USING
    (Whg_ID)
WHERE
    YEAR( m.Zeitpunkt ) = @year
GROUP BY
	w.ID;

