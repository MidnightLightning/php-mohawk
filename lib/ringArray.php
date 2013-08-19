<?php
/**
 * Custom class for handling a ring buffer of data
 *
 * This class has a private member that's a SplFixedArray, rather than extending SplFixedArray directly.
 * The native SplFixedArray doesn't have a seek() method, so can't have the cursor set explicitly, so
 * this class adds its own cursor, and uses that to implement the SeekableIterator interface
 */
class ringArray implements ArrayAccess, SeekableIterator, Countable {
	private $buffer;
	private $cursor;
	
	/**
	 * Create a new buffer, and initialize it to all zeroes
	 */
	public function __construct($size) {
		$this->buffer = new SplFixedArray($size);
		foreach($this->buffer as $i => $v) {
			$this->buffer[$i] = 0; // Initialize with zeroes, not NULLs
		}
		$this->cursor = 0;
	}
	
	/**
	 * Add value to the array, at the current index
	 */
	public function push($v) {
		$this->buffer->offsetSet($this->cursor, $v);
		$this->cursor = $this->_loopOffset($this->cursor + 1);
	}
	
	public function toArray() {
		return $this->buffer;
	}
	public function toString() {
		return $this->getSlice(0, $this->buffer->getSize());
	}
	
	public function offsetExists($o) {
		return $this->buffer->offsetExists($this->_loopOffset($o));
	}
	public function offsetGet($o) {
		return $this->buffer->offsetGet($this->_loopOffset($o));
	}
	public function offsetSet($o, $v) {
		return $this->buffer->offsetSet($this->_loopOffset($o), $v);
	}
	public function offsetUnset($o) {
		return $this->buffer->offsetUnset($this->_loopOffset($o));
	}
	public function current() {
		return $this->buffer->offsetGet($this->cursor);
	}
	public function key() {
		return $this->cursor;
	}
	public function next() {
		$this->cursor = $this->_loopOffset($this->cursor+1);
	}
	public function rewind() {
		$this->cursor = 0;
	}
	public function valid() {
		return ($this->key() < $this->count());
	}
	public function seek($o) {
		$this->cursor = $this->_loopOffset($o);
	}
	public function count() {
		return $this->buffer->count();
	}
	
	/**
	 * Grab string of bytes as Hex
	 *
	 * Return the hex string of bytes.
	 * @param integer $start offset start
	 * @param integer $length number of bytes to return; defaults to 1
	 * @return string Hex string of bytes
	 */
	public function getSlice($start, $length = 1) {
		$out = '';
		for($i=$start; $i<$start+$length; $i++) {
			$out .= sprintf('%02X', $this->buffer->offsetGet($i));
		}
		return $out;
	}
	
	/**
	 * Ensure the offset is within range
	 *
	 * This is the main logic of the "ring"; given any integer as offset,
	 * this function will roll that number over to be a number in range of the buffer
	 */
	private function _loopOffset($o) {
		$size = $this->buffer->getSize();
		while ($o < 0) {
			$o += $size;
		}
		while ($o >= $size) {
			$o -= $size;
		}
		return $o;
	}
}
?>