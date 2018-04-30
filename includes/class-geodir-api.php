<?php
/**
 * GeoDirectory API
 *
 * Handles GD-API endpoint requests.
 *
 * @author   GeoDirectory
 * @category API
 * @package  GeoDirectory/API
 * @since    2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GeoDir_API {

	/**
	 * Setup class.
	 * @since 2.0
	 */
	public function __construct() {
		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Register API endpoints.
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );

		// Handle geodir-api endpoint requests.
		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );

		// WP REST API.
		$this->rest_api_init();
	}

	/**
	 * Add new query vars.
	 *
	 * @since 2.0.0
	 * @param array $vars
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'geodir-api';
		return $vars;
	}

	/**
	 * GeoDir API for payment gateway IPNs, etc.
	 * @since 2.0.0
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'geodir-api', EP_ALL );
	}

	/**
	 * API request - Trigger any API requests.
	 *
	 * @since   2.0.0
	 */
	public function handle_api_requests() {
		global $wp;

		if ( ! empty( $_GET['geodir-api'] ) ) {
			$wp->query_vars['geodir-api'] = $_GET['geodir-api'];
		}

		// geodir-api endpoint requests.
		if ( ! empty( $wp->query_vars['geodir-api'] ) ) {

			// Buffer, we won't want any output here.
			ob_start();

			// No cache headers.
			geodir_nocache_headers();

			// Clean the API request.
			$api_request = strtolower( geodir_clean( $wp->query_vars['geodir-api'] ) );

			// Trigger generic action before request hook.
			do_action( 'geodir_api_request', $api_request );

			// Is there actually something hooked into this API request? If not trigger 400 - Bad request.
			status_header( has_action( 'geodir_api_' . $api_request ) ? 200 : 400 );

			// Trigger an action which plugins can hook into to fulfill the request.
			do_action( 'geodir_api_' . $api_request );

			// Done, clear buffer and exit.
			ob_end_clean();
			die( '-1' );
		}
	}

	/**
	 * Init WP REST API.
	 * @since 2.0.0
	 */
	private function rest_api_init() {
		// REST API was included starting WordPress 4.4.
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		$this->rest_api_includes();
		
		// Show / hide CPT from Rest API
		add_action( 'init', array( $this, 'setup_show_in_rest' ), 10 );

		// Init REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 100 );
		add_action( 'rest_api_init', array( $this, 'register_rest_query' ), 101 );

		add_action( 'pre_get_posts', array( __CLASS__, 'rest_posts_request' ), 10, 2 );
	}

	/**
	 * Include REST API classes.
	 *
	 * @since 2.0.0
	 */
	private function rest_api_includes() {
		include_once( dirname( __FILE__ ) . '/geodir-rest-functions.php' );

		// Authentication.
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-authentication.php' );
		
		// Abstract controllers.
		include_once( dirname( __FILE__ ) . '/abstracts/abstract-geodir-rest-controller.php' );
		include_once( dirname( __FILE__ ) . '/abstracts/abstract-geodir-rest-terms-controller.php' );

		// REST API v2 controllers.
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-taxonomies-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-post-types-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-post-categories-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-post-tags-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-posts-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-reviews-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-settings-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-setting-options-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-system-status-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-system-status-tools-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-countries-controller.php' );
		include_once( dirname( __FILE__ ) . '/api/class-geodir-rest-markers-controller.php' );
	}

	/**
	 * Register REST API routes.
	 * @since 2.0.0
	 */
	public function register_rest_routes() {
		global $wp_post_types;

		// Register settings to the REST API.
		$this->register_wp_admin_settings();

		if ( geodir_api_enabled() ) {
            $gd_post_types = geodir_get_posttypes();

            foreach ( $wp_post_types as $post_type ) { 
				if ( ! in_array( $post_type->name, $gd_post_types ) ) {
                    continue;
                }

                if ( empty( $post_type->show_in_rest ) ) {
                    continue;
                }

                $class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

                if ( ! class_exists( $class ) ) {
                    continue;
                }
                $controller = new $class( $post_type->name );

                if ( ! ( is_subclass_of( $controller, 'WP_REST_Posts_Controller' ) || is_subclass_of( $controller, 'WP_REST_Controller' ) ) ) {
                    continue;
                }
            }

			$controllers = array(
				// v2 controllers.
				'Geodir_REST_Taxonomies_Controller',
				'GeoDir_REST_Reviews_Controller',
				'GeoDir_REST_Post_Types_Controller',
				'GeoDir_REST_Settings_Controller',
				'GeoDir_REST_Setting_Options_Controller',
				'GeoDir_REST_System_Status_Controller',
				'GeoDir_REST_System_Status_Tools_Controller',
				'GeoDir_REST_Countries_Controller',
				'GeoDir_REST_Markers_Controller', // Map markers api should always enabled.
			);
		} else {
			$controllers = array(
				'GeoDir_REST_Markers_Controller', // Map markers api should always enabled.
			);
		}

		foreach ( $controllers as $controller ) {
			$this->$controller = new $controller();
			$this->$controller->register_routes();
		}

	}

	/**
	 * Register GeoDir settings from WP-API to the REST API.
	 * @since  2.0.0
	 */
	public function register_wp_admin_settings() {
		$pages = GeoDir_Admin_Settings::get_settings_pages();
		foreach ( $pages as $page ) {
			new GeoDir_Register_WP_Admin_Settings( $page, 'page' );
		}
	}
	
	public function setup_show_in_rest() {
		global $wp_post_types, $wp_taxonomies;

		if ( ! geodir_api_enabled() ) {
			return;
		}

		$post_types = geodir_get_posttypes( 'array' );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type => $data ) {
				if ( isset( $wp_post_types[$post_type] ) ) {
					$wp_post_types[$post_type]->gd_listing = true;
					$wp_post_types[$post_type]->show_in_rest = true;
					$wp_post_types[$post_type]->rest_base = $data['has_archive'];
					$wp_post_types[$post_type]->rest_controller_class = 'GeoDir_REST_Posts_Controller';
					
					if ( ! empty( $data['taxonomies'] ) ) {
						foreach ( $data['taxonomies'] as $taxonomy ) {
							if ( isset( $wp_taxonomies[$taxonomy] ) ) {
								$wp_taxonomies[$taxonomy]->gd_taxonomy = true;
								$wp_taxonomies[$taxonomy]->show_in_rest = true;
								if ( $taxonomy == $post_type . 'category' ) {
									$rest_base = $data['has_archive'] . '/categories';
									$rest_controller_class = 'GeoDir_REST_Post_Categories_Controller';
								} else if ( $taxonomy == $post_type . '_tags' ) {
									$rest_base = $data['has_archive'] . '/tags';
									$rest_controller_class = 'GeoDir_REST_Post_Tags_Controller';
								} else {
									$rest_base = $taxonomy;
									$rest_controller_class = '';
								}
								$wp_taxonomies[$taxonomy]->rest_base = $rest_base;
								if ( $rest_controller_class ) {
									$wp_taxonomies[$taxonomy]->rest_controller_class = $rest_controller_class;
								}
							}
						}
					}
				}
			}
		}
	}

	public static function is_rest() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		return false;
	}

	public static function register_rest_query() {
		if ( self::is_rest() ) {
			add_filter( 'posts_clauses_request', array( __CLASS__, 'posts_clauses_request' ), 10, 2 );
		}
	}

	public static function posts_clauses_request( $clauses, $wp_query ) {
		$post_type  = !empty( $wp_query->query_vars['post_type'] ) ? $wp_query->query_vars['post_type'] : '';

		if ( ! geodir_is_gd_post_type( $post_type ) ) {
			return $clauses;
		}

		$clauses['distinct']    = apply_filters( 'geodir_rest_posts_clauses_distinct', $clauses['distinct'], $wp_query, $post_type );
		$clauses['fields']      = apply_filters( 'geodir_rest_posts_clauses_fields', $clauses['fields'], $wp_query, $post_type );
		$clauses['join']        = apply_filters( 'geodir_rest_posts_clauses_join', $clauses['join'], $wp_query, $post_type );
		$clauses['where']       = apply_filters( 'geodir_rest_posts_clauses_where', $clauses['where'], $wp_query, $post_type );
		$clauses['groupby']     = apply_filters( 'geodir_rest_posts_clauses_groupby', $clauses['groupby'], $wp_query, $post_type );
		$clauses['orderby']     = apply_filters( 'geodir_rest_posts_clauses_orderby', $clauses['orderby'], $wp_query, $post_type );
		$clauses['limits']      = apply_filters( 'geodir_rest_posts_clauses_limits', $clauses['limits'], $wp_query, $post_type );

		return apply_filters( 'geodir_rest_posts_clauses_request', $clauses, $wp_query, $post_type );
	}

	public static function rest_posts_request( $query ) {
		if ( self::is_rest() ) {
			add_filter( 'geodir_rest_posts_clauses_distinct', array( __CLASS__, 'rest_posts_distinct' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_fields', array( __CLASS__, 'rest_posts_fields' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_join', array( __CLASS__, 'rest_posts_join' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_where', array( __CLASS__, 'rest_posts_where' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_groupby', array( __CLASS__, 'rest_posts_groupby' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_orderby', array( __CLASS__, 'rest_posts_orderby' ), 10, 3 );
			add_filter( 'geodir_rest_posts_clauses_limits', array( __CLASS__, 'rest_posts_limits' ), 10, 3 );
		}
	}

	public static function rest_posts_distinct( $distinct, $wp_query, $post_type ) {
		return $distinct;
	}

	public static function rest_posts_fields( $fields, $wp_query, $post_type ) {
		if ( trim( $fields ) != '' ) {
			$fields .= ", ";
		}

		$table = geodir_db_cpt_table( $post_type );

		$fields .= "{$table}.*";

		return $fields;
	}

	public static function rest_posts_join( $join, $wp_query, $post_type ) {
		global $wpdb;

		$table = geodir_db_cpt_table( $post_type );

		$join .= " LEFT JOIN {$table} ON ( {$table}.post_id = {$wpdb->posts}.ID )";

		return $join;
	}

	public static function rest_posts_where( $where, $wp_query, $post_type ) {
		return $where;
	}

	public static function rest_posts_groupby( $groupby, $wp_query, $post_type ) {
		return $groupby;
	}

	public static function rest_posts_orderby( $orderby, $wp_query, $post_type ) {
		return $orderby;
	}

	public static function rest_posts_limits( $limits, $wp_query, $post_type ) {
		return $limits;
	}

}
