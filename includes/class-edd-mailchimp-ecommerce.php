<?php

/**
 * EDD MailChimp Ecommerce class
 *
 * @copyright   Copyright (c) 2014-2017, Easy Digital Downloads
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
*/
class EDD_MailChimp_Ecommerce {

	public function __construct() {
		add_action( 'init', array( $this, 'set_ecommerce_session' ) );
		add_action( 'edd_insert_payment', array( $this, 'set_ecommerce_flags' ), 10, 2 );
		add_action( 'edd_complete_purchase', array( $this, 'add_order' ), 20, 3 );
		add_action( 'edd_update_payment_status', array( $this, 'remove_order' ), 10, 3 );
	}

	/**
	 * Enables MailChimp's Ecommerce tracking from the parameters
	 * added to a newsletter campaign
	 *
	 * @uses campaign UID
	 * @uses member email's UID
	 */
	public function set_ecommerce_session() {
		$mc_cid = isset( $_GET['mc_cid'] ) ? $_GET['mc_cid'] : '';
		$mc_eid = isset( $_GET['mc_eid'] ) ? $_GET['mc_eid'] : '';

		if ( ! empty( $mc_cid ) && ! empty( $mc_eid ) ) {
			EDD()->session->set( self::_get_session_id( 'campaign' ), filter_var( $mc_cid , FILTER_SANITIZE_STRING ) );
			EDD()->session->set( self::_get_session_id( 'email' ),    filter_var( $mc_eid , FILTER_SANITIZE_STRING ) );
		}
	}

	/**
	 * Sets flags in post meta so that we can detect them when completing a purchase via IPN
	 *
	 * @param  integer $payment_id
	 * @param  array $payment_data
	 * @return bool
	 */
	public function set_ecommerce_flags( $payment_id = 0, $payment_data = array() ) {

		$record_test_mode = edd_get_option('eddmc_record_test_mode');

		// Don't record details if we're in test mode and user prefers not to record.
		if ( edd_is_test_mode() && $record_test_mode === false ) {
			return;
		}

		$mc_cid_key  = self::_get_session_id( 'campaign' );
		$mc_eid_key  = self::_get_session_id( 'email' );

		$campaign_id = EDD()->session->get( $mc_cid_key );
		$email_id    = EDD()->session->get( $mc_eid_key );

		if ( ! empty( $campaign_id ) && ! empty( $email_id ) ) {

			add_post_meta( $payment_id, '_edd_mc_campaign_id', $campaign_id, true );
			add_post_meta( $payment_id, '_edd_mc_email_id', $email_id, true );

			EDD()->session->set( $mc_cid_key, NULL );
			EDD()->session->set( $mc_eid_key, NULL );

		}
	}

	/**
	 * Send purchase details to MailChimp's Ecommerce360 extension.
	 *
	 * @param  integer $payment_id   Payment ID number
	 * @param  object  $payment      EDD_Payment object
	 * @param  object  $customer     EDD_Customer object
	 * @return bool
	 */
	public function add_order( $payment_id = 0, $payment, $customer  ) {

		$record_test_mode = edd_get_option('eddmc_record_test_mode');

		// Don't record details if we're in test mode and user prefers not to record.
		if ( edd_is_test_mode() && $record_test_mode === false ) {
			edd_debug_log( 'add_order(): order ' . $payment_id . ' not added because test mode is enabled or recording of orders in test mode is disabled' );
			return;
		}

		$order = new EDD_MailChimp_Order( (int) $payment_id );

		// Set Ecommerce360 variables if they exist
		$campaign_id = get_post_meta( $payment_id, '_edd_mc_campaign_id', true );

		edd_debug_log( 'add_order(): campaign ID for payment ' . $payment_id . ' is: ' . $campaign_id );

		/**
		 * In July 2017, the Easy Digital Downloads team decided that the email address
		 * entered by the customer during checkout should be the authorative factor in
		 * determining which email address the EDD order should be associated with.
		 *
		 * This email address _could_ be different than the one associated with the unique
		 * email id that may have been stored if the user was referred to this purchase
		 * by a MailChimp campaign.
		 *
		 * If you wish to rather associate the EDD Order with the email address that
		 * was sent the referring campaign, you should hook a callback function to the
		 * following WordPress filter provided by this plugin:
		 *
		 *   edd.mailchimp.order
		 *
		 * You can then fetch the unique MailChimp email ID from the referring campaign
		 * by checking the post meta entry for the EDD Payment ID.
		 *
		 *   $email_id = get_post_meta( $payment_id, '_edd_mc_email_id', true );
		 *
		 * If it exists, you would then need to determine which list that the referring
		 * campaign was sent from. You can do so with a request similar to this:
		 *
		 *   $campaign_id = get_post_meta( $payment_id, '_edd_mc_campaign_id', true );
		 *
		 * Make sure to first check that the $campaign_id also exists, and then dispatch
		 * an authenticated GET request with the MailChimp API library.
		 *
		 *   GET https://us5.api.mailchimp.com/3.0/campaigns/{$campaign_id}
		 *
		 * The corresponding MailChimp List ID will exist in the response from that request.
		 *
		 *   $list_id = $response->recipients->list_id;
		 *
		 * Now that you have the MailChimp List ID, you can retrieve the MailChimp member's
		 * email address by dispatching a GET request to the list's endpoint with a filtering
		 * query parameter:
		 *
		 *   GET https://us5.api.mailchimp.com/3.0/lists/{$list_id}/members?unique_email_id={$email_id}
		 *
		 * If the member's email address was successfully found, you now have the email address to
		 * associate the ecommerce order with.
		 *
		 *   if ( $response->total_items === 1 ) {
		 *       $email = $response->members[0]->email_address;
		 *   }
		 *
		 * Set that as your order's email address, and you are good to go.
		 *
		 *   $order['customer']['email_address'] = $email;
		 *
		 * FIN
		 * - Dave Kiss
		 */

		// Send/update order in MailChimp
		try {
			$default_list = EDD_MailChimp_List::get_default();

			if ( $default_list ) {

				edd_debug_log( 'add_order(): default list found: ' . $default_list->remote_id );

				if ( ! empty( $campaign_id ) && $default_list->recipient_of_campaign( $campaign_id ) ) {
					$order->campaign_id = $campaign_id;
				}

				$store = EDD_MailChimp_Store::find_or_create( $default_list );
				$store->orders->add( $order );

				if( ! $store->api->success() ) {
					edd_debug_log( 'add_order() MailChimp request:' . var_export( $store->api->getLastRequest(), true ) );
					edd_debug_log( 'add_order() MailChimp error:' . var_export( $store->api->getLastError(), true ) );
				}

			}

			unset( $order->campaign_id );

			foreach( $order->lines as $line_item ) {
				$download = new EDD_MailChimp_Download( (int) $line_item['product_id'] );
				$preferences = $download->subscription_preferences();

				if ( ! empty( $preferences ) ) {
					foreach( $preferences as $list ) {
						$list = new EDD_MailChimp_List( $list['remote_id'] );

						if ( ! empty( $campaign_id ) && $list->recipient_of_campaign( $campaign_id ) ) {
							$order->campaign_id = $campaign_id;
						}

						$store = EDD_MailChimp_Store::find_or_create( $list );
						$store->orders->add( $order );

						if( ! $store->api->success() ) {

							edd_debug_log( 'add_order() MailChimp request:' . var_export( $store->api->getLastRequest(), true ) );
							edd_debug_log( 'add_order() MailChimp error:' . var_export( $store->api->getLastError(), true ) );

						} else {

							edd_debug_log( 'add_order() payment ' . $payment_id . ' added successfully' );

						}

					}
				}
			}

			edd_insert_payment_note( $payment_id, __( 'Order details have been updated in MailChimp successfully', 'eddmc' ) );

		} catch ( Exception $e ) {

			edd_debug_log( 'add_order(): Exception encountered for payment ' . $payment_id . '. Exception message: ' . $e->getMessage() );
			edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $e->getMessage() );
			return false;

		}

		return true;
	}

	/**
	 * Remove an order from MailChimp if the payment was refunded
	 *
	 * @return bool
	 */
	public function remove_order( $payment_id, $new_status, $old_status) {

		if ( 'publish' != $old_status && 'revoked' != $old_status ) {
			return;
		}

		if ( 'refunded' != $new_status ) {
			return;
		}

		$order = new EDD_MailChimp_Order( $payment_id );

		edd_debug_log( 'remove_order() processing for ' . $payment_id );

		try {
			$default_list = EDD_MailChimp_List::get_default();

			if ( $default_list ) {

				$store = EDD_MailChimp_Store::find_or_create( $default_list );
				$store->orders->remove( $order );

				if( ! $store->api->success() ) {

					edd_debug_log( 'remove_order() MailChimp request:' . var_export( $store->api->getLastRequest(), true ) );
					edd_debug_log( 'remove_order() MailChimp error:' . var_export( $store->api->getLastError(), true ) );

				} else {

					edd_debug_log( 'remove_order() payment ' . $payment_id . ' removed successfully' );

				}

			}

			foreach( $order->lines as $line_item ) {

				$download = new EDD_MailChimp_Download( (int) $line_item['product_id'] );
				$preferences = $download->subscription_preferences();

				if ( ! empty( $preferences ) ) {

					foreach( $preferences as $list ) {

						$list = new EDD_MailChimp_List( $list['remote_id'] );
						$store = EDD_MailChimp_Store::find_or_create( $list );
						$store->orders->remove( $order );

						if( ! $store->api->success() ) {

							edd_debug_log( 'remove_order() MailChimp request:' . var_export( $store->api->getLastRequest(), true ) );
							edd_debug_log( 'remove_order() MailChimp error:' . var_export( $store->api->getLastError(), true ) );

						} else {

							edd_debug_log( 'remove_order() payment ' . $payment_id . ' removed from default list successfully' );

						}

					}
				}
			}

			edd_insert_payment_note( $payment_id, __( 'Order details have been removed from MailChimp.', 'eddmc' ) );
			return true;

		} catch (Exception $e) {
			edd_debug_log( 'remove_order(): Exception encountered while removing ' . $payment_id . ' from list' );
			edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $e->getMessage() );
			return false;
		}

	}

	/**
	 * Returns the unique Ecommerce session keys for this EDD installation.
	 *
	 * @param  string $type campaign | email
	 * @return string Key identifier for stored sessions
	 */
	protected static function _get_session_id( $type = 'campaign' ) {
		$prefix = substr( $type, 0, 1);
		$store = md5( home_url() );
		return sprintf( 'edd_mc360_%1$s_%2$sid', substr( $store, 0, 10 ), $prefix );
	}

}
