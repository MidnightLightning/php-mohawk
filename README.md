PHP-Mohawk
==========
This is a set of PHP scripts designed to parse ([MHWK/Mohawk archive](http://insidethelink.ortiche.net/wiki/index.php/Mohawk_archive_format)) files  from the Myst/Riven games from Cyan studios.

The `view_mhk.php` file shows details about a Mohawk archive file (outputs HTML; use like `view_mhk.php?f=MYST.DAT`), showing which data can be found in which ranges of the file.

Riven-tBMP
----------
If you have a set of game data files, use the `parse_mhk.php` file to find and extract all the [tBMP resources](http://www.mystellany.com/riven/imageformat/) out of it (creates a folder called `output` and creates a `*.tBMP` file for each resource in it).

Those extracted tBMP files can then be parsed using the `parse_tBMP.php` script (outputs a `*.png` file in the same location as the `*.tBMP` file read). Currently it only parses Riven-compressed data (8-bpp, non LZ compression, no secondary compression).

Myst-WDIB
---------
If you have a set of game data files, use the `parse_mhk.php` file to find and extract all the [WDIB resources](http://insidethelink.ortiche.net/wiki/index.php/Myst_WDIB_resources) out of it (creates a folder called `output` and creates a `*.WDIB` file for each resource in it).

Those extracted WDIB files can then be parsed using the `parse_wdib.php` script (outputs a `*.png` file in the same location as the `*.WDIB` file read). Currently it only parses WDIB resources that contain BMP data (a few of them contain PICT files) that are LZ-compressed. The `splay_wdib.php` script gives more information about a WDIB file, showing the "runs" of data that comprise the compression algorithm, and gives notes on which pixels of the image they're affecting.

If you have a BMP file you want compressed into a WDIB, the `bmp2wdib.php` script will do that (outputs a `*.WDIB` file in the same location as the `*.bmp` file read).