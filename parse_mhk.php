<?php
/**
 * Parse a Mohawk file
 *
 * Usage: 
 *   php parse_mhk.php [PATH TO MHK FILE]
 *
 * Creates an "output" folder, and creates a '*.tBMP' file for each tBMP resource found
 */

if (count($argv) < 2) exit("Need to specify an input data file\n");
$src = $argv[1];
if (!file_exists($src)) exit("Data file $src doesn't exist\n");

require('lib/binParser.php');
require('lib/tBMP.php');

$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

mkdir('output'); // Create output folder, if it doesn't exist

// http://insidethelink.ortiche.net/wiki/index.php/Mohawk_archive_format
if ($bin->word() !== 'MHWK') exit("Not a valid MHWK file\n");
$file_size = $bin->long();
if ($bin->word() != 'RSRC') exit("RSRC header not found");
$rsrc = array();
$rsrc['version'] = $bin->short();
$rsrc['compaction'] = $bin->short();
$rsrc['size'] = $bin->long();
$rsrc['offset'] = $bin->long();

$file_table = array();
$file_table['offset'] = $bin->short();
$file_table['size'] = $bin->short();

// Parse the file table
$bin->cursor = $rsrc['offset']+$file_table['offset'];
$num = $bin->long();
$resources = array();
for($i=1; $i<=$num; $i++) {
	$resources[$i] = array();
	$resources[$i]['offset'] = $bin->long();
	$s1 = $bin->short();
	$s2 = $bin->byte();
	$resources[$i]['size'] = ($s2 << 16) | $s1;
	//echo "{$i}: S1: {$s1}, S2: {$s2}, Size: {$resources[$i]['size']} (".($resources[$i]['size']/1024/1024).")\n";
	$flags = $bin->byte();
	$tmp = $bin->short();
	//$resources[$i]['bin'] = new binParser(substr($bin->bin, $resources[$i]['offset']*2, $resources[$i]['size']*2));
	$resources[$i]['name'] = '';
}

// Parse the type table
$bin->cursor = $rsrc['offset'];
$names_offset = $bin->short();

$num = $bin->short();
echo "$num Types total:\n";
$types = array();
for ($i=0; $i<$num; $i++) {
	$type = array();
	$type['name'] = $bin->word();
	$type['resource_offset'] = $bin->short();
	$type['name_offset'] = $bin->short();
	echo "Type {$type['name']}...\n";
	
	// Get Names
	$prior = $bin->cursor; // Save
	$bin->cursor = $rsrc['offset'] + $type['name_offset'];
	$name_num = $bin->short();
	$type['names'] = array();
	for ($j=0; $j<$name_num; $j++) {
		$tmp = array();
		$tmp['offset'] = $bin->short();
		$tmp['index'] = $bin->short();
		
		$prior2 = $bin->cursor; // Save
		//echo "Resource Dir offset: {$rsrc['offset']}\nResource Name List offset: {$names_offset} (".($rsrc['offset']+$names_offset).")\nItem {$tmp['index']} ({$type['name']}) offset: {$tmp['offset']}\n";
		$bin->cursor = $rsrc['offset']+$names_offset+$tmp['offset'];
		$tmp['str'] = $bin->str();
		$resources[$tmp['index']]['name'] = $tmp['str'];
		$bin->cursor = $prior2; // Restore
		
		$type['names'][] = $tmp;
	}
	$bin->cursor = $prior; // Restore
	
	// Get Resources
	$prior = $bin->cursor; // Save
	$bin->cursor = $rsrc['offset'] + $type['resource_offset'];
	$rsrc_num = $bin->short();
	for ($j=0; $j<$rsrc_num; $j++) {
		$id = $bin->short();
		$index = $bin->short();
		$resources[$index]['id'] = $id;
		$resources[$index]['type'] = $type['name'];
		$r = $resources[$index];
		
		if ($type['name'] === 'tBMP') {
			echo "Resource $index ({$r['type']}): {$r['name']}\n";
			//echo "\toffset: {$r['offset']}, size: {$r['size']}\n";
			file_put_contents('output/'.$r['name'].'.tBMP', pack('H*', substr($bin->bin, $r['offset']*2, $r['size']*2)));
		}
	}
	$bin->cursor = $prior; // Restore

	$types[$type['name']] = $type;
}