<html>
<head>
<title>Mohawk Details</title>
<style>
.console { margin-bottom:2em; font-family:'Courier New'; font-size:10px; line-height:12px; }
.console b { font-weight:normal; display:inline-block; padding:0.25 0.3em; }
.range { display:inline; background-color:#FFC; margin:0 -1px; border:solid 1px #CCA; }
.range.long { background-color:#CFC; }
.range.short { background-color:#DED; }
.range.binary { background-color:#FCC; }
</style>
</head>
<body>
<?php

if (!isset($_GET['f']) || empty($_GET['f'])) {
	echo "No file specified";
	exit;
}

$src = 'data/'.$_GET['f'];
if (!file_exists($src)) {
	echo "No such file";
	exit;
}

require('lib/binParser.php');
ini_set("memory_limit","512M");

$bin = unpack('H*', file_get_contents($src));
$bin = new binParser($bin[1]);

if ($bin->word() !== 'MHWK') exit("Not a valid MHWK file\n");
$file_size = $bin->long();
if ($bin->word() != 'RSRC') exit("RSRC header not found");

$ranges = array();
$ranges[] = array('start' => 0, 'length' => 4, 'class'=>'word', 'note'=>'MHWK');
$ranges[] = array('start' => 4, 'length' => 4, 'class'=>'long', 'note'=>'File size: '.number_format($file_size));
$ranges[] = array('start' => 8, 'length' => 4, 'class'=>'word', 'note'=>'RSRC');
$ranges[] = array('start' => $bin->key(), 'length' => 2, 'class'=>'short', 'note'=>'Version: '.number_format($bin->short()));
$ranges[] = array('start' => $bin->key(), 'length' => 2, 'class'=>'short', 'note'=>'Compaction: '.number_format($bin->short()));
$rsrc_end = $bin->long();
$ranges[] = array('start' => $bin->key()-4, 'length' => 4, 'class'=>'long', 'note'=>'RSRC End: '.number_format($rsrc_end));
$rsrc_offset = $bin->long();
$ranges[] = array('start' => $bin->key()-4, 'length' => 4, 'class'=>'long', 'note'=>'RSRC Offset: '.number_format($rsrc_offset));
$ranges[] = array('start' => $rsrc_offset, 'length' => $rsrc_end-$rsrc_offset, 'class' => 'block', 'note' => 'RSRC table');

$file_table_offset = $bin->short();
$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=>'File Table offset: '.number_format($file_table_offset));
$file_table_size = $bin->short();
$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=>'File Table size: '.number_format($file_table_size));

// Parse the file table
$bin->seek($rsrc_offset+$file_table_offset);
$num = $bin->long();
$ranges[] = array('start' => $rsrc_offset+$file_table_offset, 'length' => 4, 'class'=>'long', 'note'=>'File Table count: '.number_format($num));

$resources = array();
for($i=1; $i<=$num; $i++) {
	$cur_start = $bin->key();
	$resources[$i] = array();
	$resources[$i]['offset'] = $bin->long();
	$s1 = $bin->short();
	$s2 = $bin->byte();
	$resources[$i]['size'] = ($s2 << 16) | $s1;
	$flags = $bin->byte();
	$tmp = $bin->short();
	$ranges[] = array('start' => $cur_start, 'length' => 4, 'class' => 'long', 'note' => 'Resource '.$i.' file offset: '.number_format($resources[$i]['offset']));
	$ranges[] = array('start' => $cur_start+4, 'length' => 2, 'class' => 'short', 'note' => 'Resource '.$i.' size (low bits): '.number_format($s1));
	$ranges[] = array('start' => $cur_start+6, 'length' => 1, 'class' => 'short', 'note' => 'Resource '.$i.' size (high bits): '.number_format($s2));
	$ranges[] = array('start' => $cur_start+7, 'length' => 1, 'class' => 'short', 'note' => 'Resource '.$i.' flags: '.$flags.' ('.decbin($flags).')');
	$ranges[] = array('start' => $cur_start+8, 'length' => 2, 'class' => 'short', 'note' => 'Resource '.$i.' unknown: '.number_format($tmp));
}

// Parse the Type table
$bin->seek($rsrc_offset);
$names_offset = $bin->short();
$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=>'Name Table offset: '.number_format($names_offset));
$num = $bin->short();
$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=>'Type Table count: '.number_format($num));
$types = array();
for ($i=0; $i<$num; $i++) {
	$cur_start = $bin->key();
	$type = array();
	$type['name'] = $bin->word();
	$type['resource_offset'] = $bin->short();
	$type['name_offset'] = $bin->short();
	$ranges[] = array('start' => $cur_start, 'length' => 4, 'class' => 'word', 'note' => 'Type '.$i.' name: '.$type['name']);
	$ranges[] = array('start' => $cur_start+4, 'length' => 2, 'class' => 'short', 'note' => 'Type '.$type['name'].' Resource offset: '.$type['resource_offset']);
	$ranges[] = array('start' => $cur_start+6, 'length' => 2, 'class' => 'short', 'note' => 'Type '.$type['name'].' Names offset: '.$type['name_offset']);
	$types[$type['name']] = $type;
}

// Parse each Type
foreach($types as $t) {
	// Parse Names
	$cur_start = $rsrc_offset+$t['name_offset'];
	$bin->seek($cur_start);
	$ranges[] = array('start' => $cur_start, 'length' => 1, 'class' => 'block', 'note' => 'Start of Type '.$type['name'].' Name Table start');
	$num = $bin->short();
	$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=> 'Type '.$t['name'].' Names count: '.number_format($num));
	for($i=0; $i<$num; $i++) {
		$o = $bin->short();
		$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=> 'Type '.$t['name'].' Name '.$i.' offset: '.number_format($o));
		$index = $bin->short();
		$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=> 'Type '.$t['name'].' Name '.$i.' Resource index: '.number_format($index));
		
		$prior = $bin->key(); // Save
		$str_start = $rsrc_offset+$names_offset+$o;
		$bin->seek($str_start);
		$s = $bin->str();
		$ranges[] = array('start' => $str_start, 'length' => $bin->key()-$str_start, 'class' => 'word', 'note' => 'Resource '.$index.' ('.$t['name'].') name: '.$s);
		$bin->seek($prior); // Restore
	}
	
	// Parse Resources
	$cur_start = $rsrc_offset+$t['resource_offset'];
	$ranges[] = array('start' => $cur_start, 'length' => 1, 'class' => 'block', 'note' => 'Start of Type '.$type['name'].' Resource Table start');	
	$bin->seek($cur_start);
	$num = $bin->short();
	$ranges[] = array('start' => $bin->key()-2, 'length' => 2, 'class'=>'short', 'note'=> 'Type '.$t['name'].' Resources count: '.number_format($num));
	for($i=0; $i<$num; $i++) {
		$start = $bin->key();
		$id = $bin->short();
		$index = $bin->short();
		$ranges[] = array('start' => $start, 'length' => 2, 'class' => 'short', 'note' => 'Resource '.$index.' ('.$t['name'].') ID: '.$id);
		$ranges[] = array('start' => $start+2, 'length' => 2, 'class' => 'short', 'note' => 'Type '.$t['name'].' Resource index '.$i.' assignment: '.$index);
		$r = $resources[$index];
		$ranges[] = array('start' => $r['offset'], 'length' => $r['size'], 'class' => 'binary', 'note' => 'Resource '.$index.' ('.$t['name'].') binary data');
	}	
}
	

// Output
usort($ranges, function($a, $b) {
	return $a['start'] - $b['start'];
});
echo "<table id=\"ranges\">\n";
foreach($ranges as $r) {
	echo "<tr><td style=\"text-align:right;\">".number_format($r['start'])."</td><td>&rarr;</td><td>".number_format($r['start'] + $r['length'])."</td><td style=\"text-align:right;\">(".number_format($r['length'])."):</td><td>".$r['note']."</td></tr>";
}
echo "</table>\n";

echo "<h2>Beginning of file:</h2>\n";
drawConsole(0, 1000);

echo "<h2>Start of Resource table:</h2>\n";
drawConsole($rsrc_offset-50, 2000);

echo "<h2>Name Table:</h2>\n";
drawConsole($rsrc_offset+$names_offset-50, 2000);

echo "<h2>File Table:</h2>\n";
drawConsole($rsrc_offset+$file_table_offset-50, 2000);

class rangeFilter {
	function __construct($start, $length) {
		$this->start = $start;
		$this->length = $length;
	}
	
	function filter($i) {
		return ($i['start'] >= $this->start && $i['start'] <  $this->start+$this->length);
	}
}

function drawConsole($start, $length) {
	global $bin, $ranges;
	echo "<div class=\"console\">";
	$sub_ranges = array_filter($ranges, array(new rangeFilter($start, $length), 'filter'));
	foreach(new LimitIterator($bin, $start, $length) as $i => $byte) {
		foreach($sub_ranges as $r) {
			if ($r['start'] == $i) {
				echo "<span class=\"range {$r['class']}\" title=\"({$r['start']},{$r['length']}) {$r['note']}\">";
			}
			if ($r['start'] + $r['length'] == $i) {
				echo "</span>";
			}
		}
		echo '<b>'.sprintf('%02X', $byte).'</b>';
	}
	echo "</div>";
	
}

?>
</body>
</html>