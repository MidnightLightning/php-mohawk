<?php
/**
 * Parse an individual tBMP file
 *
 * Usage:
 *   php parse_tBMP.php [PATH TO TBMP FILE]
 * For parsing all tBMP files in a folder (in OSX/Linux):
 *   find ./output -name '*.tBMP' -exec php parse_tBMP.php '{}' \;
 */

if (count($argv) < 2) exit("Need to specify an input data file\n");
$src = $argv[1];
if (!file_exists($src)) exit("Data file $src doesn't exist\n");

require('lib/binParser.php');
require('lib/tBMP.php');

echo "Parsing tBMP file $src...\n";
$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

$r = new tBMP($bin);
$im = $r->convert();

$dir = pathinfo($src);
imagepng($im, $dir['dirname'].'/'.$dir['filename'].'.png');
