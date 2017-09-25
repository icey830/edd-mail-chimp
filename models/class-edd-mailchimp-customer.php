<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Customer extends EDD_MailChimp_Model {

	protected $_customer;

	public function __construct( $customer ) {
		parent::__construct();

		$this->_set_customer( $customer );
		$this->_build();
	}

	protected function _set_customer( $customer ) {
		if ( is_integer( $customer ) ) {
			$this->_customer = new EDD_Customer( $customer );
		} elseif ( is_object( $customer ) && get_class($customer) === 'EDD_Customer' ) {
			$this->_customer = $customer;
		}

		$this->id = apply_filters( 'edd.mailchimp.customer.id', $this->_customer->id, $this->_customer );
	}

	/**
	 * Build a customer record from defaults
	 *
	 * @return [type] [description]
	 */
	protected function _build() {
		$names      = explode( ' ', $this->_customer->name );
		$first_name = ! empty( $names[0] ) ? $names[0] : '';
		$last_name  = '';
		if( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$last_name = implode( ' ', $names );
		}

		$customer = array(
			'id'            => $this->_customer->id,
			'email_address' => $this->_customer->email,
			'opt_in_status' => apply_filters('edd.mailchimp.customer.opt_in_status', false, $this->_customer, $this->_payment),  // false => transactional, true => subscribed
			'orders_count'  => (int) $this->_customer->purchase_count,
			'total_spent'   => $this->_customer->purchase_value,
			'first_name'    => $first_name,
			'last_name'     => $last_name,
		);

		$this->_record = apply_filters( 'edd.mailchimp.customer', $customer );
	}

}
