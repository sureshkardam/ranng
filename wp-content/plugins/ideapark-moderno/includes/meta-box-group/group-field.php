<?php
/**
 * Group field class.
 *
 * @package    Meta Box
 * @subpackage Meta Box Group
 */

/**
 * Class for group field.
 *
 * @package    Meta Box
 * @subpackage Meta Box Group
 */
class RWMB_Group_Field extends RWMB_Field {
	/**
	 * Queue to store the group fields' meta(s). Used to get child field meta.
	 *
	 * @var array
	 */
	protected static $meta_queue = [];

	/**
	 * Add hooks for sub-fields.
	 */
	public static function add_actions() {
		// Group field is the 1st param.
		$args = func_get_args();
		foreach ( $args[0]['fields'] as $field ) {
			RWMB_Field::call( $field, 'add_actions' );
		}
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public static function admin_enqueue_scripts() {
		// Group field is the 1st param.
		$args   = func_get_args();
		$fields = $args[0]['fields'];

		// Load clone script conditionally.
		foreach ( $fields as $field ) {
			if ( $field['clone'] ) {
				wp_enqueue_script( 'rwmb-clone', RWMB_JS_URL . 'clone.js', [ 'jquery-ui-sortable' ], RWMB_VER, true );
				break;
			}
		}

		// Enqueue sub-fields scripts and styles.
		foreach ( $fields as $field ) {
			RWMB_Field::call( $field, 'admin_enqueue_scripts' );
		}

		// Use helper function to get correct URL to current folder, which can be used in themes/plugins.
		list( , $url ) = RWMB_Loader::get_path( __DIR__ );
		wp_enqueue_style( 'rwmb-group', $url . 'group.css', [], filemtime( __DIR__ . '/group.css' ) );
		wp_enqueue_script( 'rwmb-group', $url . 'group.js', [ 'jquery', 'underscore' ], filemtime( __DIR__ . '/group.js' ), true );
		wp_localize_script(
			'rwmb-group',
			'RWMB_Group',
			[
				'confirmRemove' => __( 'Are you sure you want to remove this group?', 'ideapark-moderno' ),
				'on'            => __( 'On', 'ideapark-moderno' ),
				'off'           => __( 'Off', 'ideapark-moderno' ),
				'yes'           => __( 'Yes', 'ideapark-moderno' ),
				'no'            => __( 'No', 'ideapark-moderno' ),
			]
		);
	}

	/**
	 * Get group field HTML.
	 *
	 * @param mixed $meta  Meta value.
	 * @param array $field Field parameters.
	 *
	 * @return string
	 */
	public static function html( $meta, $field ) {
		ob_start();

		self::output_collapsible_elements( $field, $meta );

		// Add filter to child field meta value, make sure it's added only once.
		if ( empty( self::$meta_queue ) ) {
			add_filter( 'rwmb_raw_meta', [ __CLASS__, 'child_field_meta' ], 10, 3 );
		}

		// Add group value to the queue.
		array_unshift( self::$meta_queue, $meta );

		foreach ( $field['fields'] as $child_field ) {
			$child_field['field_name']       = self::child_field_name( $field['field_name'], $child_field['field_name'] );
			$child_field['attributes']['id'] = self::child_field_id( $field, $child_field );
			$child_field['std']              = self::child_field_std( $field, $child_field, $meta );

			self::child_field_clone_default( $field, $child_field );

			if ( in_array( $child_field['type'], [ 'file', 'image' ], true ) ) {
				$child_field['input_name'] = '_file_' . uniqid();
				$child_field['index_name'] = self::child_field_name( $field['field_name'], $child_field['index_name'] );
			}

			self::call( 'show', $child_field, RWMB_Group::$saved );
		}

		// Remove group value from the queue.
		array_shift( self::$meta_queue );

		// Remove filter to child field meta value and reset class's parent field's meta.
		if ( empty( self::$meta_queue ) ) {
			remove_filter( 'rwmb_raw_meta', [ __CLASS__, 'child_field_meta' ] );
		}

		return ob_get_clean();
	}

	/**
	 * Output collapsible elements for groups.
	 *
	 * @param array $field Group field parameters.
	 */
	protected static function output_collapsible_elements( $field, $meta ) {
		if ( ! $field['collapsible'] ) {
			return;
		}

		// Group title.
		$title_attributes = [
			'class'        => 'rwmb-group-title',
			'data-options' => $field['group_title'],
		];

		$title                            = self::normalize_group_title( $field['group_title'] );
		$title_attributes['data-options'] = [
			'type'    => 'text',
			'content' => $title,
			'fields'  => self::get_child_field_ids( $field ),
		];

		echo '<div class="rwmb-group-title-wrapper">';
		echo '<h4 ', self::render_attributes( $title_attributes ), '>', $title, '</h4>';
		if ( $field['clone'] ) {
			echo '<a href="javascript:;" class="rwmb-group-remove">', esc_html__( 'Remove', 'ideapark-moderno' ), '</a>';
		}
		echo '</div>';

		// Collapse/expand icon.
		$default_state = ( isset( $field['default_state'] ) && $field['default_state'] === 'expanded' ) ? 'true' : 'false';
		echo '<button aria-expanded="' . esc_attr( $default_state ) . '" class="rwmb-group-toggle-handle button-link"><span class="rwmb-group-toggle-indicator" aria-hidden="true"></span></button>';
	}

	private static function normalize_group_title( $group_title ) {
		if ( is_string( $group_title ) ) {
			return $group_title;
		}
		$fields = array_filter( array_map( 'trim', explode( ',', $group_title['field'] . ',' ) ) );
		$fields = array_map( function ( $field ) {
			return '{' . $field . '}';
		}, $fields );

		$separator = isset( $group_title['separator'] ) ? $group_title['separator'] : ' ';

		return implode( $separator, $fields );
	}

	/**
	 * Change the way we get meta value for child fields.
	 *
	 * @param mixed $meta        Meta value.
	 * @param array $child_field Child field.
	 * @param bool  $saved       Has the meta box been saved.
	 *
	 * @return mixed
	 */
	public static function child_field_meta( $meta, $child_field, $saved ) {
		$group_meta = reset( self::$meta_queue );
		$child_id   = $child_field['id'];
		if ( isset( $group_meta[ $child_id ] ) ) {
			$meta = $group_meta[ $child_id ];
		}

		// Fix value for date time timestamp.
		if (
			in_array( $child_field['type'], [ 'date', 'datetime', 'time' ], true )
			&& ! empty( $child_field['timestamp'] )
			&& isset( $meta['timestamp'] )
		) {
			$meta = $meta['timestamp'];
		}

		return $meta;
	}

	/**
	 * Get meta value, make sure value is an array (of arrays if field is cloneable).
	 * Don't escape value.
	 *
	 * @param int   $post_id Post ID.
	 * @param bool  $saved   Is the meta box saved.
	 * @param array $field   Field parameters.
	 *
	 * @return mixed
	 */
	public static function meta( $post_id, $saved, $field ) {
		if ( empty( $field['id'] ) ) {
			return '';
		}

		// Get raw meta.
		$raw_meta = self::call( $field, 'raw_meta', $post_id );

		// Use $field['std'] only when the meta box hasn't been saved (i.e. the first time we run).
		$meta = ! $saved || ! $field['save_field'] ? $field['std'] : $raw_meta;

		if ( $field['clone'] ) {
			$meta = is_array( $raw_meta ) ? $raw_meta : [];

			// If clone empty start = false (default),
			// ensure $meta is an array with values so that the foreach loop in self::show() runs properly.
			if ( ! $field['clone_empty_start'] && empty( $meta ) ) {
				$meta = [ $field['std'] ];
			}

			// Always add the first item to the beginning of the array for the template.
			// We will need to remove it later before saving.
			array_unshift( $meta, $field['std'] );
		}

		return $meta;
	}

	/**
	 * Set value of meta before saving into database.
	 *
	 * @param mixed $new     The submitted meta value.
	 * @param mixed $old     The existing meta value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field parameters.
	 *
	 * @return array
	 */
	public static function value( $new, $old, $post_id, $field ) {
		if ( empty( $field['fields'] ) || ! is_array( $field['fields'] ) ) {
			return [];
		}
		if ( ! $new || ! is_array( $new ) ) {
			$new = [];
		}
		$new = self::get_sub_values( $field['fields'], $new, $post_id );

		return self::sanitize( $new, $old, $post_id, $field );
	}

	/**
	 * Recursively get values for sub-fields and sub-groups.
	 *
	 * @param array $fields  List of group fields.
	 * @param array $new     Group value.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	protected static function get_sub_values( $fields, $new, $post_id ) {
		$fields = array_filter( $fields, function ( $field ) {
			return in_array( $field['type'], [ 'file', 'image', 'group' ], true );
		} );

		foreach ( $fields as $field ) {
			$value = isset( $new[ $field['id'] ] ) ? $new[ $field['id'] ] : [];

			if ( 'group' === $field['type'] ) {
				$value               = $field['clone'] ? RWMB_Clone::value( $value, [], $post_id, $field ) : self::get_sub_values( $field['fields'], $value, $post_id );
				$new[ $field['id'] ] = $value;
				continue;
			}

			// File uploads.
			if ( $field['clone'] ) {
				$value = RWMB_File_Field::clone_value( $value, [], $post_id, $field, $new );
			} else {
				$index          = isset( $new["_index_{$field['id']}"] ) ? $new["_index_{$field['id']}"] : null;
				$field['index'] = $index;
				$value          = RWMB_File_Field::value( $value, '', $post_id, $field );
			}

			$new[ $field['id'] ] = $value;
		}

		return $new;
	}

	/**
	 * Sanitize value of meta before saving into database.
	 *
	 * @param mixed $new     The submitted meta value.
	 * @param mixed $old     The existing meta value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field parameters.
	 *
	 * @return array
	 */
	public static function sanitize( $new, $old, $post_id, $field ) {
		$sanitized = [];
		if ( ! $new || ! is_array( $new ) ) {
			return $sanitized;
		}

		foreach ( $new as $key => $value ) {
			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = self::sanitize( $value, '', '', [] );
			}
			if ( '' !== $value && [] !== $value ) {
				if ( is_int( $key ) ) {
					$sanitized[] = $value;
				} else {
					$sanitized[ $key ] = $value;
				}
			}
		}

		return $sanitized;
	}

	public static function normalize( $field ) {
		$field           = parent::normalize( $field );
		$field['fields'] = empty( $field['fields'] ) ? [] : RW_Meta_Box::normalize_fields( $field['fields'] );

		$field = wp_parse_args( $field, [
			'collapsible'   => false,
			'save_state'    => false,
			'group_title'   => $field['clone'] ? __( 'Entry {#}', 'ideapark-moderno' ) : __( 'Entry', 'ideapark-moderno' ),
			'default_state' => 'expanded',
		] );

		if ( $field['collapsible'] ) {
			$field['class'] .= ' rwmb-group-collapsible';

			if ( 'collapsed' === $field['default_state'] ) {
				$field['class'] .= ' rwmb-group-collapsed';
			}
		}
		// Add a new hidden field to save the collapse/expand state.
		if ( $field['save_state'] ) {
			$field['fields'][] = RWMB_Input_Field::normalize(
				[
					'type'       => 'hidden',
					'id'         => '_state',
					'std'        => $field['default_state'],
					'class'      => 'rwmb-group-state',
					'attributes' => [
						'data-current' => $field['default_state'],
					],
				]
			);
		}
		if ( ! $field['clone'] ) {
			$field['class'] .= ' rwmb-group-non-cloneable';
		}

		return $field;
	}

	/**
	 * Change child field name from 'child' to 'parent[child]'.
	 *
	 * @param string $parent Parent field's name.
	 * @param string $child  Child field's name.
	 *
	 * @return string
	 */
	protected static function child_field_name( $parent, $child ) {
		$pos  = strpos( $child, '[' );
		$pos  = false === $pos ? strlen( $child ) : $pos;
		$name = $parent . '[' . substr( $child, 0, $pos ) . ']' . substr( $child, $pos );

		return $name;
	}

	/**
	 * Change child field attribute id to from 'id' to 'parent_id'.
	 *
	 * @param array $parent Parent field.
	 * @param array $child  Child field.
	 *
	 * @return string
	 */
	protected static function child_field_id( $parent, $child ) {
		if ( isset( $child['attributes']['id'] ) && false === $child['attributes']['id'] ) {
			return false;
		}
		$parent = isset( $parent['attributes']['id'] ) ? $parent['attributes']['id'] : $parent['id'];
		$child  = isset( $child['attributes']['id'] ) ? $child['attributes']['id'] : $child['id'];

		return "{$parent}_{$child}";
	}

	/**
	 * Change child field std.
	 *
	 * @param array $parent Parent field settings.
	 * @param array $child  Child field settings.
	 * @param array $meta   The value of the parent field. When meta box is not saved, it's the parent's std value.
	 *
	 * @return string
	 */
	protected static function child_field_std( $parent, $child, $meta ) {
		// Respect 'std' value set in child field.
		if ( ! empty( $child['std'] ) ) {
			return $child['std'];
		}

		// $meta contains $parent['std'] or clone's std.
		$std = isset( $meta[ $child['id'] ] ) ? $meta[ $child['id'] ] : '';

		return $std;
	}

	protected static function get_child_field_ids( $field ) {
		$ids = [];
		foreach ( $field['fields'] as $sub_field ) {
			if ( ! isset( $sub_field['id'] ) ) {
				continue;
			}
			$sub_ids = isset( $sub_field['fields'] ) ? self::get_child_field_ids( $sub_field ) : [ $sub_field['id'] ];
			$ids     = array_merge( $ids, $sub_ids );
		}

		return $ids;
	}

	/**
	 * Setup clone_default for sub-fields.
	 * Test cases: https://docs.google.com/spreadsheets/d/10jQ70ygXH42qdaDpwIk52wYqYhKK3TiqaJvOxEYo5bQ/edit?usp=sharing
	 */
	protected static function child_field_clone_default( $parent, &$child ) {
		$clone_default = $child['clone_default'];
		if ( $parent['clone'] && $parent['clone_default'] && ! $child['clone'] ) {
			$clone_default = true;
		}
		$child['clone_default'] = $clone_default;
		if ( ! $clone_default ) {
			return;
		}
		$child['attributes'] = wp_parse_args( $child['attributes'], [
			'data-default'       => $child['std'],
			'data-clone-default' => 'true',
		] );
	}

	private static function get_child_field( $field, $child_id ) {
		foreach ( $field['fields'] as $child_field ) {
			if ( $child_field['id'] === $child_id ) {
				return $child_field;
			}
			if ( ! isset( $child_field['fields'] ) ) {
				continue;
			}
			$child = self::get_child_field( $child_field, $child_id );
			if ( $child ) {
				return $child;
			}
		}
		return false;
	}

	public static function format_clone_value( $field, $value, $args, $post_id ) {
		$output = '<ul>';

		foreach ( $value as $key => $values ) {
			$name = ' ';
			if ( ! is_numeric( $key ) ) {
				$child_field = self::get_child_field( $field, $key );

				add_filter( 'rwmb_' . $child_field['id'] . '_raw_meta', function () use ($values) {
					return $values;
				}, 10 );

				if ( $child_field ) {
					$name = $child_field['name'] . ': ';

					$values = self::call( 'get_value', $child_field, $args, $post_id );

					if ( ! empty( $values ) ) {
						$values = self::call( 'format_value', $child_field, $values, $args, $post_id );
					}
				}
			}

			$name = '<strong>' . $name . '</strong>';
			$output .= '<li>' . $name;

			if ( is_array( $values ) ) {
				$output .= self::format_clone_value( $field, $values, $args, $post_id );
			} else {
				$output .= self::format_single_value( $field, $values, $args, $post_id );
			}

			$output .= '</li>';
		}

		$output .= '</ul>';

		return $output;
	}
}
