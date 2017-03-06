<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Mailchimp_Model {

  public $api;

  public function __construct( $list_id ) {
    $this->api = 'api';
  }

  /**
   * [create description]
   * @return [type] [description]
   */
  abstract public function create() {}

  /**
   * [update description]
   * @return [type] [description]
   */
  abstract public function update() {}

  /**
   * [delete description]
   * @return [type] [description]
   */
  abstract public function delete() {}
}
