<?php
/**
 * Take a WDIB file, and insert it into a MOHAWK file, replacing a given Resource ID
 *
 * Usage: php insert_wdib.php [Path to MHK File] [Resource ID number] [Path to WDIB file]
 */

if (count($argv) < 4) exit("Not enough arguments given\n");
$mhk = $argv[1];
$rs_id = intval($argv[2]);
$wdib = $argv[3];
if (!file_exists($mhk)) exit("Mohawk file $mhk doesn't exist\n");
if (!file_exists($wdib)) exit("WDIB file $wdib doesn't exist\n");
if ($rs_id < 0) exit("Invalid Resource ID $rs_id\n");
echo "Replacing Resource {$rs_id} in {$mhk} with contents from {$wdib}...\n";

require('lib/binParser.php');

$dir = pathinfo($mhk);
$outfile = $dir['dirname'].'/'.$dir['filename'].'_mod.'.$dir['extension'];

$bin = unpack('H*', file_get_contents($mhk));
$mhk = new binParser($bin[1]);

if ($mhk->word() !== 'MHWK') exit("Not a valid MHWK file\n");
$file_size = $mhk->long(); // Size of the Mohawk file, minus 8 for the MHWK tag and this file length tag
if ($mhk->word() != 'RSRC') exit("RSRC header not found");
$rsrc = array();
$rsrc['version'] = $mhk->short();
$rsrc['compaction'] = $mhk->short();
$rsrc['size'] = $mhk->long();
$rsrc['offset'] = $mhk->long();

$file_table = array();
$file_table['offset'] = $mhk->short();
$file_table['size'] = $mhk->short();

echo "File Size given: {$file_size}, calculated: ".$mhk->count()."\n";

// Find the given Resource in the File table
$mhk->seek($rsrc['offset']+$file_table['offset']);
$num = $mhk->long();
if ($rs_id > $num) exit ("This Mohawk file only has $num resources; can't update number $rs_id...\n");
$file_data_start = $mhk->key();
$rs_offset = $file_data_start + 10*($rs_id-1); // Every File table entry is a 10-byte sequence; jump ahead to the one to be replaced 
echo "File data starts at $file_data_start\n";
echo "Resource $rs_id starts at $rs_offset\n";

$mhk->seek($rs_offset);
$rs = array();
$rs['bin_offset'] = $mhk->long();
$s1 = $mhk->short();
$s2 = $mhk->byte();
$rs['size'] = ($s2 << 16) | $s1;
$rs['flags'] = $mhk->byte();
$rs['unk'] = $mhk->short();

$bin = unpack('H*', file_get_contents($wdib));
$bin = $bin[1];
$rs['size'] = strlen($bin)/2;

echo "Updating Resource $rs_id binary offset to ".($file_size+8)." and size to {$rs['size']}\n";

// Rebuild the Mohawk file
$fh = fopen($outfile, 'w');
fwrite($fh, pack('H*', substr($mhk->bin, 0, 8))); // MHWK header
fwrite($fh, pack('H*', sprintf('%08X', $file_size+$rs['size']))); // File size
fwrite($fh, pack('H*', substr($mhk->bin, 16, $rs_offset*2-16))); // Clone data, up to Resource in question
fwrite($fh, pack('H*', sprintf('%08X', $file_size+8))); // Binary location is now at the end of what the file was
fwrite($fh, pack('H*', sprintf('%04X', $rs['size'])));
fwrite($fh, pack('H*', sprintf('%02X', $rs['size'] >> 16)));
fwrite($fh, pack('H*', sprintf('%02X', $rs['flags']))); // Clone other data
fwrite($fh, pack('H*', sprintf('%04X', $rs['unk']))); // Clone other data
fwrite($fh, pack('H*', substr($mhk->bin, $rs_offset*2+20))); // Clone rest of Mohawk file, after Resource file table entry
fwrite($fh, pack('H*', $bin)); // Write the WDIB contents at the end of the file
fclose($fh);
