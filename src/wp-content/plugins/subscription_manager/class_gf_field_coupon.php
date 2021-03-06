<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Coupon extends GF_Field {

	public $type = 'coupon';

	/**
	 * Return the field title, for use in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return __( 'Coupon', 'gravityformscoupons' );
	}

	/**
	 * Assign the Coupon button to the Pricing Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'pricing_fields',
			'text'  => $this->get_form_editor_field_title()
		);
	}

	/**
	 * Return the settings which should be available on the field in the form editor.
	 *
	 * @return array
	 */
	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'label_setting',
			'admin_label_setting',
			'css_class_setting',
			'description_setting',
			'placeholder_setting',
			'visibility_setting',
			'rules_setting',
			'error_message_setting',
		);
	}

	/**
	 * Enable support for using the field with conditional logic.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Returns the input ID to be assigned to the field label for attribute.
	 *
	 * @param array $form The form object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {
		return sprintf( 'gf_coupon_code_%s', $form['id'] );
	}

	/**
	 * Returns the fields inner markup.
	 *
	 * @param array $form The form object currently being processed.
	 * @param string $value The field value from the $_POST or the resumed incomplete submission. Not currently used.
	 * @param null $entry
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$id              = (int) $this->id;

		if ( $is_entry_detail ) {
			$input = "<input type='hidden' id='input_{$id}' name='input_{$id}' value='{$value}' />";

			return $input . '<br/>' . esc_html__( 'Coupon fields are not editable', 'gravityformscoupons' );
		}

		$disabled_text         = $this->is_form_editor() ? 'disabled="disabled"' : '';
		$logic_event           = $this->get_conditional_logic_event( 'change' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$coupons_detail        = '';
		$coupon_codes          = '';

		$input = "<div class='ginput_container' id='gf_coupons_container_{$form_id}'>" .
		         "<input id='gf_coupon_code_{$form_id}' class='gf_coupon_code' onkeyup='DisableApplyButton({$form_id});' onchange='DisableApplyButton({$form_id});' onpaste='setTimeout(function(){DisableApplyButton({$form_id});}, 50);' type='text'  {$disabled_text} {$placeholder_attribute} " . $this->get_tabindex() . '/>' .
		         "<input type='button' disabled='disabled' onclick='ApplyCouponCode({$form_id});' value='" . esc_attr__( 'Apply', 'gravityformscoupons' ) . "' id='gf_coupon_button' class='button' {$disabled_text} " . $this->get_tabindex() . '/> ' .
		         "<img style='display:none;' id='gf_coupon_spinner' src='".plugins_url( 'img/spinner.gif', __FILE__ )."' alt='" . esc_attr__( 'please wait', 'gravityformscoupons' ) . "'/>" .
		         "<div id='gf_coupon_info'></div>" .
		         "<input type='hidden' id='gf_coupon_codes_{$form_id}' name='input_{$id}' value='' {$logic_event} />" .
		         "<input type='hidden' id='gf_total_no_discount_{$form_id}'/>" .
		         "<input type='hidden' id='gf_coupons_{$form_id}' name='gf_coupons_{$form_id}' value='" . esc_attr( $coupons_detail ) . "' />" .
		         "</div>";

		return $input;
	}

	/**
	 * Re-validate the coupon codes, ensuring the coupons usage count or status hasn't changed since the coupon was first applied.
	 *
	 * @param string $value The field value from the $_POST
	 * @param array $form The form object currently being processed.
	 */
	public function validate( $value, $form ) {

	}

	/**
	 * Include the gform_form_editor_can_field_be_added script on the form editor page and set the default label for new fields.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$script = sprintf( "function SetDefaultValues_%s(field) {field.label = '%s';}", $this->type, $this->get_form_editor_field_title() ) . PHP_EOL;

		$script .= "
		gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
			if (type == 'coupon') {
				if (GetFieldsByType(['product']).length <= 0) {
					alert(" . json_encode( esc_html__( 'You must add a Product field to the form', 'gravityformscoupons' ) ) . ");
					return false;
				} else if (GetFieldsByType(['total']).length  <= 0) {
					alert(" . json_encode( esc_html__( 'You must add a Total field to the form', 'gravityformscoupons' ) ) . ");
					return false;
				} else if (GetFieldsByType(['coupon']).length) {
					alert(" . json_encode( esc_html__( 'Only one Coupon field can be added to the form', 'gravityformscoupons' ) ) . ");
					return false;
				}
			}
			return canFieldBeAdded;
		});";

		return $script;
	}

	/**
	 * Return the formatted entry value.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $input_id The field or input ID.
	 * @param bool|false $use_text
	 * @param bool|false $is_csv
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );

		if ( ! empty( $value ) ) {
			$form         = GFAPI::get_form( $entry['form_id'] );
			$product_info = GFCommon::get_product_fields( $form, $entry );
			$coupon_codes = array_map( 'trim', explode( ',', $value ) );
			$coupons      = array();

			foreach ( $coupon_codes as $code ) {
				$price     = GFCommon::to_money( $product_info['products'][ $code ]['price'], rgar( $entry, 'currency' ) );
				$coupons[] = sprintf( '%s (%s: %s)', $product_info['products'][ $code ]['name'], $code, $price );
			}

			$value = implode( ', ', $coupons );
		}

		return $value;
	}

}

GF_Fields::register( new GF_Field_Coupon() );