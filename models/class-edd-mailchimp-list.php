<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_List extends EDD_MailChimp_Model {

  public $id;
  protected $_endpoint = 'lists';

  public function __construct( $list_id = '' ) {
    parent::__construct();

    if ( $list_id === '' ) {
      $this->id = edd_get_option( 'eddmc_list', false );
    } else {
      $this->id = $list_id;
    }

    if ( $this->id ) {
      $this->_resource = $this->_endpoint . '/' . $this->id;
    }
  }

  /**
   * Subscribe an email to a list.
   *
   * @return [type] [description]
   */
  public function subscribe() {
    return;

    // Make sure an API key and list ID has been entered
    if ( empty( $this->api ) || ! $this->id ) {
      return false;
    }

    $opt_in = edd_get_option('eddmc_double_opt_in');

    if ( $opt_in_override ) {
      $opt_in = true;
    }

    $status = $opt_in ? 'pending' : 'subscribed';

    $merge_fields = array( 'FNAME' => $user_info['first_name'], 'LNAME' => $user_info['last_name'] );
    $interests = isset( $options['interests'] ) ? $options['interests'] : array();

    $subscriber_hash = $this->api->subscriberHash( $user_info['email'] );

    $result = $this->api->put("lists/$list_id/members/$subscriber_hash", apply_filters( 'edd_mc_subscribe_vars', array(
      'email_address' => $user_info['email'],
      'status_if_new' => $status,
      'merge_fields'  => $merge_fields,
      'interests'     => $interests,
    ) ));

    if( $result ) {
      return true;
    }

    return false;
  }



  /**
   * Retrieve the MailChimp lists
   *
   * Must return an array like this:
   *   array(
   *     'some_id'  => 'value1',
   *     'other_id' => 'value2'
   *   )
   */
  private function _get_lists() {
    $lists = array();

    $list_data = get_transient( 'edd_mailchimp_list_data' );

    if( false === $list_data && ! empty( $this->api ) ) {
      $list_data = $this->api->get('lists', array( 'count' => 100 ) );
      set_transient( 'edd_mailchimp_list_data', $list_data, 24*24*24 );
    }

    if( ! empty( $list_data ) ) {
      foreach( $list_data['lists'] as $key => $list ) {
        $lists[ $list['id'] ] = $list['name'];
      }
    }

    return $lists;
  }


}