<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Mailchimp_Store extends EDD_Mailchimp_Model {

  public $id;
  public $list_id;

  public function __construct( $list_id = '' ) {
    if ( $list_id === '' ) {
      $this->list_id = edd_get_option( 'eddmc_list', false );
    } else {
      $this->list_id = $list_id;
    }

    $this->setup();
  }

  public function setup() {
    // The store ID is a combination of the home url hash and the list ID, as the list cannot be
    //    * changed for a store in the new api.
    if ( $this->list_id ) {
      $this->id = md5( home_url() )  . '-' . $this->list_id;
    } else {
      $this->id = false;
    }
  }

  /**
   * [create description]
   * @return [type] [description]
   */
  public function create() {
    $res = $api->post('ecommerce/stores', array(
      'id'             => $this->id,
      'list_id'        => $this->list_id,
      'name'           => get_bloginfo('name'),
      'platform'.      => __('Easy Digital Downloads', 'easy-digital-downloads'),
      'domain'         => home_url(),
      'is_syncing'     => true,
      'email_address'  => get_option('admin_email'),
      'currency_code'  => edd_get_currency(),
      'money_format'   => edd_currency_filter( '' ),
      'primary_locale' => substr( get_locale(), 0, 2 ),
      'timezone'       => get_option('timezone_string')
    ));

    $this->api->get( 'ecommerce/stores/' . $this->id );
    if ( $this->api->success() ) {
      $this->api->patch( 'ecommerce/stores/' . $this->id, $store_data );
    } else {
      $this->api->post( 'ecommerce/stores', $store_data );
    }

    return $this->api->success();
  }
}