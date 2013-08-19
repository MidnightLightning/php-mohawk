<?php

/**
 * Utility class for dealing with binary data
 * @TODO To maximize memory usage, the SplFileObject class (http://us3.php.net/manual/en/class.splfileobject.php) could be used to read the file directly, without having it all cached to memory
 */
class binParser implements SeekableIterator, Countable {
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
	
	public function current() {
		return $this->get(1);
	}
	public function key() {
		return $this->cursor;
	}
	public function next() {
		++$this->cursor;
	}
	public function rewind() {
		$this->cursor = 0;
	}
	public function valid() {
		return ($this->key() < $this->count());
	}
	public function seek($o) {
		if ($o > $this->count()) throw new OutOfBoundsException();
		$this->cursor = $o;
	}
	public function count() {
		return strlen($this->bin)/2;
	}
	
	/**
	 * Grab bytes from current cursor
	 *
	 * Return the value of the next string of bytes at the cursor, converted to decimal as a big-endian byte string.
	 * @param integer $bytes Number of bytes to return; defaults to 1
	 * @return integer Decimal interpretation of byte value
	 */
	public function get($bytes = 1) {
		$hex = substr($this->bin, $this->cursor*2, $bytes*2);
		return hexdec($hex);
	}
	
	/**
	 * Grab bytes from the current cursor, as a hex string
	 *
	 * Return the hex string of bytes at the cursor.
	 * @param integer $bytes number of bytes to return; defaults to 1
	 * @return string Hex string of bytes
	 */
	public function getHex($bytes = 1) {
		return substr($this->bin, $this->cursor*2, $bytes*2);
	}
	
	/**
	 * Get one byte (16-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 * @return integer
	 */
	public function byte($shift = true) {
		$rs = $this->get(1);
		if ($shift) $this->cursor += 1;
		return $rs;
	}
	
	/**
	 * Get a "short" integer (two bytes; 32-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 * @return integer
	 */
	public function short($shift = true) {
		$rs = $this->get(2);
		if ($shift) $this->cursor += 2;
		return $rs;
	}
	
	/**
	 * Get a "long" integer (four bytes; 64-bits) of data from the current cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 * @return integer
	 */
	public function long($shift = true) {
		$rs = $this->get(4);
		if ($shift) $this->cursor += 4;
		return $rs;
	}
	
	/**
	 * Get a null-terminated string, starting at the cursor
	 * @param boolean $shift Should the cursor be incremented after this action? Defaults to 'true'
	 * @return string
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
	 * @return string
	 */
	public function word($shift = true) {
		$hex = substr($this->bin, $this->cursor*2, 8);
		$ascii = pack('H*', $hex);
		if ($shift) $this->cursor += 4;
		return $ascii;
	}
	
	static function LEshort($i) {
		return self::switchEndian(sprintf('%04X', $i));
	}
	static function LElong($i) {
		return self::switchEndian(sprintf('%08X', $i));
	}
	
	static function switchEndian($str) {
		$a = str_split($str, 2);
		return implode('', array_reverse($a));
	}
}