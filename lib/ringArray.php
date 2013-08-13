<?php
class ringArray implements ArrayAccess, Countable {
	private $buffer;
	private $cursor;
	
	public function __construct($size) {
		$this->buffer = new SplFixedArray($size);
		foreach($this->buffer as $i => $v) {
			$this->buffer[$i] = 0; // Initialize with zeroes, not NULLs
		}
		$this->cursor = 0;
	}
	
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