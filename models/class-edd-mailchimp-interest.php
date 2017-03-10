<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Interest extends EDD_MailChimp_Model {
  public $interest_category;

  public function __construct() {
    parent::__construct();
  }


  /**
  * Retrieve the list of categories associated with a list id
  *
  * @param  string $list_id       List id for which categories should be returned
  * @return array  $category_data Data about the category
  */
  private function _get_interests( $list_id = '' ) {

    global $post;

    $settings = (array) get_post_meta( $post->ID, '_edd_mailchimp', true );
    $category_data = array();

    // Uncomment for testing only:
    // delete_transient('edd_mailchimp_interest_categories_' . $list_id);

    $interest_categories = get_transient( 'edd_mailchimp_interest_categories_' . $list_id );

    if( false === $interest_categories && ! empty( $this->api ) ) {
      $interest_categories = $this->api->get( "lists/$list_id/interest-categories" );
      set_transient( 'edd_mailchimp_interest_categories_' . $list_id, $interest_categories, 24*24*24 );
    }

    if( $interest_categories && ! empty( $interest_categories['categories'] ) ) {

      foreach( $interest_categories['categories'] as $category ) {

        $category_id   = $category['id'];
        $category_name = $category['title'];

        $interests = $this->api->get( "lists/$list_id/interest-categories/$category_id/interests" );

        if ( $interests && ! empty( $interests['interests'] ) ) {
          foreach ( $interests['interests'] as $interest ) {
            $interest_id   = $interest['id'];
            $interest_name = $interest['name'];

            $category_data["$list_id|$interest_id"] = $category_name . ' - ' . $interest_name;
          }
        }
      }
    }

    return $category_data;
  }

}