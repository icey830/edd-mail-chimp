<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Mailchimp_Order extends EDD_Mailchimp_Model {

  public $payment;
  public $store;

  public function __construct( $payment ) {
    if ( is_integer($payment) ) {
      $this->payment = new EDD_Payment($payment)
    } elseif ( is_object( $payment ) && get_class($payment) === 'EDD_Payment' ) {
      $this->payment = $payment;
    } else {
      $this->payment = false;
    }
  }

  /**
   * Create an order on MailChimp
   *
   * @see    http://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/orders/
   * @see    http://docs.easydigitaldownloads.com/article/1113-eddpayment
   * @return [type] [description]
   */
  public function create() {

    $order = array(
      'id'       => (string) $this->payment->number,
      'customer' => array(
        'id'            => $this->payment->customer_id,
        'email_address' => $this->payment->email,
        'opt_in_status' => false,
        // 'company'       => '',
        'first_name'    => $this->payment->first_name,
        'last_name'     => $this->payment->last_name,
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
      'financial_status'     => $this->payment->status_nicename,
      'fulfillment_status'   => $this->payment->status_nicename,
      'currency_code'        => $this->payment->currency,
      'order_total'          => $this->payment->total,
      'order_url'            => add_query_arg( 'id', $this->payment->ID, admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details' ) ),
      'tax_total'            => $this->payment->tax,
      'processed_at_foreign' => $this->payment->completed_date,
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

    foreach ( $this->payment->cart_details as $line ) {
      $order['lines'][] = array(
        'id'         => $line['id'],
        'product_id' => $line['id'],
        'product_variant_id' => $line['item_number']['options']['price_id'],
        'quantity'   => $line['quantity'],
        'price'      => $line['price'],
        'discount'   => $line['discount']
      );
    }

    $api->post('ecommerce/stores/' . self::_get_store_id() . '/orders', $order);
  }
}