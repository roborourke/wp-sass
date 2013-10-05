<?php
/**
Plugin Name: WP Sass
Plugin URI: https://github.com/sanchothefat/wp-sass/
Description: Allows you to enqueue .sass files and have them automatically compiled whenever a change is detected.
Author: Robert O'Rourke
Version: 0.2
Author URI: http://interconnectit.com
License: MIT
*/

// Busted! No direct file access
! defined( 'ABSPATH' ) AND exit;


// load SASS parser
! class_exists( 'SassParser' ) AND require_once( 'phpsass/SassParser.php' );


if ( ! class_exists( 'wp_sass' ) ) {
	// add on init to support theme customiser in v3.4
	add_action( 'init', array( 'wp_sass', 'instance' ) );

/**
 * Enables the use of SASS in WordPress
 *
 * See README.md for usage information
 *
 * @author  Robert O'Rourke @link http://sanchothefat.com/
 * @package WP SASS
 * @license MIT
 * @version 2012-06-13.1701
 */
class wp_sass {
	/**
	 * Reusable object instance.
	 *
	 * @type object
	 */
	protected static $instance = null;


	/**
	 * Creates a new instance. Called on 'init'.
	 * May be used to access class methods from outside.
	 *
	 * @see    __construct()
	 * @return void
	 */
	public static function instance() {

		null === self :: $instance AND self :: $instance = new self;
		return self :: $instance;
	}


	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {

		// every CSS file URL gets passed through this filter
		add_filter( 'style_loader_src', array( $this, 'parse_stylesheet' ), 100000, 2 );

		// editor stylesheet URLs are concatenated and run through this filter
		add_filter( 'mce_css', array( $this, 'parse_editor_stylesheets' ), 100000 );
	}


	/**
	 * Sassify the stylesheet and return the href of the compiled file
	 *
	 * @param String $src		Source URL of the file to be parsed
	 * @param String $handle	An identifier for the file used to create the file name in the cache
	 *
	 * @return String    URL of the compiled stylesheet
	 */
	public function parse_stylesheet( $src, $handle ) {

		// we only want to handle .less files
		if ( ! preg_match( "/\.(?:sass|scss)(\.php)?$/", preg_replace( "/\?.*$/", "", $src ) ) )
			return $src;

		// get file path from $src
		if ( ! strstr( $src, '?' ) ) $src .= '?'; // prevent non-existent index warning when using list() & explode()

		list( $sass_path, $query_string ) = explode( '?', str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $src ) );

		// output css file name
		$css_path = trailingslashit( $this->get_cache_dir() ) . "{$handle}.css";

		$syntax = strstr( $sass_path, 'scss' ) ? 'scss' : 'sass';

		// automatically regenerate files if source's modified time has changed or vars have changed
		try {
			// load the cache
			$cache_path = "{$css_path}.cache";
			if ( file_exists( $cache_path ) )
				$full_cache = unserialize( file_get_contents( $cache_path ) );

			// If the root path in the cache is wrong then regenerate
			if ( ! isset( $full_cache ) )
				$full_cache = array( 'root' => dirname( __FILE__ ), 'path' => $sass_path, 'css' => '', 'updated' => filemtime( $sass_path ) );

			// check output after php has run for changes. Options are preloaded by default so the database hit shouldn't be too bad for most uses
			if ( strstr( $sass_path, '.php' ) ) {
				ob_start();
				include( $sass_path );
				$sass = ob_get_clean();
				if ( ! file_exists( "{$cache_path}.preprocessed.{$syntax}" ) || ( file_exists( "{$cache_path}.preprocessed.{$syntax}" ) && $sass != file_get_contents( "{$cache_path}.preprocessed.{$syntax}" ) ) ) {
					$full_cache[ 'css' ] = '';
					file_put_contents( "{$cache_path}.preprocessed.{$syntax}", $sass );
				}
			}

			// parse if we need to
			if ( empty( $full_cache[ 'css' ] ) || filemtime( $sass_path ) > $full_cache[ 'updated' ] || $full_cache[ 'root' ] != dirname( __FILE__ ) ) {
				// preprocess php files in WP context
				if ( strstr( $sass_path, '.php' ) ) {
					$full_cache[ 'css' ] = $this->__parse( "{$cache_path}.preprocessed.{$syntax}", $syntax, "nested", array( dirname( $sass_path ) ) );
				} else {
					$full_cache[ 'css' ] = $this->__parse( $sass_path, $syntax, "nested", array( dirname( $sass_path ) ) );
				}
				// update cache creation time
				$full_cache[ 'updated' ] = filemtime( $sass_path );
				file_put_contents( $cache_path, serialize( $full_cache ) );
				file_put_contents( $css_path, $full_cache[ 'css' ] );
			}
		} catch ( exception $ex ) {
			wp_die( $ex->getMessage() );
		}

		// return the compiled stylesheet with the query string it had if any
		return trailingslashit( $this->get_cache_dir( false ) ) . "{$handle}.css" . ( ! empty( $query_string ) ? "?{$query_string}" : '' );
	}

	public function __parse( $file, $syntax, $style = 'nested', $load_path = array() ) {
		$options = array(
			'style' => $style,
			'cache' => FALSE,
			'syntax' => $syntax,
			'debug' => FALSE,
			'callbacks' => array(
				'warn' => array( $this, 'cb_warn' ),
				'debug' => array( $this, 'cb_debug' ),
			),
			'load_paths' => $load_path,
		);
		// Execute the compiler.
		$parser = new SassParser( $options );
		return $parser->toCss( $file );
    }

	public function cb_warn( $message, $context ) {
		print "<p class='warn'>WARN : ";
		print_r( $message );
		print "</p>";
    }

    public function cb_debug( $message ) {
		print "<p class='debug'>DEBUG : ";
		print_r( $message );
		print "</p>";
    }


	/**
	 * Compile editor stylesheets registered via add_editor_style()
	 *
	 * @param String $mce_css comma separated list of CSS file URLs
	 *
	 * @return String    New comma separated list of CSS file URLs
	 */
	public function parse_editor_stylesheets( $mce_css ) {

		// extract CSS file URLs
		$style_sheets = explode( ",", $mce_css );

		if ( count( $style_sheets ) ) {
			$compiled_css = array();

			// loop through editor styles, any .sass or .scss files will be compiled and the compiled URL returned
			foreach( $style_sheets as $style_sheet )
				$compiled_css[] = $this->parse_stylesheet( $style_sheet, $this->url_to_handle( $style_sheet ) );

			$mce_css = implode( ",", $compiled_css );
		}

		// return new URLs
		return $mce_css;
	}


	/**
	 * Get a nice handle to use for the compiled CSS file name
	 *
	 * @param String $url 	File URL to generate a handle from
	 *
	 * @return String    	Sanitised string to use for handle
	 */
	public function url_to_handle( $url ) {

		$url = parse_url( $url );
		$url = str_replace( '.sass', '', basename( $url[ 'path' ] ) );
		$url = str_replace( '/', '-', $url );

		return sanitize_key( $url );
	}


	/**
	 * Get (and create if unavailable) the compiled CSS cache directory
	 *
	 * @param Bool $path 	If true this method returns the cache's system path. Set to false to return the cache URL
	 *
	 * @return String 	The system path or URL of the cache folder
	 */
	public function get_cache_dir( $path = true ) {

		// get path and url info
		$upload_dir = wp_upload_dir();

		if ( $path ) {
			$dir = apply_filters( 'wp_sass_cache_path', trailingslashit( $upload_dir[ 'basedir' ] ) . 'wp-sass-cache' );
			// create folder if it doesn't exist yet
			if ( ! file_exists( $dir ) )
				wp_mkdir_p( $dir );
		} else {
			$dir = apply_filters( 'wp_sass_cache_url', trailingslashit( $upload_dir[ 'baseurl' ] ) . 'wp-sass-cache' );
		}

		return rtrim( $dir, '/' );
	}

} // END class

} // endif;