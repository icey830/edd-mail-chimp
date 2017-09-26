<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Order extends EDD_MailChimp_Model {

	public $_endpoint = 'orders';
	protected $_payment;

	public function __construct( $payment = false ) {
		parent::__construct();

		$this->_set_payment( $payment );
		$this->_build();
	}

	/**
	 * [_set_payment description]
	 * @param [type] $payment [description]
	 */
	protected function _set_payment( $payment ) {
		if ( is_integer( $payment ) ) {
			$this->_payment = new EDD_Payment($payment);
		} elseif ( is_object( $payment ) && get_class( $payment ) === 'EDD_Payment' ) {
			$this->_payment = $payment;
		}

		$this->id = apply_filters( 'edd.mailchimp.order.id', $this->_payment->ID, $this->_payment );
	}


	/**
	 * [build description]
	 * @return [type] [description]
	 */
	protected function _build() {

		$customer = new EDD_MailChimp_Customer( (int) $this->_payment->customer_id );

		switch( $this->_payment->status ) {

			case 'refunded' :
				$status = 'refunded';
				break;

			case 'cancelled' :
				$status = 'cancelled';
				break;

			case 'publish' :
			case 'complete':
			case 'revoked' :
			default :

				$status = 'pending';
				break;
		}

		$order = array(
			'id'       => (string) $this->_payment->ID,
			'customer' => array(
				'id'            => $this->_payment->customer_id,
				'email_address' => $this->_payment->email,
				'opt_in_status' => apply_filters( 'edd.mailchimp.customer.opt_in_status', false, $customer, $this->_payment ), // false => transactional, true => subscribed
				// 'company'       => '',
				'first_name'    => $this->_payment->first_name,
				'last_name'     => $this->_payment->last_name,
				'orders_count'  => $customer->orders_count,
				'total_spent'   => $customer->total_spent,
				// 'address'       => array(
				//   'address1'      => '',
				//   'address2'      => '',
				//   'city'          => '',
				//   'province'      => '',
				//   'province_code' => '',
				//   'postal_code'   => '',
				//   'country'       => '',
				//   'country_code'  => '',
				// )
			),
			'financial_status'     => $status,
			'fulfillment_status'   => $status,
			'landing_site'         => home_url(),
			'currency_code'        => $this->_payment->currency,
			'order_total'          => $this->_payment->total,
			'order_url'            => add_query_arg( 'payment_key', $this->_payment->key, edd_get_success_page_uri() ),
			'tax_total'            => $this->_payment->tax,
			'processed_at_foreign' => $this->_payment->completed_date,
			'lines' => array(),
			// 'discount_total'       => '',
			// 'shipping_total'       => '',
			// 'tracking_code'        => '',
			// 'cancelled_at_foreign' => '',
			// 'updated_at_foreign'   => '',
			// 'billing_address' => array(
			//   'name'          => '',
			//   'address1'      => '',
			//   'address2'      => '',
			//   'city'          => '',
			//   'province'      => '',
			//   'province_code' => '',
			//   'postal_code'   => '',
			//   'country'       => '',
			//   'country_code'  => '',
			//   'phone'         => '',
			//   'company'       => '',
			// ),
		);

		foreach ( $this->_payment->cart_details as $line ) {
			if ( isset( $line['item_number']['options']['price_id'] ) || null === $line['item_number']['options']['price_id'] ) {
				$variant_id = $line['id'] . '_1';
			} else {
				$variant_id = $line['id'] . '_' . $line['item_number']['options']['price_id'];
			}

			$order['lines'][] = array(
				'id'         => (string) $line['id'],
				'product_id' => (string) $line['id'],
				'product_variant_id' => $variant_id,
				'quantity'   => $line['quantity'],
				'price'      => $line['price'],
				'discount'   => $line['discount']
			);
		}

		$this->_record = apply_filters( 'edd.mailchimp.order', $order, $this->_payment );
		return $this;
	}
}
