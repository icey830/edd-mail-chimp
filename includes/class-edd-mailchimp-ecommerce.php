<?php

/**
 * EDD MailChimp Ecommerce class
 *
 * @copyright   Copyright (c) 2014-2017, Dave Kiss
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
*/
class EDD_MailChimp_Ecommerce {

	public function __construct() {
		add_action( 'init', array( $this, 'set_ecommerce_session' ) );
		add_action( 'edd_insert_payment', array( $this, 'set_ecommerce_flags' ), 10, 2 );
		add_action( 'edd_complete_purchase', array( $this, 'add_order' ) );
		add_action( 'edd_update_payment_status', array( $this, 'remove_order' ), 10, 3 );
	}

	/**
	 * Sets flags in post meta so that we can detect them when completing a purchase via IPN
	 *
	 * @param  integer $payment_id
	 * @param  array $payment_data
	 * @return bool
	 */
	public function set_ecommerce_flags( $payment_id = 0, $payment_data = array() ) {

		// Don't record details if we're in test mode
		if ( edd_is_test_mode() ) {
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
	 * @param  integer $payment_id    [description]
	 * @return bool
	 */
	public function add_order( $payment_id = 0 ) {

		// Don't record details if we're in test mode
		if ( edd_is_test_mode() ) {
			return;
		}

		$order = new EDD_MailChimp_Order( $payment_id );

		// Set Ecommerce360 variables if they exist
		$campaign_id = get_post_meta( $payment_id, '_edd_mc_campaign_id', true );
		$email_id    = get_post_meta( $payment_id, '_edd_mc_email_id', true );

		// TODO: Fetch unique email address for customer using the ecommerce tracking email id?

		if ( ! empty( $campaign_id ) ) {
			$order['campaign_id'] = $campaign_id;
		}

		// Send/update order in MailChimp
		try {
			$default_list = EDD_MailChimp_List::default();

			if ( $default_list ) {
				$store = EDD_MailChimp_Store::find_or_create($default_list);
				$store->orders->add( $order );
			}

			foreach( $order->lines as $line_item ) {
				$download = new EDD_MailChimp_Download( (int) $line_item['product_id'] );
				$preferences = $download->subscription_preferences();

				if ( ! empty( $preferences ) ) {
					foreach( $preferences as $list ) {
						$list = new EDD_MailChimp_List( $list['remote_id'] );
						$store = EDD_MailChimp_Store::find_or_create( $list );
						$store->orders->add( $order );
					}
				}
			}

			edd_insert_payment_note( $payment_id, __( 'Order details have been updated in MailChimp successfully', 'eddmc' ) );
		} catch (Exception $e) {
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

		try {
			$default_list = EDD_MailChimp_List::default();

			if ( $default_list ) {
				$store = EDD_MailChimp_Store::find_or_create( $default_list );
				$store->orders->remove( $order );
			}

			foreach( $order->lines as $line_item ) {
				$download = new EDD_MailChimp_Download( (int) $line_item['product_id'] );
				$preferences = $download->subscription_preferences();

				if ( ! empty( $preferences ) ) {
					foreach( $preferences as $list ) {
						$list = new EDD_MailChimp_List( $list['remote_id'] );
						$store = EDD_MailChimp_Store::find_or_create( $list );
						$store->orders->remove( $order );
					}
				}
			}

			edd_insert_payment_note( $payment_id, __( 'Order details have been removed from MailChimp.', 'eddmc' ) );
			return true;
		} catch (Exception $e) {
			edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $e->getMessage() );
			return false;
		}
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
