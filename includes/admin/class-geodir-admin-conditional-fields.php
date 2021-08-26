<?php
/**
 * Conditional Fields Admin class
 *
 * @package GeoDirectory
 * @since 2.1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GeoDir_Admin_Conditional_Fields', false ) ) {

/**
 * GeoDir_Admin_Conditional_Fields Class.
 */
class GeoDir_Admin_Conditional_Fields {

	/**
	 * Current post type.
	 *
	 * @var string
	 */
	private static $post_type = '';

	/**
	 * Current field.
	 *
	 * @var object
	 */
	private static $field = array();

	public function __construct() {
		add_action( 'geodir_cfa_before_save', array( $this, 'conditional_fields_setting' ), 1, 3 );
		add_filter( 'geodir_cpt_cf_sanatize_custom_field', array( $this, 'save_conditional_fields' ), 11, 2 );
		add_filter( 'geodir_cf_show_conditional_fields_setting', array( $this, 'cf_show_conditional_fields_setting' ), 10, 4 );
		add_filter( 'geodir_cf_show_conditional_fields_setting_desc', array( $this, 'show_extra_description' ), 11, 3 );
		add_filter( 'geodir_cfa_tab_header_icon', array( $this, 'show_conditional_icon' ), 10, 2 );
	}

	/**
	 * Conditional field settings.
	 *
	 * @since 2.1.1.0
	 *
	 * @param string $post_type Current post type.
	 * @param object $field Current field object.
	 * @param array  $data Current field data.
	 * @return array The settings.
	 */
	public function conditional_fields_setting( $post_type, $field, $data ) {
		self::$post_type = $post_type;
		self::$field = $field;

		$hide = false;
		/**
		 * Filter to show/hide conditional field settings.
		 *
		 * @since 2.1.1.0
		 *
		 * @param bool   $hide True to hide.
		 * @param string $post_type Current post type.
		 * @param object $field Current field object.
		 * @param array  $data Current field data.
		 */
		$hide = apply_filters( 'geodir_cf_show_conditional_fields_setting', $hide, $post_type, $field, $data );

		if ( $hide ) {
			return;
		}

		$extra_fields = ! empty( $field->extra_fields ) ? $field->extra_fields : array();
		$conditions = geodir_parse_field_conditions( $extra_fields );

		?>
		<h3 class="geodir-con-fields-hidden" data-setting="conditional_fields_heading"><a href="javascript:void(0)"><span class="geodir-show-cf"><i class="fas fa-plus" aria-hidden="true"></i></span><span class="geodir-hide-cf"><i class="fas fa-minus" aria-hidden="true"></i></span> <?php _e( 'Conditional Fields', 'geodirectory' ); ?></a></h3>
		<div class="gd-advanced-setting" data-setting="conditional_fields" id="geodir_conditional_fields">
			<p data-setting="conditional_fields_desc"><?php _e( 'Setup conditional logic to show/hide this field in the listing form based on specific fields value or conditions.', 'geodirectory' ); ?></p>
			<?php 
			/**
			 * Show conditional field extra message.
			 *
			 * @since 2.1.1.0
			 *
			 * @param string $post_type Current post type.
			 * @param object $field Current field object.
			 * @param array  $data Current field data.
			 */
			do_action( 'geodir_cf_show_conditional_fields_setting_desc', $post_type, $field, $data );
			?>
			<div class="geodir-conditional-template">
				<div class="geodir-conditional-row" data-condition-index="TEMP">
					<?php 
					echo $this->get_field( 'action' );
					echo $this->get_field( 'field' );
					echo $this->get_field( 'condition' );
					echo $this->get_field( 'value' );
					echo $this->get_field( 'remove' );
					?>
				</div>
			</div>
			<div class="geodir-conditional-items">
				<?php if ( ! empty( $conditions ) ) { ?>
					<?php foreach ( $conditions as $k => $condition ) { ?>
						<div class="geodir-conditional-row" data-condition-index="<?php echo esc_attr( $k ); ?>">
							<?php 
							echo $this->get_field( 'action', $condition['action'], $k );
							echo $this->get_field( 'field', $condition['field'], $k );
							echo $this->get_field( 'condition', $condition['condition'], $k );
							echo $this->get_field( 'value', $condition['value'], $k );
							echo $this->get_field( 'remove', '', $k );
							?>
						</div>
					<?php } ?>
				<?php } else { ?>
				<div class="geodir-conditional-row" data-condition-index="0">
					<?php 
					echo $this->get_field( 'action', '', 0 );
					echo $this->get_field( 'field', '', 0 );
					echo $this->get_field( 'condition', '', 0 );
					echo $this->get_field( 'value', '', 0 );
					echo $this->get_field( 'remove', '', 0 );
					?>
				</div>
				<?php } ?>
			</div>
			<p data-setting="conditional_fields_add"><a href="javascript:void(0);" class="button button-secondary geodir-conditional-add"><i class="fas fa-plus-circle"></i> <?php _e( 'Add Rule', 'geodirectory' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Get the conditional field HTML.
	 *
	 * @since 2.1.1.0
	 *
	 * @param string      $key Field key.
	 * @param string      $value Field value.
	 * @param string|int  $index Field index.
	 * @return string Conditional field HTML.
	 */
	public function get_field( $key, $value = '', $index = 'TEMP' ) {
		ob_start();
		switch ( $key ) {
			case 'action':
				?><select class="geodir-conditional-el geodir-conditional-<?php echo $key; ?>" name="conditional_fields[<?php echo $index; ?>][action]" id="conditional_action_<?php echo $index; ?>">
					<option value=""><?php _e( 'ACTION', 'geodirectory' ); ?></option>
					<option value="show" <?php selected( $value == 'show', true ); ?>><?php _e( 'show', 'geodirectory' ); ?></option>
					<option value="hide" <?php selected( $value == 'hide', true ); ?>><?php _e( 'hide', 'geodirectory' ); ?></option>
				</select><?php
				break;
			case 'field':
				$fields = $this->get_fields( self::$post_type );
				?><select class="geodir-conditional-el geodir-conditional-<?php echo $key; ?>" name="conditional_fields[<?php echo $index; ?>][field]" id="conditional_field_<?php echo $index; ?>">
					<option value=""><?php _e( 'FIELD', 'geodirectory' ); ?></option>
					<?php if ( ! empty( $fields ) ) { ?>
						<?php foreach ( $fields as $name => $label ) {
							$skip = ! empty( self::$field ) && ! empty( self::$field->htmlvar_name ) && self::$field->htmlvar_name == $name ? true : false;

							/**
							 * Filter to skip field from conditional fields options.
							 *
							 * @since 2.1.1.0
							 *
							 * @param bool   $skip True to skip.
							 * @param string $name Field name.
							 * @param string $key Field key.
							 */
							if ( apply_filters( 'geodir_conditional_field_option_skip_cf', $skip, $name, $key ) ) {
								continue;
							} ?>
							<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $value == $name, true ); ?>><?php echo $label; ?></option>
						<?php } ?>
					<?php } ?>
				</select><?php
				break;
			case 'condition':
				?><select class="geodir-conditional-el geodir-conditional-<?php echo $key; ?>" name="conditional_fields[<?php echo $index; ?>][condition]" id="conditional_condition_<?php echo $index; ?>">
					<option value=""><?php _e( 'CONDITION', 'geodirectory' ); ?></option>
					<option value="empty" <?php selected( $value == 'empty', true ); ?>><?php _e( 'empty', 'geodirectory' ); ?></option>
					<option value="not empty" <?php selected( $value == 'not empty', true ); ?>><?php _e( 'not empty', 'geodirectory' ); ?></option>
					<option value="equals to" <?php selected( $value == 'equals to', true ); ?>><?php _e( 'equals to', 'geodirectory' ); ?></option>
					<option value="not equals" <?php selected( $value == 'not equals', true ); ?>><?php _e( 'not equals', 'geodirectory' ); ?></option>
					<option value="greater than" <?php selected( $value == 'greater than', true ); ?>><?php _e( 'greater than', 'geodirectory' ); ?></option>
					<option value="less than" <?php selected( $value == 'less than', true ); ?>><?php _e( 'less than', 'geodirectory' ); ?></option>
					<option value="contains" <?php selected( $value == 'contains', true ); ?>><?php _e( 'contains', 'geodirectory' ); ?></option>
				</select><?php
				break;
			case 'value':
				?>
				<input class="geodir-conditional-el geodir-conditional-<?php echo $key; ?>" type="text" name="conditional_fields[<?php echo $index; ?>][value]" id="conditional_value_<?php echo $index; ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'VALUE', 'geodirectory' ); ?>">
				<?php
				break;
			case 'remove':
				?>
				<a class="geodir-conditional-<?php echo $key; ?>" href="javascript:void(0);" title="<?php esc_attr_e( 'Remove', 'geodirectory' ); ?>"><i class="fas fa-minus-circle"></i></a>
				<?php
				break;
		}

		$content = ob_get_clean();

		return '<div class="geodir-conditional-col" data-conditional="' . $key . '">' . trim( $content ) . '</div>';
	}

	/**
	 * Get conditional fields.
	 *
	 * @since 2.1.1.0
	 *
	 * @param string $post_type Current post type.
	 * @return array Conditional fields.
	 */
	public function get_fields( $post_type ) {
		$custom_fields = geodir_post_custom_fields( '', 'all', $post_type );

		$fields = array();
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $key => $field ) {
				$skip = false;

				/**
				 * Filter to skip field from conditional fields.
				 *
				 * @since 2.1.1.0
				 *
				 * @param bool  $skip True to skip.
				 * @param array $name Field array.
				 */
				if ( apply_filters( 'geodir_conditional_field_skip_cf', $skip, $field ) ) {
					continue;
				}

				$field = stripslashes_deep( $field );
				$fields[ $field['name'] ] = ! empty( $field['admin_title'] ) ? __( $field['admin_title'], 'geodirectory' ) : __( $field['frontend_title'], 'geodirectory' );

				if ( $field['name'] == 'post_category' ) {
					$fields[ 'default_category' ] = __( 'Default Category', 'geodirectory' );
				} else if ( $field['name'] == 'address' ) {
					unset( $fields[ $field['name'] ] );

					if ( $address_fields = $this->get_address_fields( $post_type ) ) {
						$fields = $fields + $address_fields;
					}
				}
			}
		}

		/**
		 * Filter the conditional fields.
		 *
		 * @since 2.1.1.0
		 *
		 * @param array  $fields Fields array.
		 * @param string $post_type Current post type.
		 */
		return apply_filters( 'geodir_conditional_fields_options', $fields, $post_type );
	}

	/**
	 * Get address fields.
	 *
	 * @since 2.1.1.0
	 *
	 * @param string $post_type Current post type.
	 * @return array Address fields.
	 */
	public function get_address_fields( $post_type ) {
		$address_fields = geodir_post_meta_address_fields( $post_type );

		$fields = array();
		if ( ! empty( $address_fields ) ) {
			foreach ( $address_fields as $key => $_field ) {
				if ( $key == 'map_directions' ) {
					continue;
				}
				$fields[ $key ] = $_field['frontend_title'];
			}
		}

		/**
		 * Filter the conditional address fields.
		 *
		 * @since 2.1.1.0
		 *
		 * @param array  $fields Address fields array.
		 * @param string $post_type Current post type.
		 */
		return apply_filters( 'geodir_conditional_fields_address_options', $fields, $post_type );
	}

	/**
	 * Save conditional fields.
	 *
	 * @since 2.1.1.0
	 *
	 * @param object $field_data Field data.
	 * @param array  $request Request data.
	 */
	public function save_conditional_fields( $field_data, $request ) {
		if ( ! empty( $request['conditional_fields'] ) ) {
			$fields = $request['conditional_fields'];

			if ( ! empty( $fields['TEMP'] ) ) {
				unset( $fields['TEMP'] );
			}

			$conditions = array();

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $_field ) {
					if ( ! empty( $_field['action'] ) && ! empty( $_field['field'] ) && ! empty( $_field['condition'] ) ) {
						$conditions[] = array(
							'action' => sanitize_text_field( $_field['action'] ),
							'field' => sanitize_text_field( $_field['field'] ),
							'condition' => sanitize_text_field( $_field['condition'] ),
							'value' => ( isset( $_field['value'] ) ? sanitize_text_field( stripslashes( $_field['value'] ) ) : '' ),
						);
					}
				}
			}

			$extra_fields = ! empty( $field_data->extra_fields ) ? $field_data->extra_fields : '';
			$is_serialized = is_serialized( $extra_fields );
			$is_changed = false;

			if ( ! empty( $conditions ) ) {
				$extra_fields = maybe_unserialize( $extra_fields );

				if ( ! is_array( $extra_fields ) ) {
					if ( $extra_fields != '' ) {
						$extra_fields = array( $extra_fields );
					} else {
						$extra_fields = array();
					}
				}

				if ( empty( $extra_fields ) ) {
					$is_serialized = true;
				}

				$is_changed = true;
				$extra_fields['conditions'] = $conditions;
			} else {
				if ( is_array( $extra_fields ) && isset( $extra_fields['conditions'] ) ) {
					unset( $extra_fields['conditions'] );
				}
			}

			if ( $is_changed ) {
				if ( $is_serialized ) {
					$extra_fields = ! empty( $extra_fields ) ? maybe_serialize( $extra_fields ) : '';
				}

				$field_data->extra_fields = $extra_fields;
			}
		}

		return $field_data;
	}

	/**
	 * Skip conditional for event dates field.
	 *
	 * @since 2.1.1.1
	 *
	 * @param bool   $hide True to hide.
	 * @param string $post_type Current post type.
	 * @param object $field Current field object.
	 * @param array  $data Current field data.
	 * @return bool True to hide, False to show.
	 */
	public function cf_show_conditional_fields_setting( $hide, $post_type, $field, $data ) {
		if ( ! empty( $field->htmlvar_name ) && $field->htmlvar_name == 'address' ) {
			$hide = true;
		}

		return $hide;
	}

	/**
	 * Show conditional field extra message.
	 *
	 * @since 2.1.1.0
	 *
	 * @param string $post_type Current post type.
	 * @param object $field Current field object.
	 * @param array  $data Current field data.
	 * @return mixed
	 */
	public function show_extra_description( $post_type, $field, $data ) {
		if ( ! empty( $field->htmlvar_name ) && in_array( $field->htmlvar_name, array( 'post_title', 'post_content', 'post_category', 'address' ) ) ) {
			?><p data-setting="conditional_fields_extra_desc"><?php _e( 'Please be aware that the form will not submit if this field is hidden.', 'geodirectory' ); ?></p><?php
		}
	}

	/**
	 * Show conditional field icon in tab header.
	 *
	 * @since 2.1.1.1
	 *
	 * @param object $field The field object settings.
	 * @param array  $cf The customs field default settings.
	 * @return mixed
	 */
	public function show_conditional_icon( $field, $cf ) {
		$conditional_attrs = geodir_conditional_field_attrs( $field );
		$conditional_icon = geodir_conditional_field_icon( $conditional_attrs, $field );

		if ( $conditional_icon ) {
			echo ' <span class="dd-extra-icon">' . $conditional_icon . '</span> ';
		}
	}
} }

return new GeoDir_Admin_Conditional_Fields();
