<?php

/**
 * Custom class for handling an array of pixel data
 *
 * When building an array of pixels from the compressed tBMP data, using standard PHP arrays with array_slice() and array_merge() actions
 * really hurts for memory use (since you're constantly increasing the size of the array, needing to chew up more memory and defragment it).
 * Switching to a SplFixedArray alleviates the memory issue and results in MUCH faster processing.
 *
 * This class extends the basic SplFixedArray with a few convenience functions for counting from the end of the array, as needed for Mohawk Bitmaps.
 * Note, these functions assume the array is being filled by using the self::rewind() and self::next() functions (the Iterator interface methods)
 */
class pixArray extends SplFixedArray {
	/**
	 * Set the current array index to the supplied value
	 * @param mixed $val The value to set
	 * @param boolean $advance Should the array index pointer be incremented after this action? Defaults to 'true'
	 */
	public function currentSet($val, $advance = true) {
		if ($this->valid()) { // Mohawk bitmaps sometimes set data that's outside the bounds of the image. If we're outside the bounds of the image, just ignore
			$this->offsetSet($this->key(), $val);
		}
		if ($advance) $this->next();
	}
	
	/**
	 * Copy a pixel from prior in the array
	 *
	 * @param int $num How far back in the array (counting from the end) should we pull from?
	 * @param boolean $advance Should the array index pointer be incremented after this action? Defaults to 'true'
	 */
	public function copyBack($num, $advance = true) {
		$this->currentSet($this->peekBack($num), $advance);
	}
	
	/**
	 * Return a value from prior in the array
	 * @param int $num How far back in the array (counting from the end) should we pull from?
	 */
	public function peekBack($num) {
		$cur = $this->key();
		if ($cur - $num < 0) throw new OutOfBoundsException("Cannot go back $num places, only $cur places exist at the moment");
		if ($this->valid()) return $this->offsetGet($cur-$num);
		return 0; // If outside the image bounds, just return some number
	}
	
	/**
	 * Given a starting point prior in the array, copy a certain number of elements forward
	 *
	 * This is labeled as a "ring" copy, since sometimes in a Mowhawk bitmap, the number of elements to copy is more than the remaining elements in the array (count > start)
	 * In that case, the newly-copied elements to the end of the array get copied again, until the required count is met
	 * @param int $start How far back in the array (counting from the end) should we pull from?
	 * @param int $count How many elements to copy?
	 */
	public function ringCopy($start, $count) {
		for($i=0; $i<$count; $i++) {
			$this->copyBack($start);
		}
	}
}