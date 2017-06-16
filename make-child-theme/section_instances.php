<?php
/**
 * @package Make
 */

if ( ! class_exists( 'TTFMAKE_Section_Instances' ) ) :
/**
 * Handler for section overlays
 *
 * @since 1.9.0.
 *
 * Class TTFMAKE_Section_Instances
 */
class TTFMAKE_Section_Instances {

	private static $instance;

	public function __construct() {

	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function hook() {
		add_filter( 'make_add_section', array( $this, 'add_controls' ), 999 );
		add_filter( 'make_section_defaults', array( $this, 'add_section_defaults' ), 999 );
		add_filter( 'make_prepare_data_section', array( $this, 'save_section' ), 20, 2 );
		add_filter( 'make_prepare_data_section', array( $this, 'save_sid_field' ), 10, 2 );
		add_action( 'make_builder_data_saved', array( $this, 'save_layout' ), 10, 2 );
		add_filter( 'make_get_section_data', array( $this, 'read_layout' ), 10, 2 );
	}

	public function add_controls( $args ) {
		array_push( $args[ 'config' ], array(
			'type'    => 'checkbox',
			'label'   => __( 'Master', 'make-plus' ),
			'name'    => 'master',
			'default' => 0,
		) );

		array_push( $args[ 'config' ], array(
			'type'    => 'text',
			'label'   => __( 'Master ID', 'make-plus' ),
			'name'    => 'master-id',
			'default' => '',
		) );

		return $args;
	}

	public function add_section_defaults( $defaults ) {
		foreach ( $defaults as $section_id => $section_defaults ) {
			$defaults[ $section_id ][ 'master' ] = 0;
			$defaults[ $section_id ][ 'master-id' ] = '';
		}


		return $defaults;
	}

	public function save_section( $clean_data, $raw_data ) {
		if ( isset( $raw_data[ 'master' ] ) ) {
			if ( $raw_data[ 'master' ] == 1 ) {
				$clean_data[ 'master' ] = 1;
			} else {
				$clean_data[ 'master' ] = 0;
			}
		}

		$clean_data[ 'master-id' ] = $raw_data[ 'master-id' ];

		return $clean_data;
	}

	public function save_sid_field( $clean_data, $raw_data ) {
		// Make the sid field pass through
		// during the save routine
		$clean_data[ 'sid' ] = $raw_data[ 'sid' ];

		return $clean_data;
	}

	public function save_layout( $sections, $post_id ) {
		// Skip if this is being run on a revision
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Remove legacy post meta
		delete_post_meta( $post_id, '_ttfmake-section-ids' );

		$post_meta = get_post_meta( $post_id );

		foreach ( $post_meta as $key => $value ) {
			if ( 0 === strpos( $key, '_ttfmake:' ) ) {
				delete_post_meta( $post_id, $key );
			}
		}

		$s = 1;
		$layout = array();

		foreach( $sections as $id => $section ) {
			// If it's the first time we store this section
			// append the sid value coming from db
			if ( ! isset( $section[ 'sid' ] ) || ! $section[ 'sid' ] ) {
				$section_id = add_post_meta( $post_id, "__ttfmake_section_{$s}", '', true );
				$section[ 'sid' ] = "{$section_id}";
			}

			// If the section is set as master, and no reference
			// to an existing master is set, create the master
			// section entry in wp_options table
			if ( $section[ 'master' ] && ! $section[ 'master-id' ] ) {
				$option_id = 'master_' . $section[ 'section-type' ] . '_' . $section[ 'sid' ];
				// These keys should be removed from the master,
				// and be the only keys remaining on the instance.
				$id_keys = array( 'sid', 'master', 'master-id' );
				$master = array_diff_key( $section, array_flip( $id_keys ) );
				$section = array_intersect_key( $section, array_flip( $id_keys ) );
				// Set the master-id reference on the instance
				$section[ 'master-id' ] = $option_id;

				update_option( $option_id, wp_slash( json_encode( $master ) ) );
			} else if ( ! $section[ 'master' ] ) {
				// Clear the master-id reference
				// if section isn't master anymore
				$section[ 'master-id' ] = ttfmake_get_section_default( 'master-id', $section[ 'section-type' ] );
			}

			// Avoid adding new metadatas each time.
			// Instead, update the section meta
			// using the meta id
			update_metadata_by_mid(
				'post',
				$section[ 'sid' ],
				wp_slash( json_encode( $section ) ),
				"__ttfmake_section_{$s}",
				true
			);

			$layout[] = $section[ 'sid' ];
			$s ++;
		}

		// Update layout
		update_post_meta( $post_id, "__ttfmake_layout", wp_slash( json_encode( $layout ) ) );
	}

	public function read_layout( $sections, $post_id ) {
		$layout_meta = get_post_meta( $post_id, '__ttfmake_layout', true );

		if ( $layout_meta ) {
			$layout = json_decode( $layout_meta, true );
			$sections = array();

			foreach ( $layout as $section_id ) {
				// Fetch section using its db id
				$section_meta = get_metadata_by_mid( 'post', $section_id );
				$section = json_decode( wp_unslash( $section_meta->meta_value ), true );

				if ( $section[ 'master-id' ] ) {
					$master_option = get_option( $section[ 'master-id' ] );
					$master = json_decode( wp_unslash( $master_option ), true );
					// Merge the master data with the section instance
					$section += $master;
				}

				$sections[] = $section;
			}
		}

		return $sections;
	}

}

endif;

if ( ! function_exists( 'ttfmake_get_section_instances' ) ) :
/**
 * Instantiate or return the one TTFMAKE_Section_Instances instance.
 *
 * @since  1.9.0.
 *
 * @return TTFMAKE_Section_Instances
 */
function ttfmake_get_section_instances() {
	return TTFMAKE_Section_Instances::instance();
}
endif;