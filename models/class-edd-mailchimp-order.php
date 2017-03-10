<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Order extends EDD_MailChimp_Model {

  protected $_payment;
  public $_endpoint = 'orders';

  public function __construct( $payment = false ) {
    parent::__construct();

    if ( is_integer($payment) ) {
      $this->_payment = new EDD_Payment($payment);
    } elseif ( is_object( $payment ) && get_class($payment) === 'EDD_Payment' ) {
      $this->_payment = $payment;
    } else {
      $this->_payment = false;
    }

    if ( $this->_payment ) {
      $this->_resource = $this->_endpoint . '/' . $this->_payment->number;
    }
  }

  /**
   * [build description]
   * @return [type] [description]
   */
  protected function _build() {
    $this->_record = array(
      'id'       => (string) $this->_payment->number,
      'customer' => array(
        'id'            => $this->_payment->customer_id,
        'email_address' => $this->_payment->email,
        'opt_in_status' => false,
        // 'company'       => '',
        'first_name'    => $this->_payment->first_name,
        'last_name'     => $this->_payment->last_name,
        'orders_count'  => '',
        'total_spent'   => '',
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
      'campaign_id'          => '',
      'financial_status'     => $this->_payment->status_nicename,
      'fulfillment_status'   => $this->_payment->status_nicename,
      'currency_code'        => $this->_payment->currency,
      'order_total'          => $this->_payment->total,
      'order_url'            => add_query_arg( 'id', $this->_payment->ID, admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details' ) ),
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
      $this->_record['lines'][] = array(
        'id'         => $line['id'],
        'product_id' => $line['id'],
        'product_variant_id' => $line['item_number']['options']['price_id'],
        'quantity'   => $line['quantity'],
        'price'      => $line['price'],
        'discount'   => $line['discount']
      );
    }
  }
}
