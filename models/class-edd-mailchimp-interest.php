<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Interest extends EDD_MailChimp_Model {

	public function __construct( $remote_interest_id ) {
		$this->remote_id = $remote_interest_id;
		$this->_set_record();
	}


	/**
	 * Sets the class `_record` property if the interest has been connected locally.
	 *
	 * @return EDD_MailChimp_Interest
	 */
	private function _set_record() {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $wpdb->edd_mailchimp_interests WHERE interest_remote_id = %s LIMIT 1",
			$this->remote_id
		), ARRAY_A );

		if ( $result !== null ) {
			$this->_record = $result;
		}

		return $this;
	}


	/**
	 * Find all interests associated with the given download ID.
	 *
	 * @param  mixed $download  int | EDD_Download
	 * @return array
	 */
	public static function associated_with_download( $download ) {
		if ( is_integer( $download ) ) {
			$klass = new EDD_Download( $download );
		} elseif ( is_object( $download ) && get_class( $download ) === 'EDD_Download' ) {
			$klass = $download;
		}

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT
				interests.id,
				interests.interest_category_name,
				interests.interest_remote_id,
				interests.interest_name
			FROM $wpdb->edd_mailchimp_interests interests
			LEFT JOIN $wpdb->edd_mailchimp_downloads_interests dl
			ON dl.interest_id = interests.id
			WHERE dl.download_id = %d",
			$klass->ID
		) );
	}


	/**
	 * Associate the current list with the provided EDD Download.
	 *
	 * @param  mixed $download  int | EDD_Download
	 * @return mixed            int insert_id | false
	 */
	public function associate_with_download( $download ) {

		if ( is_integer( $download ) ) {
			$klass = new EDD_Download( $download );
		} elseif ( is_object( $download ) && get_class( $download ) === 'EDD_Download' ) {
			$klass = $download;
		}

		global $wpdb;

		$result = $wpdb->insert( $wpdb->edd_mailchimp_downloads_interests, array(
			'download_id' => $klass->ID,
			'interest_id' => $this->id,
		), array(
			'%d', '%d'
		) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

}
