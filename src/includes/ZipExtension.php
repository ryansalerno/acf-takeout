<?php
// Unlike ZipArchive itself, this wrapper will let us stream the zipped contents without creating a temp file on disk
// https://github.com/phpmyadmin/phpmyadmin/blob/RELEASE_4_8_5/libraries/classes/ZipExtension.php

/**
 * Interface for the zip extension
 *
 * @package PhpMyAdmin
 */

class ZipExtension
{
	/**
	 * @var ZipArchive
	 */
	private $zip;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->zip = new ZipArchive();
	}

	/**
	 * Creates a zip file.
	 * If $data is an array and $name is a string, the filenames will be indexed.
	 * The function will return false if $data is a string but $name is an array
	 * or if $data is an array and $name is an array, but they don't have the
	 * same amount of elements.
	 *
	 * @param array|string $data contents of the file/files
	 * @param array|string $name name of the file/files in the archive
	 * @param integer      $time the current timestamp
	 *
	 * @return string|bool the ZIP file contents, or false if there was an error.
	 */
	public function createFile($data, $name, $time = 0)
	{
		$datasec = []; // Array to store compressed data
		$ctrl_dir = []; // Central directory
		$old_offset = 0; // Last offset position
		$eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; // End of central directory record

		if (is_string($data) && is_string($name)) {
			$data = [$name => $data];
		} elseif (is_array($data) && is_string($name)) {
			$ext_pos = strpos($name, '.');
			$extension = substr($name, $ext_pos);
			$newData = [];
			foreach ($data as $key => $value) {
				$newName = str_replace(
					$extension,
					'_' . $key . $extension,
					$name
				);
				$newData[$newName] = $value;
			}
			$data = $newData;
		} elseif (is_array($data) && is_array($name) && count($data) === count($name)) {
			$data = array_combine($name, $data);
		} else {
			return false;
		}

		foreach ($data as $table => $dump) {
			$temp_name = str_replace('\\', '/', $table);

			/* Convert Unix timestamp to DOS timestamp */
			$timearray = ($time == 0) ? getdate() : getdate($time);

			if ($timearray['year'] < 1980) {
				$timearray['year'] = 1980;
				$timearray['mon'] = 1;
				$timearray['mday'] = 1;
				$timearray['hours'] = 0;
				$timearray['minutes'] = 0;
				$timearray['seconds'] = 0;
			}

			$time = (($timearray['year'] - 1980) << 25)
			| ($timearray['mon'] << 21)
			| ($timearray['mday'] << 16)
			| ($timearray['hours'] << 11)
			| ($timearray['minutes'] << 5)
			| ($timearray['seconds'] >> 1);

			$hexdtime = pack('V', $time);

			$unc_len = strlen($dump);
			$crc = crc32($dump);
			$zdata = gzcompress($dump);
			$zdata = substr(substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
			$c_len = strlen($zdata);
			$fr = "\x50\x4b\x03\x04"
				. "\x14\x00"        // ver needed to extract
				. "\x00\x00"        // gen purpose bit flag
				. "\x08\x00"        // compression method
				. $hexdtime         // last mod time and date

				// "local file header" segment
				. pack('V', $crc)               // crc32
				. pack('V', $c_len)             // compressed filesize
				. pack('V', $unc_len)           // uncompressed filesize
				. pack('v', strlen($temp_name)) // length of filename
				. pack('v', 0)                  // extra field length
				. $temp_name

				// "file data" segment
				. $zdata;

			$datasec[] = $fr;

			// now add to central directory record
			$cdrec = "\x50\x4b\x01\x02"
				. "\x00\x00"                     // version made by
				. "\x14\x00"                     // version needed to extract
				. "\x00\x00"                     // gen purpose bit flag
				. "\x08\x00"                     // compression method
				. $hexdtime                      // last mod time & date
				. pack('V', $crc)                // crc32
				. pack('V', $c_len)              // compressed filesize
				. pack('V', $unc_len)            // uncompressed filesize
				. pack('v', strlen($temp_name))  // length of filename
				. pack('v', 0)                   // extra field length
				. pack('v', 0)                   // file comment length
				. pack('v', 0)                   // disk number start
				. pack('v', 0)                   // internal file attributes
				. pack('V', 32)                  // external file attributes
												 // - 'archive' bit set
				. pack('V', $old_offset)         // relative offset of local header
				. $temp_name;                    // filename
			$old_offset += strlen($fr);
			// optional extra field, file comment goes here
			// save to central directory
			$ctrl_dir[] = $cdrec;
		}

		/* Build string to return */
		$temp_ctrldir = implode('', $ctrl_dir);
		$header = $temp_ctrldir .
			$eof_ctrl_dir .
			pack('v', sizeof($ctrl_dir)) . //total #of entries "on this disk"
			pack('v', sizeof($ctrl_dir)) . //total #of entries overall
			pack('V', strlen($temp_ctrldir)) . //size of central dir
			pack('V', $old_offset) . //offset to start of central dir
			"\x00\x00";                         //.zip file comment length

		$data = implode('', $datasec);

		return $data . $header;
	}
}
