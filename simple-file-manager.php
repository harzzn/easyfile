<?php

class sfm {

	/**
	* Creates a zip file from a file or a folder recursively without a full nested folder structure inside the zip file
	* Based on: http://stackoverflow.com/a/1334949/3073849
	* @param      string   $source      The path of the folder you want to zip
	* @param      string   $destination The path of the zip file you want to create
	* @return     bool     Returns TRUE on success or FALSE on failure.
	*/
	public static function zip( $source, $destination ) {

		if ( ! extension_loaded( 'zip' ) || ! file_exists( $source ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( ! $zip->open( $destination, ZIPARCHIVE::CREATE ) ) {
			return false;
		}

		$source = str_replace( '\\', '/', realpath( $source ) );

		if ( is_dir( $source ) ) {

			foreach ( new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST ) as $path ) {

				$path = str_replace( '\\', '/', $path );
				$path = realpath( $path );

				if ( is_dir( $path ) ) {
					$zip->addEmptyDir( str_replace( $source . '/', '', $path . '/' ) );
				}

				elseif ( is_file( $path ) ) {
					$zip->addFile( $path, str_replace( $source . '/', '', $path ) );
				}
			}
		}

		elseif ( is_file( $source ) ) {
			$zip->addFile( $source, basename( $source ) );
		}

		return $zip->close();
	}
	
	/**
	 * Extracts a zip file to given folder. Overwrite deletes an existing destination folder and replaces it with the content of the zip file.
	 * @param       string   $source      The path of the zip file you want to extract
	 * @param       string   $destination The path of the folder you want to extract to
	 * @param       bool     $overwrite   (Optional) Whether to overwrite an existing destination folder
	 * @return      bool     Returns TRUE on success or FALSE on failure.
	 **/
	public static function unzip( $source, $destination, $overwrite = false ) {

		if ( ! extension_loaded( 'zip' ) || ! file_exists( $source ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( ! $zip->open( $source ) ) {
			return false;
		}

		if ( ! is_dir( $destination ) ) {

			if ( ! self::mkdir( $destination ) ) {
				return false;
			}
		}

		elseif ( $overwrite ) {

			self::delete( $destination );

			if ( ! self::mkdir( $destination ) ) {
				return false;
			}
		}

		$zip->extractTo( $destination );

		// If we have a resource fork, get rid of it
		$resource_fork = $destination . '/__MACOSX/';

		if ( file_exists( $resource_fork ) ) {
			self::delete( $resource_fork );
		}

		return $zip->close();
	}

	/**
	 * Delete a file, or recursively delete a folder and it's contents
	 * Based on: http://stackoverflow.com/a/15111679/3073849
	 * @param       string   $source The path of the file or folder
	 * @return      bool     Returns TRUE on success or if file already deleted or FALSE on failure.
	 **/
	public static function delete( $source ) {

		if ( ! file_exists( $source ) ) {
			return true;
		}

		if ( is_dir( $source ) ) {

			foreach ( new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST ) as $path ) {

				if ( $path->isDir() && ! $path->isLink() ) {
					rmdir( $path->getPathname() );
				}
				else {
					unlink( $path->getPathname() );
				}
			}

			return rmdir( $source );
		}

		else {
			return unlink( $source );
		}

	}

	/**
	 * Copy a file, or recursively copy a folder and its contents
	 * Based on: http://stackoverflow.com/a/12763962/3073849
	 * @param       string   $source      Source path
	 * @param       string   $destination Destination path
	 * @param       array    $excludes    (Optional) Name of files and folders to exclude from copying
	 * @return      bool     Returns true on success, false on failure
	 **/
	public static function copy( $source, $destination, $excludes = array() ) {

		// Check if in excludes list
		if ( in_array( basename( $source ), $excludes ) ) {
			return false;
		}

		// Check for symlinks
		if ( is_link( $source ) ) {
			return symlink( readlink( $source ), $destination );
		}

		// Simple copy for a file
		if ( is_file( $source ) ) {
			return copy( $source, $destination );
		}

		// Make destination directory
		if ( ! is_dir( $destination ) ) {
			self::mkdir( $destination, fileperms( $source ) );
		}

		// Loop through the folder
		$dir = dir( $source );
		while ( false !== $entry = $dir->read() ) {

			// Skip pointers
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			// Deep copy directories
			self::copy( "$source/$entry", "$destination/$entry", $excludes );
		}

		// Clean up
		$dir->close();

		return true;
	}

	/**
	 * Creates a folder recursively.
	 * @param  		string 	$path        The path of the folder to create
	 * @param  		int 	$permissions (Optional) The mode given for the folder. The default mode (0764) is less permissive than the php default of (0777).
	 * @return      bool    Returns true if the folder already existed or if it was created on successfully, false on failure.
	 */
	public static function mkdir( $path, $permissions = SFM_DEFAULT_PERMISSIONS ) {

		// Folder exists already, return true
		if ( file_exists( $path ) ) {
			return true;
		}

		return mkdir( $path, $permissions, true );

	}

}

// Set default file/folder permission mode if not already defined
if ( ! defined( 'SFM_DEFAULT_PERMISSIONS' ) ) {
	define( 'SFM_DEFAULT_PERMISSIONS', 0764 );
}