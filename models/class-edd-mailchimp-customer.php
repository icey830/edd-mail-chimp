<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Customer extends EDD_MailChimp_Model {

  public $customer;

  public function __construct( $customer ) {
    parent::__construct();

    if ( is_integer($customer) ) {
      $this->customer = new EDD_Customer($customer);
    } elseif ( is_object( $customer ) && get_class($customer) === 'EDD_Customer' ) {
      $this->customer = $customer;
    } else {
      $this->customer = false;
    }
  }

  /**
   * Build a customer record from defaults
   *
   * @return [type] [description]
   */
  protected function _build() {
    $names      = explode( ' ', $this->customer->name );
    $first_name = ! empty( $names[0] ) ? $names[0] : '';
    $last_name  = '';
    if( ! empty( $names[1] ) ) {
      unset( $names[0] );
      $last_name = implode( ' ', $names );
    }

    $customer = array(
      'id' => $this->customer->id,
      'email_address' => $this->customer->email,
      'opt_in_status' => false,
      'orders_count'  => $this->customer->purchase_count,
      'total_spent'   => $this->customer->purchase_value,
      'first_name'    => $first_name,
      'last_name'     => $last_name,
    );

    $this->_record = apply_filters('edd.mailchimp.customer', $customer);
  }

}
