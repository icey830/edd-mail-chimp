<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Metabox {

  public function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
    add_filter( 'edd_metabox_fields_save', array( $this, 'save_metabox' ) );
  }

  /**
   * Register the metabox on the 'download' post type
   */
  public function add_metabox() {
    if ( current_user_can( 'edit_product', get_the_ID() ) ) {
      add_meta_box( 'edd_mailchimp', 'MailChimp', array( $this, 'render_metabox' ), 'download', 'side' );
    }
  }


  /**
   * Display the metabox, which is a list of newsletter lists
   */
  public function render_metabox() {

    global $post;

    echo '<p>' . __( 'Select the lists you wish buyers to be subscribed to when purchasing.', 'eddmc' ) . '</p>';

    $checked = (array) get_post_meta( $post->ID, '_edd_mailchimp', true );

    foreach( $this->_get_lists() as $list_id => $list_name ) {
      echo '<label>';
        echo '<input type="checkbox" name="_edd_mailchimp' . '[]" value="' . esc_attr( $list_id ) . '"' . checked( true, in_array( $list_id, $checked ), false ) . '>';
        echo '&nbsp;' . $list_name;
      echo '</label><br/>';

      $interests = $this->_get_interests( $list_id );
      if( ! empty( $interests ) ) {
        foreach ( $interests as $interest_id => $interest_name ){
          echo '<label>';
            echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="_edd_mailchimp[]" value="' . esc_attr( $interest_id ) . '"' . checked( true, in_array( $interest_id, $checked ), false ) . '>';
            echo '&nbsp;' . $interest_name;
          echo '</label><br/>';
        }
      }
    }
  }


  /**
   * Add metabox fields that should be saved to the post meta
   *
   * @param  array $fields
   * @return array
   */
  public function save_metabox( $fields ) {
    $fields[] = '_edd_mailchimp';
    return $fields;
  }

}
