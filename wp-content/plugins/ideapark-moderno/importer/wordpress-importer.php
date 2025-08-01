<?php
/*
Plugin Name: WordPress Importer
Plugin URI: http://wordpress.org/extend/plugins/wordpress-importer/
Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.6.3
Text Domain: wordpress-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	return;
}

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require $class_wp_importer;
	}
}

/**
 * WordPress Importer class for managing the import process of a WXR file
 *
 * @package    WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
	class WP_Import extends WP_Importer {
		var $max_wxr_version = 1.2; // max. supported WXR version

		var $id; // WXR attachment ID
		// information to import from WXR file
		var $version;
		var $authors = [];
		var $posts = [];
		var $terms = [];
		var $categories = [];
		var $tags = [];
		var $base_url = '';

		// mappings from old information to new
		var $processed_authors = [];
		var $author_mapping = [];
		var $processed_terms = [];
		var $processed_posts = [];
		var $post_orphans = [];
		var $processed_menu_items = [];
		var $menu_item_orphans = [];
		var $missing_menu_items = [];
		var $term_order = 0;

		var $fetch_attachments = false;
		var $url_remap = [];
		var $featured_images = [];
		var $post_exists = false;

		var $placeholder_path = '';
		var $placeholder_post_id = '';

		/**
		 * Registered callback function for the WordPress Importer
		 *
		 * Manages the three separate stages of the WXR import process
		 */
		function dispatch() {
			$this->header();

			$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
			switch ( $step ) {
				case 0:
					$this->greet();
					break;
				case 1:
					check_admin_referer( 'import-upload' );
					if ( $this->handle_upload() ) {
						$this->import_options();
					}
					break;
				case 2:
					check_admin_referer( 'import-wordpress' );
					$this->fetch_attachments = ( ! empty( $_POST['fetch_attachments'] ) && $this->allow_fetch_attachments() );
					$this->id                = (int) $_POST['import_id'];
					$file                    = get_attached_file( $this->id );
					set_time_limit( 0 );
					$this->import( $file );
					break;
			}

			$this->footer();
		}

		/**
		 * The main controller for the actual import stage.
		 *
		 * @param string $file Path to the WXR file for importing
		 */

		function import( $file ) {

			add_filter( 'import_post_meta_key', [ $this, 'is_valid_meta_key' ] );
			add_filter( 'http_request_timeout', [ &$this, 'bump_request_timeout' ] );

			$this->import_start( $file );

			$this->get_author_mapping();

			wp_suspend_cache_invalidation( true );
			$this->process_categories();
			$this->process_tags();
			$this->process_terms();
			$this->process_posts();
			wp_suspend_cache_invalidation( false );

			// update incorrect/missing information in the DB
			$this->backfill_parents();
			$this->backfill_attachment_urls();
			$this->remap_featured_images();

			$this->import_end();
		}

		/**
		 * Parses the WXR file and prepares us for the task of processing parsed data
		 *
		 * @param string $file Path to the WXR file for importing
		 */
		function import_start( $file ) {
			if ( ! is_file( $file ) ) {
				echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'ideapark-moderno' ) . '</strong><br />';
				echo sprintf( esc_html__( 'The file %s does not exist, please try again.', 'ideapark-moderno' ), $file ) . '</p>';
				$this->footer();
				die();
			}

			$import_data = $this->parse( $file );

			if ( is_wp_error( $import_data ) ) {
				echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'ideapark-moderno' ) . '</strong><br />';
				echo esc_html( $import_data->get_error_message() ) . '</p>';
				$this->footer();
				die();
			}

			$this->version = $import_data['version'];
			$this->get_authors_from_import( $import_data );
			$this->posts      = $import_data['posts'];
			$this->terms      = $import_data['terms'];
			$this->categories = $import_data['categories'];
			$this->tags       = $import_data['tags'];
			$this->base_url   = esc_url( $import_data['base_url'] );

			wp_defer_term_counting( true );
			wp_defer_comment_counting( true );

			do_action( 'import_start' );
		}

		/**
		 * Performs post-import cleanup of files and the cache
		 */
		function import_end() {
			wp_import_cleanup( $this->id );

			wp_cache_flush();
			foreach ( get_taxonomies() as $tax ) {
				delete_option( "{$tax}_children" );
				_get_term_hierarchy( $tax );
			}

			wp_defer_term_counting( false );
			wp_defer_comment_counting( false );

			echo __( 'All done.', 'ideapark-moderno' ) . '<br />';

			do_action( 'import_end' );
		}

		/**
		 * Handles the WXR upload and initial parsing of the file to prepare for
		 * displaying author import options
		 *
		 * @return bool False if error uploading or invalid file, true otherwise
		 */
		function handle_upload() {
			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				echo '<p><strong>' . __( 'Sorry, there has been an error.', 'ideapark-moderno' ) . '</strong><br />';
				echo esc_html( $file['error'] ) . '</p>';

				return false;
			} else if ( ! file_exists( $file['file'] ) ) {
				echo '<p><strong>' . __( 'Sorry, there has been an error.', 'ideapark-moderno' ) . '</strong><br />';
				printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'ideapark-moderno' ), esc_html( $file['file'] ) );
				echo '</p>';

				return false;
			}

			$this->id    = (int) $file['id'];
			$import_data = $this->parse( $file['file'] );
			if ( is_wp_error( $import_data ) ) {
				echo '<p><strong>' . __( 'Sorry, there has been an error.', 'ideapark-moderno' ) . '</strong><br />';
				echo esc_html( $import_data->get_error_message() ) . '</p>';

				return false;
			}

			$this->version = $import_data['version'];
			if ( $this->version > $this->max_wxr_version ) {
				echo '<div class="error"><p><strong>';
				printf( __( 'This WXR file (version %s) may not be supported by this version of the importer. Please consider updating.', 'ideapark-moderno' ), esc_html( $import_data['version'] ) );
				echo '</strong></p></div>';
			}

			$this->get_authors_from_import( $import_data );

			return true;
		}

		/**
		 * Retrieve authors from parsed WXR data
		 *
		 * Uses the provided author information from WXR 1.1 files
		 * or extracts info from each post for WXR 1.0 files
		 *
		 * @param array $import_data Data returned by a WXR parser
		 */
		function get_authors_from_import( $import_data ) {
			if ( ! empty( $import_data['authors'] ) ) {
				$this->authors = $import_data['authors'];
				// no author information, grab it from the posts
			} else {
				foreach ( $import_data['posts'] as $post ) {
					$login = sanitize_user( $post['post_author'], true );
					if ( empty( $login ) ) {
						printf( __( 'Failed to import author %s. Their posts will be attributed to the current user.', 'ideapark-moderno' ), esc_html( $post['post_author'] ) );
						echo '<br />';
						continue;
					}

					if ( ! isset( $this->authors[ $login ] ) ) {
						$this->authors[ $login ] = [
							'author_login'        => $login,
							'author_display_name' => $post['post_author']
						];
					}
				}
			}
		}

		/**
		 * Display pre-import options, author importing/mapping and option to
		 * fetch attachments
		 */
		function import_options() {
			$j = 0;
			?>
			<form action="<?php echo admin_url( 'admin.php?import=wordpress&amp;step=2' ); ?>" method="post">
				<?php wp_nonce_field( 'import-wordpress' ); ?>
				<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>"/>

				<?php if ( ! empty( $this->authors ) ) : ?>
					<h3><?php _e( 'Assign Authors', 'ideapark-moderno' ); ?></h3>
					<p><?php _e( 'To make it easier for you to edit and save the imported content, you may want to reassign the author of the imported item to an existing user of this site. For example, you may want to import all the entries as <code>admin</code>s entries.', 'ideapark-moderno' ); ?></p>
					<?php if ( $this->allow_create_users() ) : ?>
						<p><?php printf( __( 'If a new user is created by WordPress, a new password will be randomly generated and the new user&#8217;s role will be set as %s. Manually changing the new user&#8217;s details will be necessary.', 'ideapark-moderno' ), esc_html( get_option( 'default_role' ) ) ); ?></p>
					<?php endif; ?>
					<ol id="authors">
						<?php foreach ( $this->authors as $author ) : ?>
							<li><?php $this->author_select( $j ++, $author ); ?></li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>

				<?php if ( $this->allow_fetch_attachments() ) : ?>
					<h3><?php _e( 'Import Attachments', 'ideapark-moderno' ); ?></h3>
					<p>
						<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments"/>
						<label
							for="import-attachments"><?php _e( 'Download and import file attachments', 'ideapark-moderno' ); ?></label>
					</p>
				<?php endif; ?>

				<p class="submit">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Submit', 'ideapark-moderno' ); ?>"/>
				</p>
			</form>
			<?php
		}

		/**
		 * Display import options for an individual author. That is, either create
		 * a new user based on import info or map to an existing user
		 *
		 * @param int   $n      Index for each author in the form
		 * @param array $author Author information, e.g. login, display name, email
		 */
		function author_select( $n, $author ) {
			_e( 'Import author:', 'ideapark-moderno' );
			echo ' <strong>' . esc_html( $author['author_display_name'] );
			if ( $this->version != '1.0' ) {
				echo ' (' . esc_html( $author['author_login'] ) . ')';
			}
			echo '</strong><br />';

			if ( $this->version != '1.0' ) {
				echo '<div style="margin-left:18px">';
			}

			$create_users = $this->allow_create_users();
			if ( $create_users ) {
				if ( $this->version != '1.0' ) {
					_e( 'or create new user with login name:', 'ideapark-moderno' );
					$value = '';
				} else {
					_e( 'as a new user:', 'ideapark-moderno' );
					$value = esc_attr( sanitize_user( $author['author_login'], true ) );
				}

				echo ' <input type="text" name="user_new[' . $n . ']" value="' . $value . '" /><br />';
			}

			if ( ! $create_users && $this->version == '1.0' ) {
				_e( 'assign posts to an existing user:', 'ideapark-moderno' );
			} else {
				_e( 'or assign posts to an existing user:', 'ideapark-moderno' );
			}
			wp_dropdown_users( [
				'name'            => "user_map[$n]",
				'multi'           => true,
				'show_option_all' => __( '- Select -', 'ideapark-moderno' )
			] );
			echo '<input type="hidden" name="imported_authors[' . $n . ']" value="' . esc_attr( $author['author_login'] ) . '" />';

			if ( $this->version != '1.0' ) {
				echo '</div>';
			}
		}

		/**
		 * Map old author logins to local user IDs based on decisions made
		 * in import options form. Can map to an existing user, create a new user
		 * or falls back to the current user in case of error with either of the previous
		 */
		function get_author_mapping() {
			if ( ! isset( $_POST['imported_authors'] ) ) {
				return;
			}

			$create_users = $this->allow_create_users();

			foreach ( (array) $_POST['imported_authors'] as $i => $old_login ) {
				// Multisite adds strtolower to sanitize_user. Need to sanitize here to stop breakage in process_posts.
				$santized_old_login = sanitize_user( $old_login, true );
				$old_id             = isset( $this->authors[ $old_login ]['author_id'] ) ? intval( $this->authors[ $old_login ]['author_id'] ) : false;

				if ( ! empty( $_POST['user_map'][ $i ] ) ) {
					$user = get_userdata( intval( $_POST['user_map'][ $i ] ) );
					if ( isset( $user->ID ) ) {
						if ( $old_id ) {
							$this->processed_authors[ $old_id ] = $user->ID;
						}
						$this->author_mapping[ $santized_old_login ] = $user->ID;
					}
				} else if ( $create_users ) {
					if ( ! empty( $_POST['user_new'][ $i ] ) ) {
						$user_id = wp_create_user( $_POST['user_new'][ $i ], wp_generate_password() );
					} else if ( $this->version != '1.0' ) {
						$user_data = [
							'user_login'   => $old_login,
							'user_pass'    => wp_generate_password(),
							'user_email'   => isset( $this->authors[ $old_login ]['author_email'] ) ? $this->authors[ $old_login ]['author_email'] : '',
							'display_name' => $this->authors[ $old_login ]['author_display_name'],
							'first_name'   => isset( $this->authors[ $old_login ]['author_first_name'] ) ? $this->authors[ $old_login ]['author_first_name'] : '',
							'last_name'    => isset( $this->authors[ $old_login ]['author_last_name'] ) ? $this->authors[ $old_login ]['author_last_name'] : '',
						];
						$user_id   = wp_insert_user( $user_data );
					}

					if ( ! is_wp_error( $user_id ) ) {
						if ( $old_id ) {
							$this->processed_authors[ $old_id ] = $user_id;
						}
						$this->author_mapping[ $santized_old_login ] = $user_id;
					} else {
						printf( __( 'Failed to create new user for %s. Their posts will be attributed to the current user.', 'ideapark-moderno' ), esc_html( $this->authors[ $old_login ]['author_display_name'] ) );
						if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
							echo ' ' . $user_id->get_error_message();
						}
						echo '<br />';
					}
				}

				// failsafe: if the user_id was invalid, default to the current user
				if ( ! isset( $this->author_mapping[ $santized_old_login ] ) ) {
					if ( $old_id ) {
						$this->processed_authors[ $old_id ] = (int) get_current_user_id();
					}
					$this->author_mapping[ $santized_old_login ] = (int) get_current_user_id();
				}
			}
		}

		/**
		 * Create new categories based on import information
		 *
		 * Doesn't create a new category if its slug already exists
		 */
		function process_categories() {
			$this->categories = apply_filters( 'wp_import_categories', $this->categories );

			if ( empty( $this->categories ) ) {
				return;
			}

			foreach ( $this->categories as $cat ) {
				// if the category already exists leave it alone
				$term_id = term_exists( $cat['category_nicename'], 'category' );
				if ( $term_id ) {
					if ( is_array( $term_id ) ) {
						$term_id = $term_id['term_id'];
					}
					if ( isset( $cat['term_id'] ) ) {
						$this->processed_terms[ intval( $cat['term_id'] ) ] = (int) $term_id;
					}
					continue;
				}

				$category_parent      = empty( $cat['category_parent'] ) ? 0 : category_exists( $cat['category_parent'] );
				$category_description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';
				$catarr               = [
					'category_nicename'    => $cat['category_nicename'],
					'category_parent'      => $category_parent,
					'cat_name'             => $cat['cat_name'],
					'category_description' => $category_description
				];
				$catarr               = wp_slash( $catarr );

				$id = wp_insert_category( $catarr );
				if ( ! is_wp_error( $id ) ) {
					if ( isset( $cat['term_id'] ) ) {
						$this->processed_terms[ intval( $cat['term_id'] ) ] = $id;
					}
				} else {
					printf( __( 'Failed to import category %s', 'ideapark-moderno' ), esc_html( $cat['category_nicename'] ) );
					if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
						echo ': ' . $id->get_error_message();
					}
					echo '<br />';
					continue;
				}

				$this->process_termmeta( $cat, $id );
			}

			unset( $this->categories );
		}

		/**
		 * Create new post tags based on import information
		 *
		 * Doesn't create a tag if its slug already exists
		 */
		function process_tags() {
			$this->tags = apply_filters( 'wp_import_tags', $this->tags );

			if ( empty( $this->tags ) ) {
				return;
			}

			foreach ( $this->tags as $tag ) {
				// if the tag already exists leave it alone
				$term_id = term_exists( $tag['tag_slug'], 'post_tag' );
				if ( $term_id ) {
					if ( is_array( $term_id ) ) {
						$term_id = $term_id['term_id'];
					}
					if ( isset( $tag['term_id'] ) ) {
						$this->processed_terms[ intval( $tag['term_id'] ) ] = (int) $term_id;
					}
					continue;
				}

				$tag      = wp_slash( $tag );
				$tag_desc = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
				$tagarr   = [ 'slug' => $tag['tag_slug'], 'description' => $tag_desc ];

				remove_all_actions( 'created_term' );
				$id = wp_insert_term( $tag['tag_name'], 'post_tag', $tagarr );
				if ( ! is_wp_error( $id ) ) {
					if ( isset( $tag['term_id'] ) ) {
						$this->processed_terms[ intval( $tag['term_id'] ) ] = $id['term_id'];
					}
				} else {
					printf( __( 'Failed to import post tag %s', 'ideapark-moderno' ), esc_html( $tag['tag_name'] ) );
					if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
						echo ': ' . $id->get_error_message();
					}
					echo '<br />';
					continue;
				}

				$this->process_termmeta( $tag, $id['term_id'] );
			}

			unset( $this->tags );
		}

		/**
		 * Create new terms based on import information
		 *
		 * Doesn't create a term its slug already exists
		 */
		function process_terms() {
			$this->terms = apply_filters( 'wp_import_terms', $this->terms );

			if ( empty( $this->terms ) ) {
				return;
			}

			foreach ( $this->terms as $term ) {
				// if the term already exists in the correct taxonomy leave it alone
				$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
				if ( $term_id ) {
					if ( is_array( $term_id ) ) {
						$term_id = $term_id['term_id'];
					}
					if ( isset( $term['term_id'] ) ) {
						$this->processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
					}
					continue;
				}

				if ( empty( $term['term_parent'] ) ) {
					$parent = 0;
				} else {
					$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
					if ( is_array( $parent ) ) {
						$parent = $parent['term_id'];
					}
				}
				$term        = wp_slash( $term );
				$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
				$termarr     = [
					'slug'        => $term['slug'],
					'description' => $description,
					'parent'      => intval( $parent )
				];

				remove_all_actions( 'created_term' );
				$id = wp_insert_term( $term['term_name'], $term['term_taxonomy'], $termarr );
				if ( ! is_wp_error( $id ) ) {
					if ( isset( $term['term_id'] ) ) {
						$this->processed_terms[ intval( $term['term_id'] ) ] = $id['term_id'];
					}
					global $wpdb;
					$wpdb->update( $wpdb->terms, [ 'term_order' => $id['term_id'] ], [ 'term_id' => $id['term_id'] ] );
				} else {
					printf( __( 'Failed to import %s %s', 'ideapark-moderno' ), esc_html( $term['term_taxonomy'] ), esc_html( $term['term_name'] ) );
					if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
						echo ': ' . $id->get_error_message() . print_r( $term, true );
					}
					echo '<br />';
					continue;
				}

				$this->process_termmeta( $term, $id['term_id'] );
			}

			unset( $this->terms );
		}

		/**
		 * Add metadata to imported term.
		 *
		 * @param array $term    Term data from WXR import.
		 * @param int   $term_id ID of the newly created term.
		 *
		 * @since 0.6.2
		 *
		 */
		public function process_termmeta( $term, $term_id ) {

			delete_term_meta( $term_id, 'order' );

			if ( ! isset( $term['termmeta'] ) ) {
				$term['termmeta'] = [];
			}

			/**
			 * Filters the metadata attached to an imported term.
			 *
			 * @param array $termmeta Array of term meta.
			 * @param int   $term_id  ID of the newly created term.
			 * @param array $term     Term data from the WXR import.
			 *
			 * @since 0.6.2
			 *
			 */
			$term['termmeta'] = apply_filters( 'wp_import_term_meta', $term['termmeta'], $term_id, $term );

			if ( empty( $term['termmeta'] ) ) {
				return;
			}

			foreach ( $term['termmeta'] as $meta ) {
				/**
				 * Filters the meta key for an imported piece of term meta.
				 *
				 * @param string $meta_key Meta key.
				 * @param int    $term_id  ID of the newly created term.
				 * @param array  $term     Term data from the WXR import.
				 *
				 * @since 0.6.2
				 *
				 */
				$key = apply_filters( 'import_term_meta_key', $meta['key'], $term_id, $term );
				if ( ! $key ) {
					continue;
				}

				// Export gets meta straight from the DB so could have a serialized string
				$value = maybe_unserialize( $meta['value'] );

				add_term_meta( $term_id, $key, $value );

				/**
				 * Fires after term meta is imported.
				 *
				 * @param int    $term_id ID of the newly created term.
				 * @param string $key     Meta key.
				 * @param mixed  $value   Meta value.
				 *
				 * @since 0.6.2
				 *
				 */
				do_action( 'import_term_meta', $term_id, $key, $value );
			}
		}

		/**
		 * Create new posts based on import information
		 *
		 * Posts marked as having a parent which doesn't exist will become top level items.
		 * Doesn't create a new post if: the post type doesn't exist, the given post ID
		 * is already noted as imported or a post with the same title and date already exists.
		 * Note that new/updated terms, comments and meta are imported for the last of the above.
		 */
		function process_posts() {
			global $wpdb;

			$this->posts = apply_filters( 'wp_import_posts', $this->posts );

			foreach ( $this->posts as $post ) {

				$post = apply_filters( 'wp_import_post_data_raw', $post );

				if ( ! post_type_exists( $post['post_type'] ) ) {
					printf( __( 'Failed to import &#8220;%s&#8221;: Invalid post type %s', 'ideapark-moderno' ),
						esc_html( $post['post_title'] ), esc_html( $post['post_type'] ) );
					echo '<br />';
					do_action( 'wp_import_post_exists', $post );
					continue;
				}

				if ( isset( $this->processed_posts[ $post['post_id'] ] ) && ! empty( $post['post_id'] ) ) {
					continue;
				}

				if ( $post['status'] == 'auto-draft' ) {
					continue;
				}

				if ( 'nav_menu_item' == $post['post_type'] ) {
					continue;
				}

				$post_type_object = get_post_type_object( $post['post_type'] );

				$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );

				/**
				 * Filter ID of the existing post corresponding to post currently importing.
				 *
				 * Return 0 to force the post to be imported. Filter the ID to be something else
				 * to override which existing post is mapped to the imported post.
				 *
				 * @param int   $post_exists Post ID, or 0 if post did not exist.
				 * @param array $post        The post array to be inserted.
				 *
				 * @see   post_exists()
				 * @since 0.6.2
				 *
				 */
				$post_exists       = apply_filters( 'wp_import_existing_post', $post_exists, $post );
				$this->post_exists = false;
				if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
					$this->post_exists = true;
					printf( __( '%s &#8220;%s&#8221; already exists.', 'ideapark-moderno' ), $post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
					echo '<br />';
					$comment_post_ID                                     = $post_id = $post_exists;
					$this->processed_posts[ intval( $post['post_id'] ) ] = intval( $post_exists );
				} else {
					$post_parent = (int) $post['post_parent'];
					if ( $post_parent ) {
						// if we already know the parent, map it to the new local ID
						if ( isset( $this->processed_posts[ $post_parent ] ) ) {
							$post_parent = $this->processed_posts[ $post_parent ];
							// otherwise record the parent for later
						} else {
							$this->post_orphans[ intval( $post['post_id'] ) ] = $post_parent;
							$post_parent                                      = 0;
						}
					}

					// map the post author
					$author = sanitize_user( $post['post_author'], true );
					if ( isset( $this->author_mapping[ $author ] ) ) {
						$author = $this->author_mapping[ $author ];
					} else {
						$author = (int) get_current_user_id();
					}

					$postdata = [
						'import_id'      => $post['post_id'],
						'post_author'    => $author,
						'post_date'      => $post['post_date'],
						'post_date_gmt'  => $post['post_date_gmt'],
						'post_content'   => $post['post_content'],
						'post_excerpt'   => $post['post_excerpt'],
						'post_title'     => $post['post_title'],
						'post_status'    => $post['status'],
						'post_name'      => $post['post_name'],
						'comment_status' => $post['comment_status'],
						'ping_status'    => $post['ping_status'],
						'guid'           => $post['guid'],
						'post_parent'    => $post_parent,
						'menu_order'     => $post['menu_order'],
						'post_type'      => $post['post_type'],
						'post_password'  => $post['post_password']
					];

					$original_post_ID = $post['post_id'];
					$postdata         = apply_filters( 'wp_import_post_data_processed', $postdata, $post );

					$postdata = wp_slash( $postdata );

					if ( 'attachment' == $postdata['post_type'] ) {
						$remote_url = ! empty( $post['attachment_url'] ) ? $post['attachment_url'] : $post['guid'];

						// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
						// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
						$postdata['upload_date'] = $post['post_date'];
						if ( isset( $post['postmeta'] ) ) {
							foreach ( $post['postmeta'] as $meta ) {
								if ( $meta['key'] == '_wp_attached_file' ) {
									if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) ) {
										$postdata['upload_date'] = $matches[0];
									}
									break;
								}
							}
						}

						$comment_post_ID = $post_id = $this->process_attachment( $postdata, $remote_url );

					} else {
						$comment_post_ID = $post_id = wp_insert_post( $postdata, true );
						do_action( 'wp_import_insert_post', $post_id, $original_post_ID, $postdata, $post );
					}

					if ( is_wp_error( $post_id ) ) {
						printf( __( 'Failed to import %s &#8220;%s&#8221;', 'ideapark-moderno' ),
							$post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
						if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
							echo ': ' . $post_id->get_error_message();
						}
						echo '<br />';
						continue;
					}

					if ( $post['is_sticky'] == 1 ) {
						stick_post( $post_id );
					}
				}

				// map pre-import ID to local ID
				$this->processed_posts[ intval( $post['post_id'] ) ] = (int) $post_id;

				if ( ! isset( $post['terms'] ) ) {
					$post['terms'] = [];
				}

				$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

				// add categories, tags and other terms
				if ( ! empty( $post['terms'] ) ) {
					$terms_to_set = [];
					foreach ( $post['terms'] as $term ) {
						// back compat with WXR 1.0 map 'tag' to 'post_tag'
						$taxonomy    = ( 'tag' == $term['domain'] ) ? 'post_tag' : $term['domain'];
						$term_exists = term_exists( $term['slug'], $taxonomy );
						$term_id     = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
						if ( ! $term_id ) {
							remove_all_actions( 'created_term' );
							$t = wp_insert_term( $term['name'], $taxonomy, [ 'slug' => $term['slug'] ] );
							if ( ! is_wp_error( $t ) ) {
								$term_id = $t['term_id'];
								do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
							} else {
								printf( __( 'Failed to import %s %s', 'ideapark-moderno' ), esc_html( $taxonomy ), esc_html( $term['name'] ) );
								if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
									echo ': ' . $t->get_error_message() . print_r( $term, true );
								}
								echo '<br />';
								do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
								continue;
							}
						}
						$terms_to_set[ $taxonomy ][] = intval( $term_id );
					}

					foreach ( $terms_to_set as $tax => $ids ) {
						$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
						do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
					}
					unset( $post['terms'], $terms_to_set );
				}

				if ( ! isset( $post['comments'] ) ) {
					$post['comments'] = [];
				}

				$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

				// add/update comments
				if ( ! empty( $post['comments'] ) ) {
					$num_comments      = 0;
					$inserted_comments = [];
					foreach ( $post['comments'] as $comment ) {
						$comment_id                                         = $comment['comment_id'];
						$newcomments[ $comment_id ]['comment_post_ID']      = $comment_post_ID;
						$newcomments[ $comment_id ]['comment_author']       = $comment['comment_author'];
						$newcomments[ $comment_id ]['comment_author_email'] = $comment['comment_author_email'];
						$newcomments[ $comment_id ]['comment_author_IP']    = $comment['comment_author_IP'];
						$newcomments[ $comment_id ]['comment_author_url']   = $comment['comment_author_url'];
						$newcomments[ $comment_id ]['comment_date']         = $comment['comment_date'];
						$newcomments[ $comment_id ]['comment_date_gmt']     = $comment['comment_date_gmt'];
						$newcomments[ $comment_id ]['comment_content']      = $comment['comment_content'];
						$newcomments[ $comment_id ]['comment_approved']     = $comment['comment_approved'];
						$newcomments[ $comment_id ]['comment_type']         = $comment['comment_type'];
						$newcomments[ $comment_id ]['comment_parent']       = $comment['comment_parent'];
						$newcomments[ $comment_id ]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : [];
						if ( isset( $this->processed_authors[ $comment['comment_user_id'] ] ) ) {
							$newcomments[ $comment_id ]['user_id'] = $this->processed_authors[ $comment['comment_user_id'] ];
						}
					}
					ksort( $newcomments );

					foreach ( $newcomments as $key => $comment ) {
						// if this is a new post we can skip the comment_exists() check
						if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
							if ( isset( $inserted_comments[ $comment['comment_parent'] ] ) ) {
								$comment['comment_parent'] = $inserted_comments[ $comment['comment_parent'] ];
							}
							$comment                   = wp_filter_comment( $comment );
							$inserted_comments[ $key ] = wp_insert_comment( $comment );
							do_action( 'wp_import_insert_comment', $inserted_comments[ $key ], $comment, $comment_post_ID, $post );

							foreach ( $comment['commentmeta'] as $meta ) {
								$value = maybe_unserialize( $meta['value'] );
								add_comment_meta( $inserted_comments[ $key ], $meta['key'], $value );
							}

							$num_comments ++;
						}
					}
					unset( $newcomments, $inserted_comments, $post['comments'] );
				}

				if ( ! isset( $post['postmeta'] ) ) {
					$post['postmeta'] = [];
				}

				$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

				// add/update post meta
				if ( ! empty( $post['postmeta'] ) ) {
					foreach ( $post['postmeta'] as $meta ) {
						$key   = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
						$value = false;

						if ( '_edit_last' == $key ) {
							if ( isset( $this->processed_authors[ intval( $meta['value'] ) ] ) ) {
								$value = $this->processed_authors[ intval( $meta['value'] ) ];
							} else {
								$key = false;
							}
						}

						if ( $key ) {
							// export gets meta straight from the DB so could have a serialized string
							if ( ! $value ) {
								$value = maybe_unserialize( $meta['value'] );
							}

							if ( ! ( ( $current_meta = get_post_meta( $post_id, $key ) ) && in_array( $value, $current_meta ) ) ) {

								if ( ! empty( $current_meta ) && empty( $current_meta[0] ) ) {
									delete_post_meta( $post_id, $key, '' );
								}

								add_post_meta( $post_id, $key, $value );

								do_action( 'import_post_meta', $post_id, $key, $value );

								// if the post has a featured image, take note of this in case of remap
								if ( '_thumbnail_id' == $key ) {
									$this->featured_images[ $post_id ] = (int) $value;
								}
							}
						}
					}
				}
			}

			foreach ( $this->posts as $post ) {

				$post = apply_filters( 'wp_import_post_data_raw', $post );

				if ( 'nav_menu_item' == $post['post_type'] ) {

					if ( isset( $this->processed_posts[ $post['post_id'] ] ) && ! empty( $post['post_id'] ) ) {
						continue;
					}

					if ( $post['status'] == 'auto-draft' ) {
						continue;
					}

					if ( $post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_import_id' AND meta_value = %s", $post['post_id'] ) ) ) {
						$post_type_object = get_post_type_object( $post['post_type'] );
						printf( __( '%s &#8220;%s&#8221; already exists.', 'ideapark-moderno' ), $post_type_object->labels->singular_name, $post['post_id'] );
						echo '<br />';
						$this->processed_posts[ intval( $post['post_id'] ) ] = (int) $post_id;
						continue;
					}

					if ( ! empty( $post['postmeta'] ) ) {
						foreach ( $post['postmeta'] as $ii => $meta ) {
							if ( ! empty( $meta['value'] ) ) {
								if ( $meta['key'] == '_menu_item_img_id' || $meta['key'] == '_menu_item_html_block' ) {
									if ( isset( $this->processed_posts[ $meta['value'] ] ) ) {
										$post['postmeta'][ $ii ]['value'] = $this->processed_posts[ $meta['value'] ];
									} else {
										$post['postmeta'][ $ii ]['value'] = 0;
									}
								}
							}
						}
					}
					if ( $post_id = $this->process_menu_item( $post ) ) {
						$this->processed_posts[ intval( $post['post_id'] ) ] = (int) $post_id;
						add_post_meta( $post_id, '_import_id', $post['post_id'] );
					}
				}
			}

			unset( $this->posts );
		}

		/**
		 * Attempt to create a new menu item from import data
		 *
		 * Fails for draft, orphaned menu items and those without an associated nav_menu
		 * or an invalid nav_menu term. If the post type or term object which the menu item
		 * represents doesn't exist then the menu item will not be imported (waits until the
		 * end of the import to retry again before discarding).
		 *
		 * @param array $item Menu item details from WXR file
		 */
		function process_menu_item( $item ) {
			// skip draft, orphaned menu items
			if ( 'draft' == $item['status'] ) {
				return;
			}

			$menu_slug = false;
			if ( isset( $item['terms'] ) ) {
				// loop through terms, assume first nav_menu term is correct menu
				foreach ( $item['terms'] as $term ) {
					if ( 'nav_menu' == $term['domain'] ) {
						$menu_slug = $term['slug'];
						break;
					}
				}
			}

			// no nav_menu term associated with this menu item
			if ( ! $menu_slug ) {
				if ( IDEAPARK_DEMO ) {
					_e( 'Menu item skipped due to missing menu slug', 'ideapark-moderno' );
					echo '<br />';
				}

				return;
			}

			$menu_id = term_exists( $menu_slug, 'nav_menu' );
			if ( ! $menu_id ) {
				printf( __( 'Menu item skipped due to invalid menu slug: %s', 'ideapark-moderno' ), esc_html( $menu_slug ) );
				echo '<br />';

				return;
			} else {
				$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;
			}

			$m = [];

			foreach ( $item['postmeta'] as $meta ) {
				$m[ $meta['key'] ] = $meta['value'];
			}

			if ( 'taxonomy' == $m['_menu_item_type'] && isset( $this->processed_terms[ intval( $m['_menu_item_object_id'] ) ] ) ) {
				$m['_menu_item_object_id'] = $this->processed_terms[ intval( $m['_menu_item_object_id'] ) ];
			} else if ( 'post_type' == $m['_menu_item_type'] && isset( $this->processed_posts[ intval( $m['_menu_item_object_id'] ) ] ) ) {
				$m['_menu_item_object_id'] = $this->processed_posts[ intval( $m['_menu_item_object_id'] ) ];
			} else if ( 'custom' != $m['_menu_item_type'] && 'post_type_archive' != $m['_menu_item_type'] ) {
				// associated object is missing or not imported yet, we'll retry later
				$this->missing_menu_items[] = $item;

				return;
			}

			if ( isset( $this->processed_menu_items[ intval( $m['_menu_item_menu_item_parent'] ) ] ) ) {
				$m['_menu_item_menu_item_parent'] = $this->processed_menu_items[ intval( $m['_menu_item_menu_item_parent'] ) ];
			} else if ( $m['_menu_item_menu_item_parent'] ) {
				$this->menu_item_orphans[ intval( $item['post_id'] ) ] = (int) $m['_menu_item_menu_item_parent'];
				$m['_menu_item_menu_item_parent']                      = 0;
			}

			// wp_update_nav_menu_item expects CSS classes as a space separated string
			$m['_menu_item_classes'] = maybe_unserialize( $m['_menu_item_classes'] );
			if ( is_array( $m['_menu_item_classes'] ) ) {
				$m['_menu_item_classes'] = implode( ' ', $m['_menu_item_classes'] );
			}

			$args = [
				'menu-item-object-id'   => $m['_menu_item_object_id'],
				'menu-item-object'      => $m['_menu_item_object'],
				'menu-item-parent-id'   => $m['_menu_item_menu_item_parent'],
				'menu-item-position'    => intval( $item['menu_order'] ),
				'menu-item-type'        => $m['_menu_item_type'],
				'menu-item-title'       => $item['post_title'],
				'menu-item-url'         => $m['_menu_item_url'],
				'menu-item-description' => $item['post_content'],
				'menu-item-attr-title'  => $item['post_excerpt'],
				'menu-item-target'      => $m['_menu_item_target'],
				'menu-item-classes'     => $m['_menu_item_classes'],
				'menu-item-xfn'         => $m['_menu_item_xfn'],
				'menu-item-status'      => $item['status']
			];

			$id = wp_update_nav_menu_item( $menu_id, 0, $args );

			do_action( 'ideapark_import_nav_meta', $id, $m, $this->processed_posts, $this->processed_terms );

			if ( $id && ! is_wp_error( $id ) ) {
				$this->processed_menu_items[ intval( $item['post_id'] ) ] = (int) $id;
			}

			return $id;
		}

		/**
		 * If fetching attachments is enabled then attempt to create a new attachment
		 *
		 * @param array  $post Attachment post details from WXR
		 * @param string $url  URL to fetch attachment from
		 *
		 * @return int|WP_Error Post ID on success, WP_Error otherwise
		 */
		function process_attachment( $post, $url ) {
//		if ( ! $this->fetch_attachments )
//			return new WP_Error( 'attachment_processing_error',
//				__( 'Fetching attachments is not enabled', 'ideapark-moderno' ) );

			// if the URL is absolute, but does not contain address, then upload it assuming base_site_url

			if ( ! $this->fetch_attachments && $this->placeholder_post_id ) {
				return $this->placeholder_post_id;
			}

			if ( ! $this->fetch_attachments ) {
				$url = $this->placeholder_path;
			}

			if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
				$url = rtrim( $this->base_url, '/' ) . $url;
			}

			$upload = $this->fetch_remote_file( $url, $post );

			if ( is_wp_error( $upload ) ) {
				return $upload;
			}

			if ( $info = wp_check_filetype( $upload['file'] ) ) {
				$post['post_mime_type'] = $info['type'];
			} else {
				return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'ideapark-moderno' ) );
			}

			$post['guid'] = $upload['url'];


			// as per wp-admin/includes/upload.php
			// /wp-content/plugins/ideapark-moderno/assets/img/placeholder.jpg
			$post_id = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

			// remap resized image URLs, works by stripping the extension and remapping the URL stub.
			if ( preg_match( '!^image/!', $info['type'] ) ) {
				$parts = pathinfo( $url );
				$name  = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

				$parts_new = pathinfo( $upload['url'] );
				$name_new  = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

				$this->url_remap[ $parts['dirname'] . '/' . $name ] = $parts_new['dirname'] . '/' . $name_new;
			}

			if ( ! $this->fetch_attachments ) {
				$this->placeholder_post_id = $post_id;
			}

			return $post_id;
		}

		/**
		 * Attempt to download a remote file attachment
		 *
		 * @param string $url  URL of item to fetch
		 * @param array  $post Attachment details
		 *
		 * @return array|WP_Error Local file location details on success, WP_Error otherwise
		 */
		function fetch_remote_file( $url, $post ) {
			static $api;

			if ( ! $api ) {
				$api = ideapark_api_url();
			}
			if ( $api != IDEAPARK_API_URL ) {
				$api_host_from = parse_url( IDEAPARK_API_URL, PHP_URL_HOST );
				$api_host_to   = parse_url( $api, PHP_URL_HOST );
				$url           = str_replace( $api_host_from, $api_host_to, $url );
			}

			// extract the file name and extension from the url
			$file_name = basename( $url );

			// get placeholder file in the upload dir with a unique, sanitized filename
			$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );
			if ( $upload['error'] ) {
				return new WP_Error( 'upload_dir_error', $upload['error'] );
			}

			// fetch the remote url and write it to the placeholder file
			$response = wp_remote_get( $url, [
				'filename'  => $upload['file'],
				'stream'    => true,
				'timeout'   => 30,
				'sslverify' => false
			] );

			// request failed
			if ( ! $response || is_wp_error( $response ) ) {
				@unlink( $upload['file'] );

				return new WP_Error( 'import_file_error', __( 'Remote server did not respond', 'ideapark-moderno' ) . ( is_wp_error( $response ) ? ': ' . $response->get_error_message() : '' ) );
			}

			// make sure the fetch was successful
			if ( $response['response']['code'] != '200' ) {
				@unlink( $upload['file'] );

				return new WP_Error( 'import_file_error', sprintf( __( 'Remote server returned error response %1$d %2$s', 'ideapark-moderno' ), esc_html( $response['response']['code'] ), get_status_header_desc( $response['response']['code'] ) ) );
			}

			$filesize = filesize( $upload['file'] );

			if ( isset( $response['headers']['content-length'] ) && $filesize != $response['headers']['content-length'] ) {
				@unlink( $upload['file'] );

				return new WP_Error( 'import_file_error', __( 'Remote file is incorrect size', 'ideapark-moderno' ) );
			}

			if ( 0 == $filesize ) {
				@unlink( $upload['file'] );

				return new WP_Error( 'import_file_error', __( 'Zero size file downloaded', 'ideapark-moderno' ) );
			}

			$max_size = (int) $this->max_attachment_size();
			if ( ! empty( $max_size ) && $filesize > $max_size ) {
				@unlink( $upload['file'] );

				return new WP_Error( 'import_file_error', sprintf( __( 'Remote file is too large, limit is %s', 'ideapark-moderno' ), size_format( $max_size ) ) );
			}

			// keep track of the old and new urls so we can substitute them later
			$this->url_remap[ $url ]          = $upload['url'];
			$this->url_remap[ $post['guid'] ] = $upload['url']; // r13735, really needed?
			// keep track of the destination if the remote url is redirected somewhere else
			if ( isset( $response['response']['x-final-location'] ) && $response['response']['x-final-location'] != $url ) {
				$this->url_remap[ $response['response']['x-final-location'] ] = $upload['url'];
			}

			return $upload;
		}

		/**
		 * Attempt to associate posts and menu items with previously missing parents
		 *
		 * An imported post's parent may not have been imported when it was first created
		 * so try again. Similarly for child menu items and menu items which were missing
		 * the object (e.g. post) they represent in the menu
		 */
		function backfill_parents() {
			global $wpdb;

			// find parents for post orphans
			foreach ( $this->post_orphans as $child_id => $parent_id ) {
				$local_child_id = $local_parent_id = false;
				if ( isset( $this->processed_posts[ $child_id ] ) ) {
					$local_child_id = $this->processed_posts[ $child_id ];
				}
				if ( isset( $this->processed_posts[ $parent_id ] ) ) {
					$local_parent_id = $this->processed_posts[ $parent_id ];
				}

				if ( $local_child_id && $local_parent_id ) {
					$wpdb->update( $wpdb->posts, [ 'post_parent' => $local_parent_id ], [ 'ID' => $local_child_id ], '%d', '%d' );
				}
			}

			// all other posts/terms are imported, retry menu items with missing associated object
			$missing_menu_items = $this->missing_menu_items;
			foreach ( $missing_menu_items as $item ) {
				$this->process_menu_item( $item );
			}

			// find parents for menu item orphans
			foreach ( $this->menu_item_orphans as $child_id => $parent_id ) {
				$local_child_id = $local_parent_id = 0;
				if ( isset( $this->processed_menu_items[ $child_id ] ) ) {
					$local_child_id = $this->processed_menu_items[ $child_id ];
				}
				if ( isset( $this->processed_menu_items[ $parent_id ] ) ) {
					$local_parent_id = $this->processed_menu_items[ $parent_id ];
				}

				if ( $local_child_id && $local_parent_id ) {
					update_post_meta( $local_child_id, '_menu_item_menu_item_parent', (int) $local_parent_id );
				}
			}
		}

		/**
		 * Use stored mapping information to update old attachment URLs
		 */
		function backfill_attachment_urls() {
			global $wpdb;
			// make sure we do the longest urls first, in case one is a substring of another
			uksort( $this->url_remap, [ &$this, 'cmpr_strlen' ] );

			foreach ( $this->url_remap as $from_url => $to_url ) {
				// remap urls in post_content
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url ) );
				// remap enclosure urls
				$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url ) );
			}
		}

		/**
		 * Update _thumbnail_id meta to new, imported attachment IDs
		 */
		function remap_featured_images() {
			// cycle through posts that have a featured image
			foreach ( $this->featured_images as $post_id => $value ) {
				if ( isset( $this->processed_posts[ $value ] ) ) {
					$new_id = $this->processed_posts[ $value ];
					// only update if there's a difference
					if ( $new_id != $value ) {
						update_post_meta( $post_id, '_thumbnail_id', $new_id );
					}
				}
			}
		}

		/**
		 * Parse a WXR file
		 *
		 * @param string $file Path to WXR file for parsing
		 *
		 * @return array Information gathered from the WXR file
		 */
		function parse( $file ) {
			$parser = new Ideapark_WXR_Parser();

			return $parser->parse( $file );
		}

		// Display import page title
		function header() {
			echo '<div class="wrap">';
			echo '<h2>' . __( 'Import WordPress', 'ideapark-moderno' ) . '</h2>';

			$updates  = get_plugin_updates();
			$basename = plugin_basename( __FILE__ );
			if ( isset( $updates[ $basename ] ) ) {
				$update = $updates[ $basename ];
				echo '<div class="error"><p><strong>';
				printf( __( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', 'ideapark-moderno' ), $update->update->new_version );
				echo '</strong></p></div>';
			}
		}

		// Close div.wrap
		function footer() {
			echo '</div>';
		}

		/**
		 * Display introductory text and file upload form
		 */
		function greet() {
			echo '<div class="narrow">';
			echo '<p>' . __( 'Howdy! Upload your WordPress eXtended RSS (WXR) file and we&#8217;ll import the posts, pages, comments, custom fields, categories, and tags into this site.', 'ideapark-moderno' ) . '</p>';
			echo '<p>' . __( 'Choose a WXR (.xml) file to upload, then click Upload file and import.', 'ideapark-moderno' ) . '</p>';
			wp_import_upload_form( 'admin.php?import=wordpress&amp;step=1' );
			echo '</div>';
		}

		/**
		 * Decide if the given meta key maps to information we will want to import
		 *
		 * @param string $key The meta key to check
		 *
		 * @return string|bool The key if we do want to import, false if not
		 */
		function is_valid_meta_key( $key ) {
			// skip attachment metadata since we'll regenerate it from scratch
			// skip _edit_lock as not relevant for import
			if ( in_array( $key, [ '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ] ) ) {
				return false;
			}

			return $key;
		}

		/**
		 * Decide whether or not the importer is allowed to create users.
		 * Default is true, can be filtered via import_allow_create_users
		 *
		 * @return bool True if creating users is allowed
		 */
		function allow_create_users() {
			return apply_filters( 'import_allow_create_users', true );
		}

		/**
		 * Decide whether or not the importer should attempt to download attachment files.
		 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
		 * made at the import options screen must also be true, false here hides that checkbox.
		 *
		 * @return bool True if downloading attachments is allowed
		 */
		function allow_fetch_attachments() {
			return apply_filters( 'import_allow_fetch_attachments', true );
		}

		/**
		 * Decide what the maximum file size for downloaded attachments is.
		 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
		 *
		 * @return int Maximum attachment file size to import
		 */
		function max_attachment_size() {
			return apply_filters( 'import_attachment_size_limit', 0 );
		}

		/**
		 * Added to http_request_timeout filter to force timeout at 60 seconds during import
		 * @return int 60
		 */
		function bump_request_timeout( $val ) {
			return 60;
		}

		// return the difference in length between two strings
		function cmpr_strlen( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	}

} // class_exists( 'WP_Importer' )