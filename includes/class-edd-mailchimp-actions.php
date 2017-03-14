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

		$user_info = edd_get_payment_meta_user_info( $payment_id );
		$downloads = edd_get_payment_meta_cart_details( $payment_id, true );

		$entries = array();

		foreach ( $downloads as $download ) {
			$download_lists = get_post_meta( $download['id'], '_edd_mailchimp', true );

			if ( is_array( $download_lists ) ) {
				$entries = array_merge( $download_lists, $entries );
			}
		}

		if( empty( $entries ) ) {
			return;
		}

		$entries = array_unique( $entries );

		// Convert, combine and break out any interests into lists and interest array
		$lists = array();

		foreach( $entries as $list ) {

			if ( strpos( $list, '|' ) != false ) {
				$parts          = explode( '|', $list );
				$list_id        = $parts[0];
				$interest_id    = $parts[1];
			} else {
				$list_id = $list;
			}

			if ( ! isset( $lists[$list_id] ) ) {
				$lists[$list_id] = array();
			}

			if ( isset( $interest_id ) ) {
				$lists[$list_id][$interest_id] = true;
			}
		}

		foreach( $lists as $list => $interests ) {
			// $list = new EDD_Mailchimp_List;
			// $list->subscribe( ... );
			$this->subscribe_email( $user_info, $list, false, array('interests' => $interests) );
		}
	}


	/**
	 * Subscribe an email to a list
	 *
	 * @param  array   $user_info       Customer data containing the user ID, email, first name, and last name
	 * @param  boolean $list_id         MailChimp List ID to subscribe the user to
	 * @param  boolean $opt_in_override Should we force double opt-in for this subscription?
	 * @param  boolean $options         Additional subscription options
	 * @return boolean                  Was the customer subscribed?
	 */
	public function subscribe_email( $user_info = array(), $list_id = false, $opt_in_override = false, $options = array() ) {
		return;
	}

}
