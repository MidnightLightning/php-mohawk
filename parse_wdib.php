<?php
/**
 * Parse an individual WDIB file
 *
 * Usage:
 *   php parse_wdib.php [PATH TO WDIB FILE]
 * For parsing all WDIB files in a folder (in OSX/Linux):
 *   find ./output -name '*.WDIB' -exec php parse_wdib.php '{}' \;
 */

if (count($argv) < 2) exit("Need to specify an input data file\n");
$src = $argv[1];
if (!file_exists($src)) exit("Data file $src doesn't exist\n");

echo "Parsing WDIB file $src...";
$dir = pathinfo($src);
if (file_exists($dir['dirname'].'/'.$dir['filename'].'.png')) exit("Exists\n");

require('lib/binParser.php');
require('lib/wdib.php');

$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

$r = new wdib($bin);
$im_bmp = $r->convert(); // Extract the BMP data

//file_put_contents($dir['dirname'].'/'.$dir['filename'].'.bmp', $im); // Save as BMP

// Parse the binary BMP data
$FILE = unpack('vfile_type/Vfile_size/Vreserved/Vbitmap_offset', substr($im_bmp, 0, 14));
if ($FILE['file_type'] != 0x4D42) exit("Not a BMP file?\n");
$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
  '/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
  '/Vvert_resolution/Vcolors_used/Vcolors_important', substr($im_bmp, 14,40));
$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
$BMP['decal'] = 4-(4*$BMP['decal']);
if ($BMP['decal'] == 4) $BMP['decal'] = 0;

$N = chr(0);
if ($BMP['bits_per_pixel'] == 24) {
	// True color image
	$im = imagecreatetruecolor($BMP['width'],$BMP['height']);
	
	$img_data_offset = 54;
	$IMG = substr($im_bmp, $img_data_offset, $BMP['size_bitmap']);
	$P = 0;
	$Y = $BMP['height']-1;
	while ($Y >= 0) {
		$X=0;
		while ($X < $BMP['width']) {
			$COLOR = unpack("V",substr($IMG,$P,3).$N);
			imagesetpixel($im,$X,$Y,$COLOR[1]);
			$X++;
			$P += $BMP['bytes_per_pixel'];
		}
		$Y--;
		$P+=$BMP['decal'];
	}
	
} else { // Myst images are either 8- or 24-bits per pixel, so we don't have to worry about the rest
	// 8-bits-per-pixel palette-based image
	$im = imagecreate($BMP['width'],$BMP['height']);
	
	$PALETTE = unpack('V'.$BMP['colors'], substr($im_bmp, 54, $BMP['colors']*4));
	foreach($PALETTE as $c) {
		// 1111 1000 1111 0011 1110 0011
		$red   = ($c & 0xff0000) >> 16;
		$green = ($c & 0x00ff00) >> 8;
		$blue  = $c & 0x0000ff;
		imagecolorallocate($im, $red, $green, $blue);
	}
	
	$img_data_offset = 54+$BMP['colors']*4;
	$IMG = substr($im_bmp, $img_data_offset, $BMP['size_bitmap']);
	$P = 0;
	$Y = $BMP['height']-1;
	while ($Y >= 0) {
		$X=0;
		while ($X < $BMP['width']) {
			$COLOR = unpack("n",$N.substr($IMG,$P,1));
			imagesetpixel($im,$X,$Y,$COLOR[1]);
			$X++;
			$P += $BMP['bytes_per_pixel'];
		}
		$Y--;
		$P+=$BMP['decal'];
	}
	
}

// Save graphic as PNG
imagepng($im, $dir['dirname'].'/'.$dir['filename'].'.png');
echo "Done\n";