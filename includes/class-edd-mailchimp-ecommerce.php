<?php

use \DrewM\MailChimp\MailChimp;

/**
 * EDD MailChimp Ecommerce360 class
 *
 * @copyright   Copyright (c) 2014, Dave Kiss
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
*/
class EDD_MailChimp_Ecommerce extends EDD_MailChimp {

	public function __construct() {
		add_action( 'init', array( $this, 'set_ecommerce360_session' ) );
		add_action( 'edd_insert_payment', array( $this, 'set_ecommerce360_flags' ), 10, 2 );
		add_action( 'edd_complete_purchase', array( $this, 'record_ecommerce360_purchase' ) );
		add_action( 'edd_update_payment_status', array( $this, 'delete_ecommerce360_purchase' ), 10, 3 );
	}

	/**
	 * Sets flags in post meta so that we can detect them when completing a purchase via IPN
	 *
	 * @param  integer $payment_id
	 * @param  array $payment_data
	 * @return bool
	 */
	public function set_ecommerce360_flags( $payment_id = 0, $payment_data = array() ) {

		// Make sure API has been instantiated
		if ( empty( $this->api ) ) {
			return;
		}

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
	public function record_ecommerce360_purchase( $payment_id = 0 ) {

		// Make sure API has been instantiated
		if ( empty( $this->api ) ) {
			return;
		}

		// Don't record details if we're in test mode
		if ( edd_is_test_mode() ) {
			return;
		}

		$payment = new EDD_Payment( $payment_id  );

		if ( is_array( $payment->cart_details ) ) {

			$items = array();

			// Ensure the store ID is set with MailChimp
			if ( ! $this->update_api_store_id() ) {
				edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 error, store ID could not be updated', 'eddmc' ) );
				return false;
			}

			// Increase purchase count and earnings
			foreach ( $payment->cart_details as $index => $download ) {

				// Get the categories that this download belongs to, if any
				$terms = get_the_terms( $download['id'], 'download_category' );

				if ( $terms && ! is_wp_error( $terms ) ) {
					$categories = array();

					foreach ( $terms as $term ) {
						$categories[] = $term->name;
					}

					$category_id   = $terms[0]->term_id;
					$category_name = join( " - ", $categories );
				} else {
					$category_id   = 1;
					$category_name = 'Download';
				}

				$item = array(
					'id' => (string) ($index + 1),
					'product_id' => (string) $download['id'],
					'quantity' => intval( $download['quantity'] ),
					'price' => $download['subtotal'], // double, cost of single line item
				);

				if ( edd_has_variable_prices( $download['id'] ) && isset( $download['item_number'], $download['item_number']['options'], $download['item_number']['options']['price_id'] ) ) {
					$prices = get_post_meta( $download['id'], 'edd_variable_prices', true );
					if ( isset( $prices[$download['item_number']['options']['price_id']] ) ) {
						$item['product_variant_title'] = $prices[$download['item_number']['options']['price_id']]['name'];
						$item['product_variant_id'] = (string) $download['item_number']['options']['price_id'];
					} else {
						$item['product_variant_id'] = (string) $category_id;
						$item['product_variant_title'] = $category_name;
					}
				} else {
					$item['product_variant_id'] = (string) $category_id;
					$item['product_variant_title'] = $category_name;
				}

				$items[] = apply_filters( 'edd_mc_item_vars', $item, $payment_id, $download );
			}

			$order = array(
				'id' => (string) $payment_id,
				'customer'   => array(
					'id' => (string) $user_info['id'],
					'email_address' => $user_info['email'],
					'opt_in_status' => false,
				),
				'order_total' => $amount,
				'lines' => $items,
				'currency_code' => $download['currency'],
				'processed_at_foreign' => get_the_date( 'c', $payment_id ),
			);

			// Set Ecommerce360 variables if they exist
			$campaign_id = get_post_meta( $payment_id, '_edd_mc_campaign_id', true );
			$email_id    = get_post_meta( $payment_id, '_edd_mc_email_id', true );

			// TODO: Fetch unique email address for customer using the ecommerce tracking email id?

			if ( ! empty( $campaign_id ) ) {
				$order['campaign_id'] = $campaign_id;
			}

			if ( $tax != 0 ) {
				$order['tax_total'] = $tax; // double, optional
			}

			$order = apply_filters( 'edd_mc_order_vars', $order, $payment_id );

			// Send/update order in MailChimp
			try {
				// TODO: Need to post if new, put if update?
				$result = $this->api->post( 'ecommerce/stores/' . self::_get_store_id() . '/orders', $order );
				if ( $this->api->success() ) {
					edd_insert_payment_note( $payment_id, __( 'Order details have been added to MailChimp successfully', 'eddmc' ) );
				} else {
					// attempt to update if order ID already exists
					$result = $this->api->patch( 'ecommerce/stores/' . self::_get_store_id() . '/orders/' . $order['id'], $order );
					if ( $this->api->success() ) {
						edd_insert_payment_note( $payment_id, __( 'Order details have been updated in MailChimp successfully', 'eddmc' ) );
					} else {
						edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $this->api->getLastError() . ( WP_DEBUG ? print_r( $result, true ) : '' ) );
						return false;
					}
				}
			} catch (Exception $e) {
				edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $e->getMessage() );
				return false;
			}

			return true;
		}
	}

	/**
	 * Remove an order from MailChimp if the payment was refunded
	 *
	 * @return bool
	 */
	public function delete_ecommerce360_purchase( $payment_id, $new_status, $old_status) {
		if ( 'publish' != $old_status && 'revoked' != $old_status ) {
			return;
		}

		if ( 'refunded' != $new_status ) {
			return;
		}

		// Make sure an API key has been entered
		if ( empty( $this->api ) ) {
			return false;
		}

		// Send to MailChimp
		try {
			$result = $this->api->delete( 'ecommerce/stores/' . self::_get_store_id() . '/orders/' . $payment_id );
			if ( ! $this->api->success() ) {
				edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error (delete purchase): ', 'eddmc' ) . $this->api->getLastError() );
				return false;
			}
			edd_insert_payment_note( $payment_id, __( 'Order details have been removed from MailChimp successfully', 'eddmc' ) );
			return true;
		} catch (Exception $e) {
			edd_insert_payment_note( $payment_id, __( 'MailChimp Ecommerce360 Error: ', 'eddmc' ) . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Enables MailChimp's Ecommerce360 tracking from the parameters
	 * added to a newsletter campaign
	 *
	 * @uses campaign UID
	 * @uses member email's UID
	 */
	public function set_ecommerce360_session() {
		$mc_cid = isset( $_GET['mc_cid'] ) ? $_GET['mc_cid'] : '';
		$mc_eid = isset( $_GET['mc_eid'] ) ? $_GET['mc_eid'] : '';

		if ( ! empty( $mc_cid ) && ! empty( $mc_eid ) ) {
			EDD()->session->set( self::_get_session_id( 'campaign' ), filter_var( $mc_cid , FILTER_SANITIZE_STRING ) );
			EDD()->session->set( self::_get_session_id( 'email' ),    filter_var( $mc_eid , FILTER_SANITIZE_STRING ) );
		}
	}

	/**
	 * Returns the unique EC360 session keys for this EDD installation.
	 *
	 * @param  string $type campaign | email
	 * @return string Key identifier for stored sessions
	 */
	protected static function _get_session_id( $type = 'campaign' ) {
		$prefix = substr( $type, 0, 1);
		return sprintf( 'edd_mc360_%1$s_%2$sid', substr( self::_get_store_id(), 0, 10 ), $prefix );
	}

}
