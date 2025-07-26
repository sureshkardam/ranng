<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ideapark_Moderno {

	private static $_instance = null;
	public $settings = null;
	public $_version;
	public $_token;
	public $file;
	public $dir;
	public $assets_dir;
	public $assets_url;
	public $script_suffix;
	private $sorted_post_types = [];
	private $sorted_taxonomies = [];


	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'ideapark_moderno';

		// Load plugin environment variables
		$this->file          = $file;
		$this->dir           = dirname( $this->file );
		$this->assets_dir    = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url    = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		$this->script_suffix = '';

		register_activation_hook( $this->file, [ $this, 'install' ] );
		add_action( 'wp_insert_site', [ $this, 'install' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 10, 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_styles' ], 10, 1 );
		add_action( 'wp_ajax_update-post-order', [ $this, 'update_post_order' ] );
		add_action( 'wp_ajax_update-term-order', [ $this, 'update_term_order' ] );
		add_action( 'init', [ $this, 'load_plugin_textdomain' ], 0 );
		add_action( 'init', [ $this, 'mb_term_meta_load' ], 5 );
	} // End __construct ()

	public function mb_term_meta_load() {
		if ( ! defined( 'RWMB_VER' ) || class_exists( 'MB_Term_Meta_Box' ) ) {
			return;
		}

		require dirname( __FILE__ ) . '/term-meta/class-mb-term-meta-loader.php';
		require dirname( __FILE__ ) . '/term-meta/class-mb-term-meta-box.php';
		require dirname( __FILE__ ) . '/term-meta/class-rwmb-term-storage.php';

		$loader = new MB_Term_Meta_Loader;
		$loader->init();
	}

	public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '', $options = [] ) {

		if ( ! $post_type || ! $plural || ! $single ) {
			return;
		}

		add_filter( 'rest_' . $post_type . '_collection_params', function ( $params ) {
			$params['orderby']['enum'][] = 'menu_order';

			return $params;
		}, 10, 1 );

		$post_type = new Ideapark_Moderno_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	public function register_taxonomy( $taxonomy, $object_type, $plural = '', $single = '', $description = '', $options = [] ) {

		if ( ! $taxonomy || ! $object_type || ! $plural || ! $single ) {
			return;
		}

		$post_type = new Ideapark_Moderno_Taxonomy( $taxonomy, $object_type, $plural, $single, $description, $options );

		return $post_type;
	}

	function update_post_order() {
		global $wpdb;

		parse_str( $_POST['order'], $data );

		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $this->sorted_post_types as $object ) {
			$result = $wpdb->get_results( $wpdb->prepare( "
					SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min 
					FROM {$wpdb->posts} 
					WHERE post_type = %s AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				", $object ) );
			if ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) {
				continue;
			}

			$results = $wpdb->get_results( $wpdb->prepare( "
					SELECT ID 
					FROM {$wpdb->posts} 
					WHERE post_type = %s AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
					ORDER BY menu_order ASC
				", $object ) );
			foreach ( $results as $key => $result ) {
				$wpdb->update( $wpdb->posts, [ 'menu_order' => $key + 1 ], [ 'ID' => $result->ID ] );
			}
		}

		$id_arr = [];
		foreach ( $data as $key => $values ) {
			foreach ( $values as $position => $id ) {
				$id_arr[] = $id;
			}
		}

		$menu_order_arr = [];
		foreach ( $id_arr as $key => $id ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d", intval( $id )) );
			foreach ( $results as $result ) {
				$menu_order_arr[] = $result->menu_order;
			}
		}

		sort( $menu_order_arr );

		foreach ( $data as $key => $values ) {
			foreach ( $values as $position => $id ) {
				$wpdb->update( $wpdb->posts, [ 'menu_order' => $menu_order_arr[ $position ] ], [ 'ID' => intval( $id ) ] );
			}
		}
	}

	function update_term_order() {
		global $wpdb;

		if ( empty( $_POST['order'] ) || empty( $_POST['taxonomy'] ) ) {
			return false;
		}

		parse_str( $_POST['order'], $data );
		$taxonomy = $_POST['taxonomy'];

		if ( ! is_array( $data ) || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$result = $wpdb->get_results( $wpdb->prepare( "
				SELECT count(*) as cnt, max(t.term_order) as max, min(t.term_order) as min 
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN (%s)
			", $taxonomy ) );

		if ( ! ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( "
				SELECT t.term_id 
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN (%s)
			", $taxonomy ) );
			foreach ( $results as $key => $result ) {
				$wpdb->update( $wpdb->terms, [ 'term_order' => $key + 1 ], [ 'term_id' => $result->term_id ] );
			}
		}


		$id_arr = [];
		foreach ( $data as $key => $values ) {
			foreach ( $values as $position => $id ) {
				$id_arr[] = $id;
			}
		}

		$term_order_arr = [];
		foreach ( $id_arr as $key => $id ) {
			$results = $wpdb->get_results( $wpdb->prepare("SELECT term_order FROM {$wpdb->terms} WHERE term_id = %d", intval( $id ) ) );
			foreach ( $results as $result ) {
				$term_order_arr[] = $result->term_order;
			}
		}

		sort( $term_order_arr );

		foreach ( $data as $key => $values ) {
			foreach ( $values as $position => $id ) {
				$wpdb->update( $wpdb->terms, [ 'term_order' => $term_order_arr[ $position ] ], [ 'term_id' => intval( $id ) ] );
			}
		}

		do_action( "sorted_{$taxonomy}" );
	}

	public function set_default_sorting_mode( $wp_query ) {
		if ( ! empty( $this->sorted_post_types ) ) {
			if ( function_exists( 'get_current_screen' ) && is_admin() ) {
				$screen = get_current_screen();
			} else {
				$screen = false;
			}
			if ( $screen && $screen->base == 'edit' ) {
				if ( isset( $wp_query->query['post_type'] ) && ! isset( $_GET['orderby'] ) ) {
					if ( in_array( $wp_query->query['post_type'], $this->sorted_post_types ) ) {
						$wp_query->set( 'orderby', 'menu_order' );
						$wp_query->set( 'order', 'ASC' );
					}
				}
			} else {
				if ( isset( $wp_query->query['post_type'] ) && ! is_array( $wp_query->query['post_type'] ) && in_array( $wp_query->query['post_type'], $this->sorted_post_types ) ) {
					if ( empty( $wp_query->query['suppress_filters'] ) ) {
						$orderby = $wp_query->get( 'orderby' );
						$order = $wp_query->get( 'order' );
						if ( $orderby == 'date' && $order == 'DESC' || ! $orderby && ! $order ) {
							$wp_query->set( 'orderby', 'menu_order' );
							$wp_query->set( 'order', 'ASC' );
						}
					}
				}
			}
		}
	}

	public function set_sorted_post_types( $post_types ) {
		$this->sorted_post_types = $post_types;
		add_action( 'pre_get_posts', [ $this, 'set_default_sorting_mode' ], 99 );
	}

	public function set_rwmb_validation_taxonomies( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "after-{$taxonomy}-table", function () { ?>
				<script>
					['addtag'].forEach(function(item) {
						var form = document.getElementById(item);
						if (form) {
							form.className = "rwmb-form";
						}
					});
				</script><?php } );
		}
	}

	public function set_sorted_taxonomies( $taxonomies ) {
		$this->sorted_taxonomies = $taxonomies;

		add_filter( 'get_terms_orderby', function ( $orderby, $query_vars, $_taxonomies ) use ( $taxonomies ) {

			if ( ! is_array( $_taxonomies ) && is_string( $_taxonomies ) ) {
				$_taxonomies = [ $_taxonomies ];
			}

			if ( ! is_array( $_taxonomies ) ) {
				return $orderby;
			}

			if ( array_intersect( $_taxonomies, $taxonomies ) ) {
				return 't.term_order' . ( $orderby ? ',' . $orderby : '' );
			} else {
				return $orderby;
			}
		}, 10, 3 );

		foreach ( $taxonomies AS $taxonomy ) {
			add_action( "created_term", [ $this, 'update_term_order_on_create' ], 99, 3 );
		}
	}

	public function update_term_order_on_create( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "
		UPDATE {$wpdb->terms} t 
		INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
		SET t.term_order = t.term_order + 1 
		WHERE t.term_id != %d AND tt.taxonomy IN (%s)", $term_id, $taxonomy ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->terms} SET term_order = 1 WHERE term_id = %d ", $term_id ) );

	}

	public function admin_enqueue_scripts( $hook = '' ) {
		global $ideapark_moderno_sort_notice;
		wp_enqueue_media();
		wp_enqueue_script( 'wp-color-picker' );
		$is_sortable_tax       = ( $hook == 'edit-tags.php' && ! empty( $this->sorted_taxonomies ) && isset( $_GET['taxonomy'] ) && in_array( $_GET['taxonomy'], $this->sorted_taxonomies ) );
		$is_sortable_post_type = ( ! empty( $this->sorted_post_types ) && isset( $_GET['post_type'] ) && ! isset( $_GET['taxonomy'] ) && in_array( $_GET['post_type'], $this->sorted_post_types ) );
		if ( $is_sortable_tax || $is_sortable_post_type ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_register_script( $this->_token . '-sort', esc_url( $this->assets_url ) . 'js/sort' . $this->script_suffix . '.js', [ 'jquery' ], $this->_version, true );
			wp_enqueue_script( $this->_token . '-sort' );
			wp_localize_script( $this->_token . '-sort', 'ideapark_sort_vars', [
				'notice'          => ( ( ! empty( $ideapark_moderno_sort_notice ) && ( $index = $is_sortable_tax ? $_GET['taxonomy'] : $_GET['post_type'] ) && array_key_exists( $index, $ideapark_moderno_sort_notice ) ) ) ? esc_html( $ideapark_moderno_sort_notice[ $index ] ) : '',
				'disableSortable' => esc_html__( 'Disable sortable', 'ideapark-moderno' )
			] );
		}

		if ( ! wp_script_is( 'wp-color-picker-alpha', 'enqueued' ) ) {
			if ( ! wp_script_is( 'wp-color-picker-alpha', 'registered' ) ) {
				wp_register_script( 'wp-color-picker-alpha', esc_url( $this->assets_url ) . 'js/wp-color-picker-alpha.min.js', [ 'wp-color-picker' ], '3.0.4', true );
			}
			wp_enqueue_script( 'wp-color-picker-alpha' );
		}

		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/settings' . $this->script_suffix . '.js', [ 'jquery' ], $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()


	public function admin_enqueue_styles( $hook = '' ) {
		wp_enqueue_style( 'wp-color-picker' );
		if (
			( $hook == 'edit-tags.php' && ! empty( $this->sorted_taxonomies ) && isset( $_GET['taxonomy'] ) && in_array( $_GET['taxonomy'], $this->sorted_taxonomies ) )
			||
			( ! empty( $this->sorted_post_types ) && isset( $_GET['post_type'] ) && ! isset( $_GET['taxonomy'] ) && in_array( $_GET['post_type'], $this->sorted_post_types ) )
		) {
			wp_register_style( $this->_token . '-sort', esc_url( $this->assets_url ) . 'css/sort.css', [], $this->_version );
			wp_enqueue_style( $this->_token . '-sort' );
		}
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', [], $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()


	public function load_plugin_textdomain() {
		$domain = 'ideapark-moderno';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		if ( ! load_textdomain( $domain, get_template_directory() . '/languages/' . $domain . '-' . $locale . '.mo' ) ) {
			load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
		}
	} // End load_plugin_textdomain ()


	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()


	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'ideapark-moderno' ), $this->_version );
	} // End __clone ()


	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'ideapark-moderno' ), $this->_version );
	} // End __wakeup ()


	public function install( $network_wide = false ) {

		$this->_log_version_number();
		$install = function () {
			global $wpdb;
			$query  = "SHOW COLUMNS FROM {$wpdb->terms} LIKE 'term_order'";
			$result = $wpdb->query( $query );

			if ( $result == 0 ) {
				$wpdb->query( "ALTER TABLE {$wpdb->terms} ADD `term_order` INT( 4 ) NULL DEFAULT '0'" );
			}
		};

		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( [ 'fields' => 'ids' ] ) as $blog_id ) {
				switch_to_blog( $blog_id );
				$install();
				restore_current_blog();
			}
		} else {
			$install();
		}

	} // End install ()


	private function _log_version_number() {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}