<?php

class binParser {
	public $bin;
	public $cursor;
	
	public function __construct($bin) {
		$this->bin = $bin;
		$this->cursor = 0;
	}
	
	/**
	 * Grab bytes from current cursor
	 *
	 * Return the value of the next string of bytes at the cursor, converted to decimal as a big-endian byte string.
	 * @param integer $bytes number of bytes to return; defaults to 1
	 */
	public function get($bytes = 1) {
		$hex = substr($this->bin, $this->cursor*2, $bytes*2);
		return hexdec($hex);
	}
	
	public function byte($shift = true) {
		$rs = $this->get(1);
		if ($shift) $this->cursor += 1;
		return $rs;
	}
	
	public function short($shift = true) {
		$rs = $this->get(2);
		if ($shift) $this->cursor += 2;
		return $rs;
	}
	
	public function long($shift = true) {
		$rs = $this->get(4);
		if ($shift) $this->cursor += 4;
		return $rs;
	}
	
	public function str($shift = true) {
		$str = '';
		$i = $this->cursor;
		while(true) {
			$chr = hexdec(substr($this->bin, $i*2, 2));
			//echo "Cursor at $i: $chr\n";
			if ($chr == 0) break;
			if ($chr < 32 || $chr > 126) {
				$str .= '?';
			} else {
				$str .= chr($chr);
			}
			$i++;
		}
		if ($shift) $this->cursor = $i;
		return $str;
	}
	
	public function word($shift = true) {
		$hex = substr($this->bin, $this->cursor*2, 8);
		$ascii = pack('H*', $hex);
		if ($shift) $this->cursor += 4;
		return $ascii;
	}
}