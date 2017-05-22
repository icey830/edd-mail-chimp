<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Checkout {

	public function __construct() {
		add_action( 'edd_purchase_form_before_submit', array( $this, 'checkout_fields' ), 100 );
		add_action( 'edd_checkout_before_gateway', array( $this, 'checkout_signup' ), 10, 3 );
	}

	/**
	* Output the signup checkbox on the checkout screen, if enabled
	*/
	public function checkout_fields() {
		if( ! self::_show_checkout_signup() ) {
			return;
		}

		$label = edd_get_option('eddmc_label');
		$checked = edd_get_option('eddmc_checkout_signup_default_value', false);

		if( ! empty( $label ) ) {
			$checkout_label = trim( $label );
		} else {
			$checkout_label = __( 'Signup for the newsletter', 'eddmc' );
		}

		ob_start(); ?>
		<fieldset id="edd_mailchimp">
			<p>
				<input name="edd_mailchimp_signup" id="edd_mailchimp_signup" type="checkbox" <?php if ($checked) { echo 'checked="checked"' } ?>/>
				<label for="edd_mailchimp_signup"><?php echo $checkout_label; ?></label>
			</p>
		</fieldset>
		<?php
		echo ob_get_clean();
	}


	/**
	* Check if a customer needs to be subscribed at checkout
	*/
	public function checkout_signup( $posted, $user_info, $valid_data ) {
		if ( isset( $posted['edd_mailchimp_signup'] ) ) {
			$default_list = EDD_MailChimp_List::get_default();

			if ( $default_list ) {
				$default_list->subscribe( $user_info );
			}
		}
	}

	/**
	* Determines if the checkout signup option should be displayed
	*/
	private static function _show_checkout_signup() {
		$show = edd_get_option('eddmc_show_checkout_signup');
		return ! empty( $show );
	}

}