<?php
// http://insidethelink.ortiche.net/wiki/index.php/Myst_WDIB_resources
// https://github.com/scummvm/scummvm/blob/master/engines/mohawk/bitmap.cpp#L220

require_once('ringArray.php');

/**
 * Parse a Mohawk WDIB (bitmap image) data stream
 */
class wdib {
	const LEN_BITS    = 6;
	const MIN_STRING  = 3;
	const POS_BITS    = 10; // (16 - LEN_BITS)
	const MAX_STRING  = 66; // ((1 << LEN_BITS) + MIN_STRING - 1)
	const CBUFFERSIZE = 1024; // (1 << POS_BITS)
	const POS_MASK    = 1023; // (CBUFFERSIZE - 1)
	
	public function __construct(binParser $bin) {
		$this->bin = $bin;
	}
	
	function splay() {
		$bin = $this->bin;
		$bin->rewind();
		
		$hex = $bin->getHex(4);
		$size = $bin->long();
		$this->_hexOut(0, $hex, 'Size: '.number_format($size));
		
		$ring = new ringArray(self::CBUFFERSIZE);
		$counter = 0;
		while ($bin->key() < $bin->count()) {
			$cmd = $bin->byte();
			$this->_hexOut($bin->key()-1, sprintf('%02x', $cmd), 'Run header: '.sprintf('%08b', $cmd));
			$cmd = $cmd | 0xff00;
			while ($cmd & 0x100) {
				if ($cmd & 1) {
					// Absolute Byte
					$b = $bin->byte();
					$this->_hexOut($bin->key()-1, sprintf('%02x', $b), 'Pixel '.$counter.': Raw byte '.$b);
					$counter++;
					$ring->push($b);
				} else {
					// Ring Buffer lookup
					// LLLLLLOO OOOOOOOO
					$hex = $bin->getHex(2);
					$b = $bin->short();
					$length = ($b >> self::POS_BITS) + self::MIN_STRING;
					$offset = ($b + self::MAX_STRING) & self::POS_MASK;
					$this->_hexOut($bin->key()-2, $hex, 'Pixel '.$counter.': Ring Buffer offset '.$offset.' for '.$length);
					for ($i = 0; $i<$length; $i++) {
						$b = $ring->offsetGet($offset+$i);
						$ring->push($b);
						$counter++;
					}
				}
				$cmd = $cmd >> 1;
			}
		}
	}
	
	private function _hexOut($cursor, $val, $label) {
		echo sprintf('%06d', $cursor).' | ';
		echo implode(' ', str_split($val, 2));
		echo ' ; '.$label."\n";
	}
	
	/**
	 * Parse the data stream and return an image
	 * @return string binary representation of the WDIB contents (likely a BMP file)
	 */
	function convert() {
		$bin = $this->bin;
		$bin->rewind();
		
		$size = $bin->long();
		$out = '';
		$ring = new ringArray(self::CBUFFERSIZE);
		while ($bin->key() < $bin->count()) {
			//echo "Cursor: {$bin->cursor} of ".(strlen($bin->bin)/2)."\n";
			$cmd = $bin->byte();
			//echo "Command: $cmd (".decbin($cmd).")\n";
			$cmd = $cmd | 0xff00;
			//echo "Output: $out\n";
			while ($cmd & 0x100) {
				if ($cmd & 1) {
					// Absolute Byte
					$b = $bin->byte();
					//echo sprintf('0x%02X... ', $b);
					$out .= sprintf('%02X', $b);
					$ring->push($b);
				} else {
					// Ring Buffer lookup
					// LLLLLLOO OOOOOOOO
					$b = $bin->short();
					$length = ($b >> self::POS_BITS) + self::MIN_STRING;
					$offset = ($b + self::MAX_STRING) & self::POS_MASK;
					for ($i = 0; $i<$length; $i++) {
						$b = $ring->offsetGet($offset+$i);
						//echo ($offset+$i)." ($b))... ";
						$out .= sprintf('%02X', $b);
						$ring->push($b);
					}
				}
				$cmd = $cmd >> 1;
				//echo "\n";
			}
			//echo "\n";
		}
		return pack('H*', $out);
	}
	
	static function createFromBMP(binParser $bin) {
		$bin->rewind();
		$out = binParser::LElong($bin->count());
		$ring = new ringArray(self::CBUFFERSIZE);
		$ticker = 0;
		while ($bin->key() < $bin->count()) {
			$ticker++;
			if ($ticker % 100 == 0) echo "Cursor at ".number_format($bin->key())." of ".number_format($bin->count())."...\n";
			$cmd = 0;
			$place = 0;
			$suffix = '';
			while ($place < 8) {
				// See if there's a match at least 3 bytes long
				$found = false;
				$binStr = $bin->getHex(3);
				//echo "Searching for match for $binStr...";
				$ringStr = $ring->toString();
				$ringStr = $ringStr.$ringStr; // Double it, so the loop of it can be searched
				$matches = array();
				$start = 0;
				while(true) {
					$o = strpos($ringStr, $binStr, $start);
					if ($o === false) break; // No match found
					if ($o > self::CBUFFERSIZE*2) break; // Looped into next buffer
					if ($o % 2 !== 0) {
						$start = $o+1;
						continue;
					}
					// Match found; see how long it is
					$o = $o/2; // Convert offset to bytes, not hex nibbles
					$found = true;
					$l = 4;
					while($l <= self::MAX_STRING) {
						if ($bin->getHex($l) !== substr($ringStr, $o*2, $l*2)) break;
						$l++;
					}
					$l--;
					//echo "Match found at $o for $l bytes\n";
					$matches[] = array('start' => $o, 'length' => $l);
					$start = ($o+1)*2;
				}
				if ($found === true) {
					// Look for longest match
					//echo "Longest match is: ";
					usort($matches, function($a, $b) {
						if ($a['length'] == $b['length']) return $a['start'] - $b['start'];
						return $a['length'] - $a['length']; // Sort largest length to the bottom
					});
					$m = array_pop($matches); // Grab the longest one
					$o = $m['start'];
					$l = $m['length'];
					//echo "$o for $l bytes\n";
					
					$o = $o - self::MAX_STRING;
					if ($o < 0) $o += self::CBUFFERSIZE;
					$suffix .= sprintf('%04X', (($l - self::MIN_STRING) << self::POS_BITS) | $o);
					for ($i=0; $i<$l; $i++) { // Add those bytes to the ring
						$ring->push($bin->byte());
					}
					$place++;
					continue;
				}
				// Use absolute byte
				$b = $bin->byte();
				//echo "No match found; using absolute byte for $b at place $place.\n";
				$cmd = $cmd | (1 << $place); // Set place to 1
				$place++;
				$suffix .= sprintf('%02X', $b);
				$ring->push($b);
				
				if ($bin->key() >= $bin->count()) {
					// Out of data to convert
					while (!($cmd & 0x8000)) {
						$cmd = $cmd << 1; // Add zeroes to the end
					}
				}
			}
			$out .= sprintf('%02X', $cmd & 0xff) . $suffix;
			//if ($bin->key() > 1300) break;
		}
		return $out;
	}
}