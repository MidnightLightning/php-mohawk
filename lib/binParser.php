<?php

/**
 * Utility class for dealing with binary data
 */
class binParser {
	public $bin;
	
	/**
	 * Current cursor
	 *
	 * Holds the number of bytes from the beginning of the stream the current execution is looking at.
	 * Because the self::bin variable has a string of hexadecimal characters (two characters per byte), the actual offset in the string is twice the value in this variable.
	 */
	public $cursor;
	
	/**
	 * Create a new parser
	 *
	 * @param string $bin String of Hexadecimal characters (high-nibble first) representing the binary data to parse. This is used as a string due to it being much more efficient than working with an array of decimal values representing the binary data.
	 */
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
	
	/**
	 * Get one byte (16-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 */
	public function byte($shift = true) {
		$rs = $this->get(1);
		if ($shift) $this->cursor += 1;
		return $rs;
	}
	
	/**
	 * Get a "short" integer (two bytes; 32-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 */
	public function short($shift = true) {
		$rs = $this->get(2);
		if ($shift) $this->cursor += 2;
		return $rs;
	}
	
	/**
	 * Get a "long" integer (four bytes; 64-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 */
	public function long($shift = true) {
		$rs = $this->get(4);
		if ($shift) $this->cursor += 4;
		return $rs;
	}
	
	/**
	 * Get a null-terminated string, starting at the cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 */
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
	
	/**
	 * Get a "word" string (four bytes; 64-bits, converted to ASCII) from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 */
	public function word($shift = true) {
		$hex = substr($this->bin, $this->cursor*2, 8);
		$ascii = pack('H*', $hex);
		if ($shift) $this->cursor += 4;
		return $ascii;
	}
}