<?php
// http://insidethelink.ortiche.net/wiki/index.php/Myst_WDIB_resources
// https://github.com/scummvm/scummvm/blob/master/engines/mohawk/bitmap.cpp#L220

require_once('ringArray.php');

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
	
	function convert() {
		$bin = $this->bin;
		$bin->cursor = 0; // Reset
		
		$size = $bin->long();
		$out = '';
		$ring = new ringArray(1024);
		while ($bin->cursor*2 < strlen($bin->bin)) {
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
}