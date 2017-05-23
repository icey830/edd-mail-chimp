<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Actions {

	public function __construct() {
		add_action( 'edd_save_download', array( $this, 'upsert_product' ), 10, 2 );
		add_action( 'edd_customer_post_create', array( $this, 'create_customer' ), 10, 2 );
		// add_action( 'edd_cart_contents_loaded_from_session', array( $this, 'set_cart' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts') );
		add_action( 'edd_complete_download_purchase', array( $this, 'hook_signup' ), 10, 3 );
		add_action( 'edd_mailchimp_force_list_sync', array( $this, 'force_list_sync') );
		add_action( 'edd_mailchimp_disconnect_list', array( $this, 'disconnect_list') );
	}

	/**
	 * Fire up a full list sync when requested by the user from the settings page.
	 *
	 * @param  $_GET $request Request parameters
	 * @return void
	 */
	public function force_list_sync( $request ) {
		$list_id = sanitize_key( $request['mailchimp_list_remote_id'] );

		if ( $list_id ) {
			$list = new EDD_MailChimp_List( $list_id );

			if ( $list->is_connected() && $list->exists() ) {
				$response = $list->api->getLastResponse();
				$record = json_decode( $response['body'], true );

				// Find or Create a MailChimp Store for this list and fire up a full sync job
				$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
				$store->sync();

				global $wpdb;

				$wpdb->update(
					$wpdb->edd_mailchimp_lists,
					array( 'sync_status' => 'pending' ),
					array( 'remote_id' => $list_id ),
					array( '%s'),
					array( '%s' )
				);

				$redirect_url = add_query_arg( array(
					'settings-updated' => false,
					'tab'              => 'extensions',
					'edd_mailchimp_list_queued' => 1,
				) );

				// Remove the section from the tabs so we always end up at the main section
				$redirect_url = remove_query_arg( array('section', 'edd-action', 'mailchimp_list_remote_id'), $redirect_url );

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Disconnect a list from this EDD install.
	 *
	 * @param  [type] $request [description]
	 * @return [type]          [description]
	 */
	public function disconnect_list( $request ) {
		$list_id = sanitize_key( $request['mailchimp_list_remote_id'] );

		if ( $list_id ) {
			try {
				$list = new EDD_MailChimp_List( $list_id );

				if ( $list->is_connected() ) {

					$list->disconnect();

					$redirect_url = add_query_arg( array(
						'settings-updated' => false,
						'tab'              => 'extensions',
						'edd_mailchimp_list_disconnected' => 1,
					) );

					// Remove the section from the tabs so we always end up at the main section
					$redirect_url = remove_query_arg( array('section', 'edd-action', 'mailchimp_list_remote_id'), $redirect_url );

					wp_safe_redirect( $redirect_url );
					exit;
				}
			} catch (Exception $e) {
				return;
			}
		}
	}


	/**
	 * Create product in MailChimp Store for each connected list.
	 *
	 * @param int     $id Download ID.
	 * @param WP_Post $post    Download object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 * @return void
	 */
	public function upsert_product( $id, $post ) {
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$product = new EDD_MailChimp_Product( $id );
		$lists = EDD_MailChimp_List::connected();

		foreach( $lists as $list ) {
			$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
			$store->products->add( $product );
		}
	}


	/**
	 * Create customer in MailChimp Store for each connected list.
	 *
	 * @param int   $id      If created successfully, the customer ID.  Defaults to false.
	 * @param array $args    Contains customer information such as payment ID, name, and email.
	 * @return void
	 */
	public function create_customer( $id, $args ) {
		if ( ! $id ) {
			return false;
		}

		$customer = new EDD_MailChimp_Customer( $id );
		$lists = EDD_MailChimp_List::connected();

		foreach( $lists as $list ) {
			$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
			$store->customers->add( $customer );
		}
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

		$default_list = EDD_MailChimp_List::get_default();

		if ( $default_list ) {
			$result = $default_list->subscribe( $user );
		}


		foreach ( $payment->cart_details as $line ) {
			$download = new EDD_MailChimp_Download( (int) $line['id'] );
			$preferences = $download->subscription_preferences();

			$double_opt_in = get_post_meta( $post->ID, 'edd_mailchimp_double_opt_in', true );

			foreach( $preferences as $preference ) {
				$list = new EDD_MailChimp_List( $preference['remote_id'] );
				$options = array( 'interests' => $preference['interests'] );
				$is_double_opt_in = empty( $double_opt_in );
				$options['double_opt_in'] = $is_double_opt_in;
				$list->subscribe( $user, $options );
			}
		}
	}

}
