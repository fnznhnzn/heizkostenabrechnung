<?php
# Query zum Testen des Verbrauchs von Wohnung 1, Ergebnis entspricht usagePerFlat.php
$sql = <<<SQL
SELECT
    SUM(
        Nettowert * h.Kq * h.Kc / 2.288
    )
FROM
    Messwerte
LEFT JOIN Zaehler z ON
    z.ID = Zaehler_ID
LEFT JOIN Heizkoerper h ON
    Heizkoerper_ID = h.ID
WHERE
    Zaehler_ID IN(
        '21116047',
        '21116055',
        '21116056',
        '21116057',
        '21116059'
    ) AND YEAR(Zeitpunkt) = '2024'
SQL;

# dito Verbrauch pro Wohnung, übereinstimmend mit usagePerFlat.php
$sql = <<<SQL
SELECT
    w.ID,
    SUM(
        Nettowert * h.Kq * h.Kc / 2.288
    )
FROM
    Messwerte
LEFT JOIN Zaehler z ON
    z.ID = Zaehler_ID
LEFT JOIN Heizkoerper h ON
    Heizkoerper_ID = h.ID
LEFT JOIN Wohnungen w ON
	w.ID = Whg_ID
WHERE
    YEAR(Zeitpunkt) = '2024'
GROUP BY
	w.ID
SQL;

# dito alle Wohnungen zusammen, ebenfalls übereinstimmend mit usagePerFlat.php (einfach nur ohne GROUP BY)
$sql = <<<SQL
SELECT
    SUM(
        Nettowert * h.Kq * h.Kc / 2.288
    )
FROM
    Messwerte
LEFT JOIN Zaehler z ON
    z.ID = Zaehler_ID
LEFT JOIN Heizkoerper h ON
    Heizkoerper_ID = h.ID
WHERE
    YEAR(Zeitpunkt) = '2024'
SQL;

