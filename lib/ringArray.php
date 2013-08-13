<?php
/**
 * Custom class for handling a ring buffer of data
 *
 * This class has a private member that's a SplFixedArray, rather than extending SplFixedArray directly.
 * Doing it this way, the class is not Iterable (which is fine; it would go in a loop endlessly),
 * and several functions are just pass-through (with a filter to make sure the offset value is clean)
 */
class ringArray implements ArrayAccess, Countable {
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
	
	public function count() {
		return $this->buffer->count();
	}
	
	public function toArray() {
		return $this->buffer;
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