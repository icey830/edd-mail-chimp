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
   * Fetch remote interests for a list.
   *
   * @return [type] [description]
   */
  public function get_remote_interests() {
    $all_category_data = array();

    $result = $this->api->get( $this->_resource . "/interest-categories", array( 'count' => 100 ) );

    if ( $this->api->success() && ! empty( $result['categories'] ) ) {

      foreach( $result['categories'] as $category ) {

        $category_data = array(
          'id'   => $category['id'],
          'name' => $category['title'],
          'interests' => array(),
        );

        $endpoint = $this->_resource . '/interest-categories/' . $category['id'] . '/interests';
        $interests = $this->api->get( $endpoint, array( 'count' => 100 ) );

        if ( $interests && ! empty( $interests['interests'] ) ) {
          foreach ( $interests['interests'] as $interest ) {
            $interest_id   = $interest['id'];
            $interest_name = $interest['name'];

            $interest_data = array(
              'id'   => $interest['id'],
              'name' => $interest['name'],
            );

            $category_data['interests'][] = $interest_data;
          }
        }

        $all_category_data[] = $category_data;
      }
    }

    return $all_category_data;
  }

}
