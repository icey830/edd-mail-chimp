<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Checkout {

	public function __construct() {
		add_action( 'edd_purchase_form_before_submit', array( $this, 'checkout_fields' ), 100 );
		add_action( 'edd_checkout_before_gateway', array( $this, 'checkout_signup' ), 10, 3 );
		add_action( 'edd_complete_purchase', array( $this, 'completed_purchase_signup' ), 10, 3 );
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
				<input name="edd_mailchimp_signup" id="edd_mailchimp_signup" type="checkbox" <?php if ($checked) { echo 'checked="checked"'; } ?>/>
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

		if ( empty( $posted['edd_mailchimp_signup'] ) ) {

			edd_debug_log( 'checkout_signup() not processed because edd_mailchimp_signup was not present' );
			return;
		}

		$default_list = EDD_MailChimp_List::get_default();

		edd_debug_log( 'checkout_signup(): default list is ' . $default_list->remote_id );

		if ( $default_list ) {
			$subscribed = $default_list->subscribe( $user_info );
			edd_debug_log( 'checkout_signup() customer subscription result: ' . var_export( $subscribed, true ) );
		}

	}

	/**
	 * Check if a customer needs to be subscribed on completed purchase of specific products
	 */
	public function completed_purchase_signup( $payment_id, $payment, $customer ) {

		edd_debug_log( 'completed_purchase_signup() started for payment ' . $payment_id );

		$user = array(
			'first_name' => $payment->first_name,
			'last_name'  => $payment->last_name,
			'email'      => $payment->email,
		);

		$default_list = EDD_MailChimp_List::get_default();

		edd_debug_log( 'completed_purchase_signup() default list found is ' . $default_list->remote_id );

		if ( $default_list ) {
			$result = $default_list->subscribe( $user );
		}


		foreach ( $payment->cart_details as $line ) {

			edd_debug_log( 'completed_purchase_signup() processing Download ' . $line['id'] );

			$download = new EDD_MailChimp_Download( (int) $line['id'] );
			$preferences = $download->subscription_preferences();

			$double_opt_in = get_post_meta( $line['id'], 'edd_mailchimp_double_opt_in', true );

			foreach( $preferences as $preference ) {

				$list = new EDD_MailChimp_List( $preference['remote_id'] );
				$options = array( 'interests' => $preference['interests'] );
				$is_double_opt_in = empty( $double_opt_in );
				$options['double_opt_in'] = $is_double_opt_in;

				edd_debug_log( 'completed_purchase_signup() about to subscribe customer. User data: ' . print_r( $user, true ) . 'Options data: ' . print_r( $options, true ) );

				$subscribed = $list->subscribe( $user, $options );

				edd_debug_log( 'completed_purchase_signup() customer subscription result: ' . var_export( $subscribed, true ) );
			
				if( $subscribed ) {

					edd_debug_log( 'completed_purchase_signup() customer subscription response from MailChimp: ' . var_export( $list->api->getLastResponse(), true ) );
				
				} else {

					edd_debug_log( 'completed_purchase_signup() MailChimp request:' . var_export( $list->api->getLastRequest(), true ) );
					edd_debug_log( 'completed_purchase_signup() MailChimp error:' . var_export( $list->api->getLastError(), true ) );

				}

			}
		}

		edd_debug_log( 'completed_purchase_signup() completed for payment ' . $payment_id );

	}

	/**
	* Determines if the checkout signup option should be displayed
	*/
	private static function _show_checkout_signup() {
		$show = edd_get_option('eddmc_show_checkout_signup');
		return ! empty( $show );
	}

}