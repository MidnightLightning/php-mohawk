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
		while ($bin->key() < $bin->count()) {
			echo "Cursor at ".number_format($bin->key())." of ".number_format($bin->count())."...\n";
			$cmd = 0;
			$place = 0;
			$suffix = '';
			while ($place < 8) {
				// See if there's a match at least 3 bytes long
				$found = false;
				$binStr = $bin->getHex(3);
				$ringStr = $ring->toString();
				$ringStr = $ringStr.$ringStr; // Double it, so the loop of it can be searched
				$start = 0;
				while(true) {
					$o = strpos($ringStr, $binStr, $start);
					if ($o === false) break; // No match found
					if ($o % 2 !== 0) {
						$start = $o+1;
						continue;
					}
					// Match found; see how long it is
					$o = $o/2; // Convert offset to bytes, not hex nibbles
					$found = true;
					$place++;
					$l = 4;
					while(true) {
						if ($bin->getHex($l) !== substr($ringStr, $o*2, $l*2)) break;
						$l++;
					}
					$l--;
					echo "Match found at $o for $l bytes\n";
					$suffix .= sprintf('%04X', (($l - self::MIN_STRING) << self::POS_BITS) | ($o - self::MAX_STRING));
					for ($i=0; $i<$l; $i++) { // Add those bytes to the ring
						$ring->push($bin->byte());
					}
					break;
				}
				if ($found === false) {
					// Use absolute byte
					$b = $bin->byte();
					//echo "\nNo match found; using absolute byte for $b.\n";
					$cmd = $cmd | (1 << $place); // Set place to 1
					$place++;
					$suffix .= sprintf('%02X', $b);
					$ring->push($b);
				}
				if ($bin->key() >= $bin->count()) {
					// Out of data to convert
					while (!($cmd & 0x8000)) {
						$cmd = $cmd << 1; // Add zeroes to the end
					}
				}
			}
			$out .= sprintf('%02X', $cmd & 0xff) . $suffix;
		}
		return $out;
	}
}