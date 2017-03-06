<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Mailchimp_Customer extends EDD_Mailchimp_Model {

  public $customer;

  public function __construct( $customer ) {
    if ( is_integer($customer) ) {
      $this->customer = new EDD_Customer($customer)
    } elseif ( is_object( $customer ) && get_class($customer) === 'EDD_Customer' ) {
      $this->customer = $customer;
    } else {
      $this->customer = false;
    }
  }

  /**
   * [create description]
   * @see  http://docs.easydigitaldownloads.com/article/1004-eddcustomer
   * @return [type] [description]
   */
  public function create() {

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
  }
}