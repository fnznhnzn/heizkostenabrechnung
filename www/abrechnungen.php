<?php
    declare(strict_types=1);
    error_reporting(E_ALL);
    ini_set('display_errors', 'on');

    require_once('classes/Gasrechnung.php');
    $gas = new Gasrechnung();

    require_once('classes/Verteilung.php');
    $hkv = new Verteilung($gas);

    