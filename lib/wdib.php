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
	
	/**
	 * Debug view for the WDIB file
	 *
	 * Because WDIB files are compressed in chunks of 8 commands, but each command might be more than one pixel,
	 * this method echoes out the details of where those spans of commands are, for analyzing its effect on the final image
	 */
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
	
	/**
	 * Decorator function for the splay() method
	 */
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
						//echo ($offset+$i)." ($b))... \n";
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
	
	/**
	 * Compress binary data with LZ compression and output result
	 *
	 * @param binParser $bin file to be converted
	 * @return string hex-encoded string
	 */
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
				//echo "Searching for match for $binStr...\n";
				$ringStr = $ring->toString();
				$ringStr = $ringStr.$ringStr; // Double it, so the loop of it can be searched
				$matches = array();
				$start = 0;
				$match_o = 0;
				$match_l = 0;
				while(strlen($binStr) >= 6) {
					$o = strpos($ringStr, $binStr, $start);
					if ($o === false) break; // No match found
					if ($o/2 < $ring->key() && $o/2+3 >= $ring->key()) { // Overlaps the ring cursor; just skip, rather than deal with this complexity
						$start = $o+2;
						continue;
					}
					if ($o > self::CBUFFERSIZE*2) break; // Looped into next buffer
					if ($o % 2 !== 0) {
						$start = $o+1;
						continue;
					}
					// Match found; see how long it is
					$l = 4;
					while($l < self::MAX_STRING) {
						if ($o/2 < $ring->key() && $o/2+$l >= $ring->key()) break;
						if ($bin->getHex($l) !== substr($ringStr, $o, $l*2)) break;
						$l++;
					}
					$l--;
					if ($l > $match_l) {
						//echo "Match found at $o for $l bytes: ".$bin->getHex($l)." = ".substr($ringStr, $o, $l*2)."\n";
						$found = true;
						$match_o = $o/2;
						$match_l = $l;
					}
					$start = $o+$l*2;
				}
				if ($found === true) {
					$o = $match_o;
					$l = $match_l;
					//echo "Longest match is: $match_o for $match_l bytes\n";
					
					$o = $o - self::MAX_STRING;
					if ($o < 0) $o += self::CBUFFERSIZE;
					$suffix .= sprintf('%04X', (($l - self::MIN_STRING) << self::POS_BITS) | $o);
					for ($i=0; $i<$l; $i++) { // Add those bytes to the ring
						$b = $bin->byte();
						$ring->push($b);
					}
					$place++;
					continue;
				}
				// Use absolute byte
				//echo "None found\n";
				$b = $bin->byte();
				//echo "No match found; using absolute byte for $b at place $place.\n";
				$cmd = $cmd | (1 << $place); // Set place to 1
				$place++;
				$suffix .= sprintf('%02X', $b);
				$ring->push($b);
				
				if ($bin->key() >= $bin->count()) {
					// Out of data to convert
					break;
				}
			}
			$out .= sprintf('%02X', $cmd & 0xff) . $suffix;
			//if ($bin->key() > 1300) break;
		}
		return $out;
	}
}