<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Download extends EDD_Download {

	public function __construct( $_id = false ) {
		parent::__construct( $_id );
	}

	/**
	 * Fetch this download's MailChimp subscription preferences.
	 *
	 * @return array
	 */
	public function subscription_preferences() {
		global $wpdb;

		// Check backwards compatibility
		$old_list_data = get_post_meta( $this->ID, '_edd_mailchimp', true );
		if ( ! empty( $old_list_data ) ) {
			$old_lists     = array();
			$old_interests = array();
			foreach ( $old_list_data as $old_data ) {
				if ( strpos( $old_data, '|' ) ) { // This is an old 'interest'.

					// Old format is Remote List ID, old ID, and interest name
					$old_interest  = explode( '|', $old_data, 3 );
					$list_id       = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->edd_mailchimp_lists WHERE remote_id = %s", $old_interest[0] ) );
					$interest_name = $old_interest[2];

					if ( ! empty( $list_id ) ) {
						$interest_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->edd_mailchimp_interests WHERE list_id = %d AND interest_name ='%s'", (int) $list_id, $interest_name ) );
					}

					if ( ! empty( $interest_id ) ) {
						$old_interests[] = $interest_id;
					}

				} else { // This is a top level list.

					$list_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->edd_mailchimp_lists WHERE remote_id = %s", $old_data ) );
					if ( ! empty( $list_id ) ) {
						$old_lists[] = $list_id;
					}

				}
			}

			if ( ! empty( $old_lists ) ) {
				foreach ( $old_lists as $new_list_id ) {
					$this->add_preferred_list( $new_list_id );
				}
			}

			if ( ! empty( $old_interests ) ) {
				foreach ( $old_interests as $new_interest_id ) {
					$this->add_preferred_interest( $new_interest_id );
				}
			}

			delete_post_meta( $this->ID, '_edd_mailchimp' );
		}

		$preferences = array();

		$preferred_lists = $wpdb->get_results( $wpdb->prepare(
			"SELECT lists.id, lists.remote_id, lists.name, lists.is_default
			FROM $wpdb->edd_mailchimp_lists lists
			LEFT JOIN $wpdb->edd_mailchimp_downloads_lists dl
			ON dl.list_id = lists.id
			WHERE dl.download_id = %d",
			$this->ID
		) );

		if ( ! empty( $preferred_lists ) ) {
			foreach( $preferred_lists as $list ) {

				$interests = array();

				$preferred_interests = $wpdb->get_results( $wpdb->prepare(
					"SELECT interests.id, interests.interest_remote_id
					FROM $wpdb->edd_mailchimp_interests interests
					LEFT JOIN $wpdb->edd_mailchimp_downloads_interests dl
					ON dl.interest_id = interests.id
					LEFT JOIN $wpdb->edd_mailchimp_lists lists
					ON lists.id = interests.list_id
					WHERE dl.download_id = %d
					AND interests.list_id = %d;",
					$this->ID,
					$list->id
				) );

				if ( ! empty( $preferred_interests ) ) {
					foreach( $preferred_interests as $interest ) {
						$interests[] = array(
							'id' => $interest->id,
							'remote_id' => $interest->interest_remote_id
						);
					}
				}

				$preferences[] = array(
					'id'         => $list->id,
					'name'       => $list->name,
					'remote_id'  => $list->remote_id,
					'is_default' => $list->is_default,
					'interests'  => $interests,
				);
			}
		}

		return $preferences;
	}

	/**
	 * Associate the current EDD Download with the provided $list_id
	 *
	 * @param  mixed $list_id   int
	 * @return mixed            int insert_id | false
	 */
	public function add_preferred_list( $list_id ) {

		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT *
				FROM $wpdb->edd_mailchimp_downloads_lists
				WHERE download_id = %d
				AND list_id = %d",
				$this->ID,
				$list_id
			)
		);

		if ( $result !== null ) {
			return $result->id;
		}

		$result = $wpdb->insert( $wpdb->edd_mailchimp_downloads_lists, array(
			'download_id' => $this->ID,
			'list_id'     => $list_id,
		), array(
			'%d', '%d'
		) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}


	/**
	 * Associate the current EDD Download with the provided interest ID.
	 *
	 * @param  mixed $interest_id  int
	 * @return mixed            int insert_id | false
	 */
	public function add_preferred_interest( $interest_id ) {

		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT *
				FROM $wpdb->edd_mailchimp_downloads_interests
				WHERE download_id = %d
				AND interest_id = %d",
				$this->ID,
				$interest_id
			)
		);

		if ( $result !== null ) {
			return $result->id;
		}

		$result = $wpdb->insert( $wpdb->edd_mailchimp_downloads_interests, array(
			'download_id' => $this->ID,
			'interest_id' => $interest_id,
		), array(
			'%d', '%d'
		) );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	public function clear_subscription_preferences() {
		global $wpdb;

		$wpdb->delete(
			$wpdb->edd_mailchimp_downloads_lists,
			array( 'download_id' => $this->ID ),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->edd_mailchimp_downloads_interests,
			array( 'download_id' => $this->ID ),
			array( '%d' )
		);

		return $this;
	}

	/**
	 * Remove all associated lists from this download.
	 *
	 * @return mixed  int | false
	 */
	public function clear_associated_lists() {
		global $wpdb;
		return $wpdb->delete(
			$wpdb->edd_mailchimp_downloads_lists,
			array( 'download_id' => $this->ID ),
			array( '%d' )
		);
	}

	/**
	 * Remove all associated interests from this download.
	 *
	 * @return mixed  int | false
	 */
	public function clear_associated_interests() {
		global $wpdb;
		return $wpdb->delete(
			$wpdb->edd_mailchimp_downloads_interests,
			array( 'download_id' => $this->ID ),
			array( '%d' )
		);
	}

}
