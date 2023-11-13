<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Base.php');
require_once('classes/Gas.php');
require_once('classes/Warmwasser.php');
require_once('classes/Heizkostenverteiler.php');
require_once('classes/Flaechenverteilung.php');

$Base                   = new Base();
$Gas                    = new Gas();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler();
die(); # wie beerbt man mehrere Klassen? Traits?
$Flaechenverteilung     = new Flaechenverteilung();


