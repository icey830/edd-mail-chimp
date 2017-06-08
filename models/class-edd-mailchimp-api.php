<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use \DrewM\MailChimp\MailChimp;

class EDD_MailChimp_API {

	public $api;
	protected $_endpoint;
	protected $_resource;

	public function __construct() {
		$key = edd_get_option('eddmc_api', false);

		if ( $key ) {
			$this->api = new MailChimp( trim( $key ) );

			if ( defined('EDD_MC_VERIFY_SSL') && EDD_MC_VERIFY_SSL === false ) {
				$this->api->verify_ssl = false;
			}
		}
	}

}
