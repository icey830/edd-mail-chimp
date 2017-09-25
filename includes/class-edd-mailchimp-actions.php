<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Actions {

	public function __construct() {
		add_action( 'edd_save_download', array( $this, 'upsert_product' ), 10, 2 );
		add_action( 'edd_customer_post_create', array( $this, 'create_customer' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts') );
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

			edd_debug_log( 'force_list_sync() started for ' . $list_id );

			$list = new EDD_MailChimp_List( $list_id );

			if ( $list->is_connected() && $list->exists() ) {

				edd_debug_log( 'force_list_sync(): list is connected and exists' );

				$response = $list->api->getLastResponse();
				$record = json_decode( $response['body'], true );

				// Find or Create a MailChimp Store for this list and fire up a full sync job
				$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
				$store->sync();

				edd_debug_log( 'force_list_sync(): list store sync queued successfully' );

				global $wpdb;

				$wpdb->update(
					$wpdb->edd_mailchimp_lists,
					array( 'sync_status' => 'pending' ),
					array( 'remote_id' => $list_id ),
					array( '%s'),
					array( '%s' )
				);

				edd_debug_log( 'force_list_sync(): list sync_status set to pending' );

				$redirect_url = add_query_arg( array(
					'settings-updated' => false,
					'tab'              => 'extensions',
					'section'          => 'mailchimp',
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

			edd_debug_log( 'disconnect_list(): attempting to disconnect list ' . $list_id );

			try {
				$list = new EDD_MailChimp_List( $list_id );

				if ( $list->is_connected() ) {

					$list->disconnect();

					edd_debug_log( 'disconnect_list(): list ' . $list_id . ' disconnected' );

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

			edd_debug_log( 'upsert_product(): product ' . $id . ' not pushed to MailChimp because it is not published' );

			return;
		}

		$product = new EDD_MailChimp_Product( $id );
		$lists = EDD_MailChimp_List::connected();

		foreach( $lists as $list ) {

			edd_debug_log( 'upsert_product(): pushing product ' . $id . ' for  ' . $list->remote_id );

			$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
			$store->products->add( $product );

			edd_debug_log( 'upsert_product(): pushed product ' . $id . ' for  ' . $list->remote_id );
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

			edd_debug_log( 'create_customer(): created customer ' . $id . ' for  ' . $list->remote_id );
		}
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

}