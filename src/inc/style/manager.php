<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Style_Manager
 *
 * @since x.x.x.
 */
final class MAKE_Style_Manager extends MAKE_Util_Modules implements MAKE_Style_ManagerInterface, MAKE_Util_HookInterface, MAKE_Util_LoadInterface {

	private $file_action = 'make-css';


	private $inline_action = 'make-css-inline';


	private $helper = null;

	/**
	 * Indicator of whether the hook routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private $hooked = false;

	/**
	 * Indicator of whether the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Inject dependencies.
	 *
	 * @since x.x.x.
	 *
	 * @param MAKE_Compatibility_MethodsInterface $compatibility
	 * @param MAKE_Settings_ThemeModInterface           $thememod
	 * @param MAKE_Style_CSSInterface|null              $css
	 */
	public function __construct(
		MAKE_Compatibility_MethodsInterface $compatibility,
		MAKE_Font_ManagerInterface $font,
		MAKE_Settings_ThemeModInterface $thememod,
		MAKE_Style_CSSInterface $css = null
	) {
		// Compatibility
		$this->add_module( 'compatibility', $compatibility );

		// Fonts
		$this->add_module( 'font', $font );

		// Theme mods
		$this->add_module( 'thememod', $thememod );

		// CSS
		$this->add_module( 'css', ( is_null( $css ) ) ? new MAKE_Style_CSS : $css );
	}

	/**
	 * Hook into WordPress.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function hook() {
		if ( $this->is_hooked() ) {
			return;
		}

		// Add styles as inline CSS in the document head.
		add_action( 'wp_head', array( $this, 'get_styles_as_inline' ), 11 );

		// Register Ajax handler for returning styles as inline CSS.
		add_action( 'wp_ajax_' . $this->inline_action, array( $this, 'get_styles_as_inline_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . $this->inline_action, array( $this, 'get_styles_as_inline_ajax' ) );

		// Register Ajax handler for outputting styles as a file.
		add_action( 'wp_ajax_' . $this->file_action, array( $this, 'get_styles_as_file' ) );
		add_action( 'wp_ajax_nopriv_' . $this->file_action, array( $this, 'get_styles_as_file' ) );

		// Add styles file to TinyMCE.
		add_filter( 'mce_css', array( $this, 'mce_css' ), 99 );

		// Hooking has occurred.
		$this->hooked = true;
	}

	/**
	 * Check if the hook routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	public function is_hooked() {
		return $this->hooked;
	}

	/**
	 * Load data files.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function load() {
		if ( $this->is_loaded() ) {
			return;
		}

		/**
		 * Action: Fires before the Style class loads data files.
		 *
		 * This allows, for example, for filters to be added to thememod settings to change the values
		 * before the style definitions are loaded.
		 *
		 * @since x.x.x.
		 */
		do_action( 'make_style_before_load' );

		$file_bases = array(
			'thememod-typography',
			'thememod-color',
			'thememod-background',
			'thememod-layout',
			'builder',
		);

		// Load the style includes.
		foreach ( $file_bases as $name ) {
			$file = dirname( __FILE__ ) . '/definitions/' . $name . '.php';
			if ( is_readable( $file ) ) {
				include_once $file;
			}
		}

		// Check for deprecated action.
		if ( has_action( 'make_css' ) ) {
			$this->compatibility()->deprecated_hook(
				'make_css',
				'1.7.0',
				__( 'To add dynamic CSS rules, hook into make_style_loaded instead.', 'make' )
			);

			/**
			 * The hook used to add CSS rules for the generated inline CSS.
			 *
			 * This hook is the correct hook to use for adding CSS styles to the group of selectors and properties that will be
			 * added to inline CSS that is printed in the head. Hooking elsewhere may lead to rules not being registered
			 * correctly for the CSS generation. Most Customizer options will use this hook to register additional CSS rules.
			 *
			 * @since 1.2.3.
			 * @deprecated 1.7.0.
			 */
			do_action( 'make_css' );
		}

		/**
		 * Action: Fires at the end of the Styles object's load method.
		 *
		 * This action gives a developer the opportunity to add or modify dynamic styles
		 * and run additional load routines.
		 *
		 * @since 1.2.3.
		 *
		 * @param MAKE_Style_Manager    $style    The styles object
		 */
		do_action( 'make_style_loaded', $this );

		// Loading has occurred.
		$this->loaded = true;
	}

	/**
	 * Check if the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return $this->loaded;
	}


	private function helper() {
		if ( is_null( $this->helper ) ) {
			$this->helper = new MAKE_Style_DataHelper( $this->inject_module( 'compatibility' ), $this->inject_module( 'font' ), $this->inject_module( 'thememod' ) );
		}

		return $this->helper;
	}


	public function get_styles_as_inline() {
		if ( ! $this->is_loaded() ) {
			$this->load();
		}

		/**
		 * Action: Fires before the inline CSS rules are rendered and output.
		 *
		 * @since x.x.x.
		 */
		do_action( 'make_style_before_inline' );

		// Echo the rules.
		if ( $this->css()->has_rules() ) {
			echo "\n<!-- Begin Make Inline CSS -->\n<style type=\"text/css\">\n";

			echo stripslashes( wp_filter_nohtml_kses( $this->css()->build() ) );

			echo "\n</style>\n<!-- End Make Inline Custom CSS -->\n";
		}
	}


	public function get_styles_as_inline_ajax() {
		// Only run this in the proper hook context.
		if ( ! in_array( current_action(), array( 'wp_ajax_' . $this->inline_action, 'wp_ajax_nopriv_' . $this->inline_action ) ) ) {
			wp_die();
		}
		
		$this->get_styles_as_inline();

		// End the Ajax response.
		wp_die();
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function get_styles_as_file() {
		// Only run this in the proper hook context.
		if ( ! in_array( current_action(), array( 'wp_ajax_' . $this->file_action, 'wp_ajax_nopriv_' . $this->file_action ) ) ) {
			wp_die();
		}

		if ( ! $this->is_loaded() ) {
			$this->load();
		}

		/**
		 * Action: Fires before the CSS rules are rendered and output as a file.
		 *
		 * @since x.x.x.
		 */
		do_action( 'make_style_before_file' );

		/**
		 * Filter: Set whether the dynamic stylesheet will send headers telling the browser
		 * to cache the request. Set to false to turn off these headers.
		 *
		 * @since x.x.x.
		 *
		 * @param bool    $cache_headers
		 */
		if ( ( ! defined( 'SCRIPT_DEBUG' ) || false === SCRIPT_DEBUG ) && true === apply_filters( 'make_style_file_cache_headers', true ) ) {
			// Set headers for caching
			// @link http://stackoverflow.com/a/15000868
			// @link http://www.mobify.com/blog/beginners-guide-to-http-cache-headers/
			$expires = HOUR_IN_SECONDS;
			header( 'Pragma: public' );
			header( 'Cache-Control: private, max-age=' . $expires );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires ) . ' GMT' );
		}

		// Set header for content type.
		header( 'Content-type: text/css' );

		// Echo the rules.
		echo wp_filter_nohtml_kses( $this->css()->build() );

		// End the Ajax response.
		wp_die();
	}

	/**
	 * Generate a URL for accessing the dynamically-generated CSS file.
	 *
	 * @since x.x.x.
	 *
	 * @return string
	 */
	public function get_file_url() {
		return add_query_arg( 'action', $this->file_action, admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Make sure theme option CSS is added to TinyMCE last, to override other styles.
	 *
	 * @since  1.0.0.
	 *
	 * @param  string    $stylesheets    List of stylesheets added to TinyMCE.
	 * @return string                    Modified list of stylesheets.
	 */
	function mce_css( $stylesheets ) {
		if ( $this->css()->has_rules() ) {
			$stylesheets .= ',' . $this->get_file_url();
		}

		return $stylesheets;
	}
}