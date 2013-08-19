<?php
/**
 * Take a BMP file and compress it into a WDIB file (for use in a Myst PC Mohawk archive)
 */

if (count($argv) < 2) exit("Need to specify an input data file\n");
$src = $argv[1];
if (!file_exists($src)) exit("Data file $src doesn't exist\n");

require('lib/binParser.php');
require('lib/wdib.php');

$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

$r = wdib::createFromBMP($bin);
$dir = pathinfo($src);
file_put_contents($dir['dirname'].'/'.$dir['filename'].'.WDIB', pack('H*', $r));