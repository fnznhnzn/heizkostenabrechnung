-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 22, 2026 at 07:12 PM
-- Server version: 10.11.14-MariaDB-0+deb12u2
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `heizkostenabrechnung`
--

-- --------------------------------------------------------

--
-- Table structure for table `Fehler`
--

CREATE TABLE `Fehler` (
  `ID` int(11) NOT NULL,
  `Zaehler_ID` varchar(64) NOT NULL,
  `Hinweisdatum` varchar(64) NOT NULL,
  `Hinweisflag` varchar(64) NOT NULL COMMENT 's. Fehlercodeliste'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Gasrechnungen`
--

CREATE TABLE `Gasrechnungen` (
  `ID` tinyint(3) UNSIGNED NOT NULL,
  `Lieferant` varchar(64) NOT NULL,
  `Datum` date NOT NULL,
  `Rechnungsnummer` varchar(32) NOT NULL,
  `Abrechnungsjahr` year(4) NOT NULL,
  `Kubikmeter` decimal(8,4) UNSIGNED NOT NULL,
  `kWh` mediumint(8) UNSIGNED NOT NULL,
  `Betrag` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Gebaeude`
--

CREATE TABLE `Gebaeude` (
  `ID` int(11) NOT NULL,
  `Strasse` varchar(256) NOT NULL,
  `Stadt` varchar(256) NOT NULL,
  `PLZ` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Heizkoerper`
--

CREATE TABLE `Heizkoerper` (
  `ID` int(10) UNSIGNED NOT NULL,
  `Hersteller` varchar(256) NOT NULL,
  `Art` enum('Platten','Glieder','Bad','') NOT NULL,
  `Breite` smallint(11) UNSIGNED NOT NULL COMMENT 'mm',
  `Hoehe` smallint(11) UNSIGNED NOT NULL COMMENT 'mm',
  `Tiefe` smallint(11) UNSIGNED NOT NULL COMMENT 'mm',
  `Segmente` tinyint(11) UNSIGNED NOT NULL COMMENT 'Rippen/Rohre',
  `Segmentbreite` tinyint(11) UNSIGNED NOT NULL COMMENT 'Rippenabstand / Ø-Rohre mm',
  `Schichtung` varchar(11) NOT NULL COMMENT 'P = Platte, K = Konvektionsblech, C = Cover / Rohre',
  `Kq` float NOT NULL COMMENT 'Leistung in KW',
  `Kc` float NOT NULL COMMENT 'Trägheit des Zählers'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `HKV_aktiv`
-- (See below for the actual view)
--
CREATE TABLE `HKV_aktiv` (
`ID` varchar(16)
,`Whg_ID` int(11)
,`Raum` varchar(64)
,`Nachname` varchar(64)
,`Installiert` date
);

-- --------------------------------------------------------

--
-- Table structure for table `Messwerte`
--

CREATE TABLE `Messwerte` (
  `ID` int(11) UNSIGNED NOT NULL,
  `Zaehler_ID` int(16) NOT NULL,
  `Zeitpunkt` datetime NOT NULL,
  `Wert` float NOT NULL COMMENT 'seit 1.1.',
  `Nettowert` float NOT NULL DEFAULT 0 COMMENT 'seit letzter Messung'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Mieter`
--

CREATE TABLE `Mieter` (
  `ID` int(11) NOT NULL,
  `Whg_ID` int(11) NOT NULL,
  `Nachname` varchar(64) NOT NULL,
  `Vorname` varchar(64) NOT NULL,
  `Einzug` date NOT NULL,
  `Auszug` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `Mieterwechsel_2025`
-- (See below for the actual view)
--
CREATE TABLE `Mieterwechsel_2025` (
`Whg_ID` int(11)
,`ID` varchar(16)
,`Raum` varchar(64)
,`Nachname` varchar(64)
,`Vorname` varchar(64)
,`Einzug` date
,`Auszug` date
);

-- --------------------------------------------------------

--
-- Table structure for table `Wasser`
--

CREATE TABLE `Wasser` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `Zaehler_ID` varchar(64) NOT NULL,
  `Datum` datetime NOT NULL,
  `Messwert` float UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Wohnungen`
--

CREATE TABLE `Wohnungen` (
  `ID` int(11) NOT NULL,
  `Gebaeude_ID` int(11) NOT NULL,
  `QuickImmoID` tinyint(3) UNSIGNED NOT NULL,
  `Etage` varchar(32) NOT NULL,
  `Lage` varchar(32) NOT NULL,
  `qm` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Zaehler`
--

CREATE TABLE `Zaehler` (
  `ID` varchar(16) NOT NULL,
  `Whg_ID` int(11) NOT NULL,
  `Raum` varchar(64) NOT NULL,
  `Heizkoerper_ID` tinyint(3) UNSIGNED NOT NULL,
  `Installiert` date NOT NULL,
  `Kennung` varchar(19) NOT NULL COMMENT 'EFE = Engelmann',
  `Ersetzt_durch` varchar(16) DEFAULT NULL COMMENT 'z.B. wegen Defekt'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Structure for view `HKV_aktiv`
--
DROP TABLE IF EXISTS `HKV_aktiv`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `HKV_aktiv`  AS SELECT `z`.`ID` AS `ID`, `z`.`Whg_ID` AS `Whg_ID`, `z`.`Raum` AS `Raum`, `Mieter`.`Nachname` AS `Nachname`, `z`.`Installiert` AS `Installiert` FROM (`Zaehler` `z` left join `Mieter` on(`z`.`Whg_ID` = `Mieter`.`Whg_ID` and `Mieter`.`Einzug` = (select max(`Mieter`.`Einzug`) from `Mieter` where `z`.`Whg_ID` = `Mieter`.`Whg_ID`))) WHERE `z`.`Whg_ID` <> 0 AND `z`.`Ersetzt_durch` = '' ORDER BY `z`.`Whg_ID` ASC, `z`.`ID` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `Mieterwechsel_2025`
--
DROP TABLE IF EXISTS `Mieterwechsel_2025`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `Mieterwechsel_2025`  AS SELECT `m`.`Whg_ID` AS `Whg_ID`, `z`.`ID` AS `ID`, `z`.`Raum` AS `Raum`, `m`.`Nachname` AS `Nachname`, `m`.`Vorname` AS `Vorname`, `m`.`Einzug` AS `Einzug`, `m`.`Auszug` AS `Auszug` FROM (`Mieter` `m` left join `Zaehler` `z` on(`z`.`Whg_ID` = `m`.`Whg_ID`)) WHERE (year(`m`.`Einzug`) = 2025 OR year(`m`.`Auszug`) = 2025) AND `m`.`Nachname` <> 'Leerstand' ORDER BY `m`.`Whg_ID` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Fehler`
--
ALTER TABLE `Fehler`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Zaehler_ID` (`Zaehler_ID`,`Hinweisdatum`);

--
-- Indexes for table `Gasrechnungen`
--
ALTER TABLE `Gasrechnungen`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Abrechnungsjahr` (`Abrechnungsjahr`);

--
-- Indexes for table `Gebaeude`
--
ALTER TABLE `Gebaeude`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Heizkoerper`
--
ALTER TABLE `Heizkoerper`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Messwerte`
--
ALTER TABLE `Messwerte`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Zaehler_ID` (`Zaehler_ID`,`Zeitpunkt`);

--
-- Indexes for table `Mieter`
--
ALTER TABLE `Mieter`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Wasser`
--
ALTER TABLE `Wasser`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `Zaehler_ID` (`Zaehler_ID`,`Datum`);

--
-- Indexes for table `Wohnungen`
--
ALTER TABLE `Wohnungen`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Zaehler`
--
ALTER TABLE `Zaehler`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `Heizkoerper_ID` (`Heizkoerper_ID`),
  ADD KEY `Whg_ID` (`Whg_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Fehler`
--
ALTER TABLE `Fehler`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Gasrechnungen`
--
ALTER TABLE `Gasrechnungen`
  MODIFY `ID` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Gebaeude`
--
ALTER TABLE `Gebaeude`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Heizkoerper`
--
ALTER TABLE `Heizkoerper`
  MODIFY `ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Messwerte`
--
ALTER TABLE `Messwerte`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Mieter`
--
ALTER TABLE `Mieter`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Wasser`
--
ALTER TABLE `Wasser`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Wohnungen`
--
ALTER TABLE `Wohnungen`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
