<?php

class pixArray extends SplFixedArray {
	public function currentSet($val, $advance = true) {
		if ($this->valid()) {
			$this->offsetSet($this->key(), $val);
		}
		if ($advance) $this->next();
	}
	
	/**
	 * Copy a pixel from prior in the array
	 *
	 * @param int $num How far back in the array (counting from the end) should we pull from?
	 * @param boolean $advance Should the array pointer be advanced after this operation? Defaults to 'true'
	 */
	public function copyBack($num, $advance = true) {
		$this->currentSet($this->peekBack($num), $advance);
	}
	
	public function peekBack($num) {
		$cur = $this->key();
		if ($cur - $num < 0) throw new OutOfBoundsException("Cannot go back $num places, only $cur places exist at the moment");
		if ($this->valid()) return $this->offsetGet($cur-$num);
		return 0; // If outside the image bounds, just return some number
	}
	
	public function ringCopy($start, $count) {
		for($i=0; $i<$count; $i++) {
			$this->copyBack($start);
		}
	}
}