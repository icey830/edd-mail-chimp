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

		?>
			<script>
				(function($){
					$(document).ready(function() {

						var unchecked = $('.edd-mailchimp-list:not(:checked)');

						unchecked.map(function(i) {
							var list_id = this.value;
							var $interests = $('[data-mailchimp-list="' + list_id + '"]');

							if ( $interests.length > 0 ) {
								$interests.map( function(index) {
									$(this).find('input').prop('checked', false);
								});
								$interests.fadeOut();
							}
						});

						$('.edd-mailchimp-list').change(function() {
							var list_id = this.value;
							var $interests = $('[data-mailchimp-list="' + list_id + '"]');

							if ( $interests.length > 0 ) {
								if(this.checked) {
									$interests.fadeIn();
								} else {
									$interests.map( function(index) {
										$(this).find('input').prop('checked', false);
									});
									$interests.fadeOut();
								}
							}
						});
					});
				})(jQuery)
			</script>
		<?php

		$connected_lists = EDD_MailChimp_List::connected();
		$download = new EDD_MailChimp_Download( (int) $post->ID );
		$preferences = $download->subscription_preferences();

		$preferred_list_ids = array();
		$preferred_interest_ids = array();

		foreach ( $preferences as $list ) {
			$preferred_list_ids[] = $list['id'];

			if ( ! empty( $list['interests'] ) ) {
				foreach( $list['interests'] as $interest ) {
					$preferred_interest_ids[] = $interest['id'];
				}
			}
		}

		if ( ! empty( $connected_lists ) ) {
			echo '<p>' . __( 'Select the lists you wish buyers to be subscribed to when purchasing.', 'eddmc' ) . '</p>';

			foreach( $connected_lists as $list ) {
				$list = new EDD_MailChimp_List( $list->remote_id );

				echo '<label>';
					echo '<input class="edd-mailchimp-list" type="checkbox" name="edd_mailchimp_lists[]" value="' . esc_attr( $list->id ) . '"' . checked( true, in_array( $list->id, $preferred_list_ids ), false ) . '>';
					echo '&nbsp;' . $list->name;
				echo '</label><br/>';

				$interests = $list->interests();

				if ( ! empty( $interests ) ) {
					foreach ( $interests as $interest ){
						echo '<label data-mailchimp-list="'. esc_attr($list->id) .'">';
							echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="edd_mailchimp_interests[]" value="' . esc_attr( $interest->id ) . '"' . checked( true, in_array( $interest->id, $preferred_interest_ids ), false ) . '>';
							echo '&nbsp;' . $interest->interest_category_name . ': ' . $interest->interest_name;
							echo '<br/>';
						echo '</label>';
					}
				}
			}
		} else {
			echo '<p>' . __( 'Please visit the EDD MailChimp settings page to connect your MailChimp lists to your EDD site.', 'eddmc' ) . '</p>';
		}

		echo '<hr style="margin: 20px 0;" />';

		$double_opt_in = get_post_meta( $post->ID, 'edd_mailchimp_double_opt_in', true );

		echo '<p>' . __('Check this box if purchasers of this product should be required to confirm their subscription to your MailChimp list.', 'eddmc') . '</p>';
		echo '<label>';
		echo '<input type="checkbox" name="edd_mailchimp_double_opt_in" value="1"' . checked( true, !empty($double_opt_in), false ) . '>&nbsp;';
		_e('Require Double Opt-In', 'eddmc');
		echo '</label>';
	}

	/**
	 * Save metabox data
	 *
	 * @param  int $post_id  Download ID
	 * @param  WP_Post $post
	 * @return [type]          [description]
	 */
	public function save_metabox( $post_id, $post ) {
		$download = new EDD_MailChimp_Download( $post_id );
		$download->clear_subscription_preferences();

		if ( isset( $_POST['edd_mailchimp_lists'] ) && ! empty( $_POST['edd_mailchimp_lists'] ) ) {

			foreach ( $_POST['edd_mailchimp_lists'] as $list_id ) {
				$list_id = absint( $list_id );
				$download->add_preferred_list( $list_id );
			}

			if ( isset( $_POST['edd_mailchimp_interests'] ) && ! empty( $_POST['edd_mailchimp_interests'] ) ) {
				foreach ( $_POST['edd_mailchimp_interests'] as $interest_id ) {
					$interest_id = absint( $interest_id );
					$download->add_preferred_interest( $interest_id );
				}
			}
		}

		delete_post_meta($post_id, 'edd_mailchimp_double_opt_in');

		if ( isset( $_POST['edd_mailchimp_double_opt_in'] ) && $_POST['edd_mailchimp_double_opt_in'] == 1 ) {
			add_post_meta( $post_id, 'edd_mailchimp_double_opt_in', true );
		}
	}

}
