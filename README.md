Riven-tBMP
==========
This is a set of PHP scripts designed to parse out tBMP resources out of game data files for the Riven game by Cyan studios.

If you have a set of game data files ([MHWK/Mohawk archives](http://insidethelink.ortiche.net/wiki/index.php/Mohawk_archive_format)), use the `parse_mhk.php` file to find and extract all the [tBMP resources](http://www.mystellany.com/riven/imageformat/) out of it (creates a folder called `output` and creates a `*.tBMP` file for each resource in it).

Those extracted tBMP files can then be parsed using the `parse_tBMP.php` script (outputs a `*.png` file in the same location as the `*.tBMP` file read). Currently it only parses Riven-compressed data (8-bpp, non LZ compression, no secondary compression).
