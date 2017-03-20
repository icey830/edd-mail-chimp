<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'edd_save_download', array( $this, 'save_metabox' ), 10, 2 );
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
		$associated_lists = EDD_MailChimp_List::associated_with_download( (int) $post->ID );

		$associated_list_ids = array();

		foreach ( $associated_lists as $list ) {
			$associated_list_ids[] = $list->remote_id;
		}

		foreach( $lists as $list ) {
			$list = new EDD_MailChimp_List( $list->remote_id );

			echo '<label>';
				echo '<input type="checkbox" name="edd_mailchimp_lists' . '[]" value="' . esc_attr( $list->remote_id ) . '"' . checked( true, in_array( $list->remote_id, $associated_list_ids ), false ) . '>';
				echo '&nbsp;' . $list->name;
			echo '</label><br/>';

			$interests = $list->interests();
			$associated_interests = EDD_MailChimp_Interest::associated_with_download( (int) $post->ID );

			$associated_interest_ids = array();

			foreach ( $associated_interests as $interest ) {
				$associated_interest_ids[] = $interest->interest_remote_id;
			}

			if ( ! empty( $interests ) ) {
				foreach ( $interests as $interest ){
					echo '<label>';
						echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="edd_mailchimp_interests[]" value="' . esc_attr( $interest->interest_remote_id ) . '"' . checked( true, in_array( $interest->interest_remote_id, $associated_interest_ids ), false ) . '>';
						echo '&nbsp;' . $interest->interest_name;
					echo '</label><br/>';
				}
			}
		}
	}

	/**
	 * Save metabox data
	 *
	 * @param  int $post_id  Download ID
	 * @param  WP_Post $post
	 * @return [type]          [description]
	 */
	public function save_metabox( $post_id, $post ) {
		$product = new EDD_MailChimp_Product( $post_id );

		if ( isset( $_POST['edd_mailchimp_lists'] ) && ! empty( $_POST['edd_mailchimp_lists'] ) ) {
			$product->clear_associated_lists();

			foreach ( $_POST['edd_mailchimp_lists'] as $remote_list_id ) {
				$remote_list_id = sanitize_key( $remote_list_id );

				$list = new EDD_MailChimp_List( $remote_list_id );
				$list->associate_with_download( $post_id );
			}
		}

		if ( isset( $_POST['edd_mailchimp_interests'] ) && ! empty( $_POST['edd_mailchimp_interests'] ) ) {
			$product->clear_associated_interests();

			foreach ( $_POST['edd_mailchimp_interests'] as $remote_interest_id ) {
				$remote_interest_id = sanitize_key( $remote_interest_id );

				$interest = new EDD_MailChimp_Interest( $remote_interest_id );
				$interest->associate_with_download( $post_id );
			}
		}

	}

}
