<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_filter( 'edd_save_download', array( $this, 'save_metabox' ) );
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

		$lists = EDD_MailChimp_List::connected();
		$associated_lists = EDD_MailChimp_List::associated_with_download( $post->ID );

		$associated_list_ids = array();

		foreach ( $associated_lists as $list ) {
			$associated_list_ids[] = $list->id;
		}

		foreach( $lists as $list ) {
			$list = new EDD_MailChimp_List( $list->remote_id );

			echo '<label>';
				echo '<input type="checkbox" name="_edd_mailchimp_lists' . '[]" value="' . esc_attr( $list->id ) . '"' . checked( true, in_array( $list->id, $associated_list_ids ), false ) . '>';
				echo '&nbsp;' . $list->name;
			echo '</label><br/>';

			$interests = $list->interests();
			$associated_interests = EDD_MailChimp_List::interests_associated_with_download( $post->ID );

			$associated_interest_ids = array();

			foreach ( $associated_interests as $interest ) {
				$associated_interest_ids[] = $interest->id;
			}

			if ( ! empty( $interests ) ) {
				foreach ( $interests as $interest ){
					echo '<label>';
						echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="_edd_mailchimp_interests[]" value="' . esc_attr( $interest->id ) . '"' . checked( true, in_array( $interest->id, $associated_interest_ids ), false ) . '>';
						echo '&nbsp;' . $interest->interest_name;
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
