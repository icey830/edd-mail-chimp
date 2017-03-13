<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Store extends EDD_MailChimp_Model {

  public $id;
  public $list_id;
  protected $_endpoint = 'ecommerce/stores';
  protected $_has_many = array(
    'products'  => 'EDD_MailChimp_Product',
    'carts'     => 'EDD_MailChimp_Cart',
    'orders'    => 'EDD_MailChimp_Order',
    'customers' => 'EDD_MailChimp_Customer',
  );

  public function __construct() {
    parent::__construct();
  }

  /**
   * Find or create a store record based on the provided MailChimp list.
   *
   * @param  mixed $list EDD_MailChimp_List | string $list_id | bool
   * @return mixed EDD_MailChimp_Store | Exception
   */
  public static function find_or_create( $list = false ) {
    $klass = new static;
    $klass->_set_list( $list );
    $klass->_set_resource();

    if ( $klass->exists() ) {
      $response = $klass->api->getLastResponse();
      $klass->_record = json_decode( $response['body'], true );
      return $klass;
    }

    $klass->_build();
    $result = $klass->api->post( $klass->_endpoint, $klass->_record );

    if ( $klass->api->success() ) {
      $klass->_record = $result;
      return $klass;
    }

    throw new Exception( __('Could not find or create this store on MailChimp', 'eddmc') );
  }


  /**
   * Assign the list which this store is associated with.
   *
   * @param mixed $list EDD_MailChimp_List | string $list_id
   */
  protected function _set_list( $list = false  ) {
    if ( $list === false ) {
      $this->list_id = edd_get_option( 'eddmc_list', false );
    } elseif ( is_string( $list ) ) {
      $this->list_id = $list;
    } elseif ( is_object( $list ) && get_class( $list ) === 'EDD_MailChimp_List' ) {
      $this->list_id = $list->id;
    }
  }


  /**
   * The store ID is a combination of the home url hash and the list ID, as the list cannot be
   * changed for a store in the new api.
   *
   * @return void
   */
  protected function _set_resource() {
    $id = md5( home_url() )  . '-' . $this->list_id;
    $this->id = apply_filters('edd.mailchimp.store.id', $id, $this->list_id);
    $this->_resource = $this->_endpoint . '/' . $this->id;
  }

  /**
   * Resource getter
   *
   * @return [type] [description]
   */
  public function get_resource() {
    return $this->_resource;
  }


  /**
   * Build the store record based on sensible defaults.
   *
   * @return $this
   */
  protected function _build( $args = array() ) {
    $record = array_merge(
      array(
        'id'             => $this->id,
        'list_id'        => $this->list_id,
        'name'           => get_bloginfo('name'),
        'platform'       => __('Easy Digital Downloads', 'easy-digital-downloads'),
        'domain'         => home_url(),
        'is_syncing'     => true,
        'email_address'  => get_site_option('admin_email'),
        'currency_code'  => edd_get_currency(),
        'money_format'   => edd_currency_filter( '' ),
        'primary_locale' => substr( get_locale(), 0, 2 ),
        'timezone'       => get_site_option('timezone_string')
      ),
      $args
    );

    $this->_record = apply_filters('edd.mailchimp.store', $record);
    return $this;
  }


  /**
   * Set the store's remote sync status
   *
   * @param  boolean $status [description]
   * @return boolean         [description]
   */
  public function is_syncing( $status = true ) {
    $this->_record['is_syncing'] = $status;
    $this->save();
  }

}
