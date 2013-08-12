<?php
// http://insidethelink.ortiche.net/wiki/index.php/Mohawk_Bitmaps
// http://www.mystellany.com/riven/imageformat/

require_once('pixArray.php');

/**
 * Parse a Mohawk tBMP (bitmap image) data stream
 */
class tBMP {
	public function __construct(binParser $bin) {
		$this->bin = $bin;
	}
	
	/**
	 * Parse the data stream and return an image
	 * @return GD image resource (palette-based)
	 */
	function convert() {
		$bin = $this->bin;
		$bin->cursor = 0; // Reset
		//echo $bin->bin."\n";
		
		$this->width = $bin->short() & 0x3ff;
		$this->height = $bin->short() & 0x3ff;
		$bytes_per_row = $bin->short() & 0x3ff;
		$compression = $bin->short();
		
		echo "{$this->width} x {$this->height}, {$bytes_per_row} bpr\n";
		echo "Compression flags: {$compression}\n";
		$bit_depth = $compression & 0x7;
		if ($bit_depth !== 2) exit("unexpected bit depth! ({$bit_depth})\n"); // All images in Riven are 8bpp
		$secondary_compression = ($compression & 0xF0) >> 4;
		if ($secondary_compression !== 0) exit("secondary compression used! ({$secondary_compression})\n"); // All images in Riven don't use secondary compression
		$primary_compression = ($compression & 0xF00) >> 8;
		if ($primary_compression !== 4) exit("non-Riven compression used! ({$primary_compression})\n");
		
		$this->img = imagecreate($this->width, $this->height);
		
		$palette_size = $bin->short();
		$palette_bits_per_color = $bin->byte();
		if ($palette_bits_per_color !== 24) exit("non-standard bits-per-color! ({$palette_bits_per_color})\n");
		$palette_color_count = $bin->byte();
		echo "Palette size: $palette_color_count\n";
		for ($j=0; $j<=$palette_color_count; $j++) {
			$blu = $bin->byte();
			$grn = $bin->byte();
			$red = $bin->byte();
			imagecolorallocate($this->img, $red, $grn, $blu); // no need to save the return value; we're just building the palette in order, so the indexes will automatically line up
		}
		
		$total_pixels = $this->width*$this->height;
		$img_data = new pixArray($total_pixels);
		$img_data->rewind();
		
		$bin->cursor += 4; // Starts with four unknown bytes
		while ($bin->cursor*2 < strlen($bin->bin)) {
			echo number_format($img_data->key()).' pixels of '.number_format($total_pixels)."\n";
			echo "Cursor: {$bin->cursor} of ".(strlen($bin->bin)/2)."\n";
			$cmd = $bin->byte();
			echo "(0x".dechex($cmd).") ";
			if ($cmd == 0) {
				// End of image data
				echo "End of image data\n";
				break;
			} elseif ($cmd <= 0x3f) {
				// Output [CMD] pixel duplets
				$pixels = $cmd*2;
				echo "Grab $pixels raw pixel values...\n";
				for ($j=0; $j<$pixels; $j++) {
					$img_data->currentSet($bin->byte());
				}
			} elseif ($cmd <= 0x7f) {
				// Repeat the last 2 pixels [CMD & 0x3F] times
				$num = $cmd & 0x3f;
				echo "Repeat 2 pixels $num times...\n";
				for ($j=0; $j<$num*2; $j++) {
					$img_data->copyBack(2);
				}
			} elseif ($cmd <= 0xbf) {
				// Repeat the last 4 pixels [CMD & 0x3F] times
				$num = $cmd & 0x3f;
				echo "Repeat 4 pixels $num times...\n";
				for ($j=0; $j<$num*4; $j++) {
					$img_data->copyBack(4);
				}
			} else {
				// Subcommands follow
				$num = $cmd & 0x3f;
				echo "Parse $num subcommands...\n";
				$done = 0;
				while ($done < $num) {
					$cmd = $bin->byte();
					//echo "0x".dechex($cmd)."... ";
					if ($cmd == 0) {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					} elseif ($cmd <= 0xf) {
						// Repeat duplet [CMD], counting from last
						$img_data->copyBack($cmd*2);
						$img_data->copyBack($cmd*2);
					} elseif ($cmd == 0x10) {
						// Repeat last, but change second pixel
						$img_data->copyBack(2);
						$img_data->currentSet($bin->byte());
					} elseif ($cmd <= 0x1f) {
						// First pixel from last duplet, then [CMD & 0xf] pixel, counting from last
						$img_data->copyBack(2);
						$img_data->copyBack($cmd & 0xf);
					} elseif ($cmd <= 0x2f) {
						// Repeat last duplet, but add [CMD & 0xf] to second pixel palette number
						$img_data->copyBack(2);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + ($cmd & 0xf));
					} elseif ($cmd <= 0x3f) {
						// Repeat last duplet, but subtract [CMD & 0xf] from second pixel palette number
						$img_data->copyBack(2);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - ($cmd & 0xf));
					} elseif ($cmd == 0x40) {
						// Repeat last, but change first pixel
						$img_data->currentSet($bin->byte());
						$img_data->copyBack(2);
					} elseif ($cmd <= 0x4f) {
						// Pixel at [CMD & 0xf], counting from last, then second pixel from last duplet
						$img_data->copyBack($cmd & 0xf);
						$img_data->copyBack(2);
					} elseif ($cmd == 0x50) {
						// Two absolute pixels
						$img_data->currentSet($bin->byte());
						$img_data->currentSet($bin->byte());
					} elseif ($cmd <= 0x57) {
						// Relative pixel at [CMD & 0x7], then absolute pixel
						$img_data->copyBack($cmd & 0x7);
						$img_data->currentSet($bin->byte());
					} elseif ($cmd <= 0x5f) {
						// Absolute pixel, then relative pixel
						$img_data->currentSet($bin->byte());
						$img_data->copyBack($cmd & 0x7);
					} elseif ($cmd <= 0x6f) {
						// Absolute pixel, then second pixel of last duplet, plus [CMD & 0xf]
						$img_data->currentSet($bin->byte());
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + ($cmd & 0xf));
					} elseif ($cmd <= 0x7f) {
						// Absolute pixel, then second pixel of last duplet, minus [CMD & 0xf]
						$img_data->currentSet($bin->byte());
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - ($cmd & 0xf));
					} elseif ($cmd <= 0x8f) {
						// Repeat last duplet, but add [CMD & 0xf] to first pixel palette number
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + ($cmd & 0xf));
						$img_data->copyBack(2);
					} elseif ($cmd <= 0x9f) {
						// First pixel from last duplet, plus [CMD & 0xf], then absolute pixel
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + ($cmd & 0xf));
						$img_data->currentSet($bin->byte());
					} elseif ($cmd == 0xa0) {
						// Repeat last duplet, with plus/plus shift
						$b = $bin->byte();
						$x = ($b & 0xf0) >> 4;
						$y = ($b & 0x0f);
						
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + $x);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + $y);
					} elseif ($cmd <= 0xa3) {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					} elseif ($cmd <= 0xa7) {
						// Output 3 pixels starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 3);
					} elseif ($cmd <= 0xab) {
						// Output 4 pixels starting at previous
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 4);
					} elseif ($cmd <= 0xaf) {
						// Output 5 pixels starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 5);
					} elseif ($cmd == 0xb0) {
						// Repeat last duplet, with plus/minus shift
						$b = $bin->byte();
						$x = ($b & 0xf0) >> 4;
						$y = ($b & 0x0f);
						
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + $x);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - $y);
					} elseif ($cmd <= 0xb3) {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					} elseif ($cmd <= 0xb7) {
						// Output 6 pixels, starting at previous
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 6);
					} elseif ($cmd <= 0xbb) {
						// Output 7 pixels, starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 7);
					} elseif ($cmd <= 0xbf) {
						// Output 8 pixels, starting at previous
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 8);
					} elseif ($cmd <= 0xcf) {
						// Repeat last duplet, subtracting [CMD & 0xf] from first pixel
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - ($cmd & 0xf));
						$img_data->copyBack(2);
					} elseif ($cmd <= 0xdf) {
						// First pixel of last duplet minus [CMD & 0xf], then absolute pixel
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - ($cmd & 0xf));
						$img_data->currentSet($bin->byte());
					} elseif ($cmd == 0xe0) {
						// Repeat last duplet, with minus/plus shift
						$b = $bin->byte();
						$x = ($b & 0xf0) >> 4;
						$y = ($b & 0x0f);
						
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - $x);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p + $y);
					} elseif ($cmd <= 0xe3) {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					} elseif ($cmd <= 0xe7) {
						// Output 9 pixels, starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 9);
					} elseif ($cmd <= 0xeb) {
						// Output 10 pixels, starting at previous
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 10);
					} elseif ($cmd <= 0xef) {
						// Output 11 pixels, starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 11);
					} elseif ($cmd == 0xf0) {
						// Repeat last duplet, with minus/minus shift
						$b = $bin->byte();
						$x = ($b & 0xf0) >> 4;
						$y = ($b & 0x0f);
						
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - $x);
						$p = $img_data->peekBack(2);
						$img_data->currentSet($p - $y);
					} elseif ($cmd <= 0xf3) {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					} elseif ($cmd <= 0xf7) {
						// Output 12 pixels, starting at previous
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 12);
					} elseif ($cmd <= 0xfb) {
						// Output 13 pixels, starting at previous, then a literal
						$k = $cmd & 0x3;
						$n = $bin->byte();
						$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 13);
					} elseif ($cmd == 0xfc) {
						$mask = $bin->byte(); // mmmm mfkk
						$k = $mask & 0x3;
						$m = $mask >> 3;
						$flag = $mask & 0x4;
						$n = $bin->byte();
						if ($flag == 0) {
							$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 2*$m+3);
						} else {
							$img_data = $this->_ringCopy($img_data, ($n + 256*$k), 2*$m+4);
						}
					} else {
						$this->_failOut("Unknown Command $cmd (0x".dechex($cmd).")", $img_data);
					}
					$done++;
				}
				//echo "\n";
			}
			//if (count($img_data) > 7607) $this->_failOut('debug', $img_data);
			echo "\n";
		}
		
		// Now create an image of this data
		return $this->_buildPng($img_data);
	}
	
	/**
	 * Convert a pixel array into an image
	 * @return GD image resource (palette-based)
	 */
	private function _buildPng(PixArray $img_data) {
		$x = 0;
		$y = 0;
		foreach($img_data as $i => $p) {
			if ($p === '') {
				echo "Pixel at $i ($x, $y) is empty?\n";
				imagepng($this->img, 'debug.png');
				exit;
			}
			while ($p < 0) {
				$p += 256;
			}
			$p = $p % 256;
			imagesetpixel($this->img, $x, $y, $p);
			$x++;
			if ($x >= $this->width) {
				$x = 0;
				$y++;
			}
		}
		return $this->img;
	}
	
	/**
	 * Copy data from a given point from the end of the current pixel array
	 *
	 * Mostly handled by the pixArray::ringCopy command, but if the size of the ring is odd, we need to pull another pixel value from the binary data held here
	 */
	private function _ringCopy($data, $start, $num) {
		//echo "\nRing copy from $start back, for $num:\n";
		$data->ringCopy($start, $num);
		if (($num % 2) != 0) $data->currentSet($this->bin->byte()); // If the number copied was odd, we need one more to complete the duplet
		return $data;
	}
	
	/**
	 * Debugging output
	 *
	 * A convenience function to build an image with the pixel data generated so far and show an error message before quitting.
	 * Useful for debugging to see at what point in the image pixel sequence the script bailed.
	 */
	private function _failOut($message, $img_data) {
		$im = $this->_buildPng($img_data);
		echo "\n".$message."\n";
		imagepng($im, 'debug.png');
		exit;
	}
}