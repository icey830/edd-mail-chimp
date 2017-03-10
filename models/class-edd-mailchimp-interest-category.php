<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Interest_Category extends EDD_MailChimp_Model {
  public $list;
  public function __construct() {
    parent::__construct();
  }

}
