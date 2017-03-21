<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Actions {

	public function __construct() {
		// add_action( 'edd_download_post_create', array( $this, 'create_download' ) );
		// add_action( 'edd_customer_post_create', array( $this, 'create_customer' ) );
		// add_action( 'edd_cart_contents_loaded_from_session', array( $this, 'set_cart' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts') );
		add_action( 'edd_complete_download_purchase', array( $this, 'hook_signup' ), 10, 3 );
	}

	/**
	 * Backwards compatibility to add an action for signups
	 * through the edd_complete_download_purchase action
	 */
	public function hook_signup() {
		add_action( 'edd_complete_purchase', array( $this, 'completed_purchase_signup' ) );
	}

	/**
	 * Load required admin scripts and styles here.
	 * @param  string $hook Current admin page
	 * @return void
	 */
	public function load_admin_scripts( $hook ) {

		if ( $hook !== 'download_page_edd-settings' ) {
			return;
		}

		wp_register_style('edd-mailchimp', EDD_MAILCHIMP_URL . 'assets/dist/css/main.css');
		wp_enqueue_style('edd-mailchimp');
	}

	/**
	 * Check if a customer needs to be subscribed on completed purchase of specific products
	 */
	public function completed_purchase_signup( $payment_id = 0 ) {
		$payment = new EDD_Payment( $payment_id );

		$user = array(
			'first_name' => $payment->first_name,
			'last_name'  => $payment->last_name,
			'email'      => $payment->email,
		);

		$default_list = EDD_MailChimp_List::default();

		if ( $default_list ) {
			$default_list->subscribe( $user_info );
		}


		foreach ( $payment->cart_details as $line ) {
			$download = new EDD_MailChimp_Download( (int) $line['id'] );
			$preferences = $download->subscription_preferences();

			foreach( $preferences as $preference ) {
				$list = new EDD_MailChimp_List( $preference['remote_id'] );
				$options = array( 'interests' => $preference['interests'] );
				$list->subscribe( $user, $options );
			}
		}
	}

}
