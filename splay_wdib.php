<?php
if (count($argv) < 2) exit("Need to specify an input data file\n");
$src = $argv[1];
if (!file_exists($src)) exit("Data file $src doesn't exist\n");

require('lib/binParser.php');
require('lib/wdib.php');

$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

$r = new wdib($bin);
$r->splay();
