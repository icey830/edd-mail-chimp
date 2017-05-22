<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Settings {

	public function __construct() {
		add_filter( 'edd_settings_sections_extensions', array( $this, 'subsection' ), 10, 1 );
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );
		add_filter( 'edd_settings_extensions_sanitize', array( $this, 'save_settings' ) );

		add_action( 'edd_settings_tab_bottom_extensions_mailchimp', array( $this, 'connected_lists' ) );
		add_action( 'admin_notices', array($this, 'display_sync_notice') );
		add_action( 'admin_notices', array($this, 'display_disconnected_notice') );
	}

	/**
	 * Register our subsection for EDD 2.5
	 *
	 * @since  2.5.6
	 * @param  array $sections The subsections
	 * @return array           The subsections with MailChimp added
	 */
	public function subsection( $sections ) {
		$sections['mailchimp'] = __( 'MailChimp', 'eddmc' );
		return $sections;
	}

	/**
	 * Registers the plugin settings
	 */
	public function settings( $settings ) {

		$eddmc_settings = array(
			array(
				'id'      => 'eddmc_settings',
				'name'    => '<strong>' . __( 'MailChimp Settings', 'eddmc' ) . '</strong>',
				'desc'    => __( 'Configure MailChimp Integration Settings', 'eddmc' ),
				'type'    => 'header'
			),
			array(
				'id'      => 'eddmc_api',
				'name'    => __( 'MailChimp API Key', 'eddmc' ),
				'desc'    => __( 'Enter your MailChimp API key', 'eddmc' ),
				'type'    => 'text',
				'size'    => 'regular'
			),
			array(
				'id'      => 'eddmc_show_checkout_signup',
				'name'    => __( 'Show Signup on Checkout', 'eddmc' ),
				'desc'    => __( 'Allow customers to signup for the default list selected below during checkout?', 'eddmc' ),
				'type'    => 'checkbox'
			),
			array(
				'id'      => 'eddmc_checkout_signup_default_value',
				'name'    => __( 'Signup Checked by Default', 'eddmc' ),
				'desc'    => __( 'Should the newsletter signup checkbox shown during checkout be checked by default?', 'eddmc' ),
				'type'    => 'checkbox'
			),
			array(
				'id'      => 'eddmc_label',
				'name'    => __( 'Checkout Label', 'eddmc' ),
				'desc'    => __( 'This is the text shown next to the signup option', 'eddmc' ),
				'type'    => 'text',
				'size'    => 'regular'
			),
			array(
				'id'      => 'eddmc_double_opt_in',
				'name'    => __( 'Double Opt-In', 'eddmc' ),
				'desc'    => __( 'When checked, users will be sent a confirmation email after signing up, and will only be added once they have confirmed the subscription.', 'eddmc' ),
				'type'    => 'checkbox'
			),
			array(
				'id'      => 'eddmc_replace_interests',
				'name'    => __( 'Replace Interests', 'eddmc' ),
				'desc'    => __( 'When checked, a MailChimp subscriber\'s interests will be replaced during new purchases rather than merged.', 'eddmc' ),
				'type'    => 'checkbox'
			)
		);

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$eddmc_settings = array( 'mailchimp' => $eddmc_settings );
		}

		return array_merge( $settings, $eddmc_settings );
	}


	/**
	 * Handle saving settings
	 *
	 * @param  array $input POST settings
	 * @return array $input modified input, if any
	 */
	public function save_settings( $input ) {

		global $wpdb;

		if ( isset( $input['eddmc_default_list'] ) ) {
			$id = sanitize_key( $input['eddmc_default_list'] );

			$wpdb->update(
				$wpdb->edd_mailchimp_lists,
				array(
					'is_default' => '0'
				),
				array( 'is_default' => 1 ),
				array( '%d'),
				array( '%d' )
			);

			$wpdb->update(
				$wpdb->edd_mailchimp_lists,
				array(
					'is_default' => '1'
				),
				array( 'remote_id' => $id ),
				array( '%d' ),
				array( '%s' )
			);
		}

		if ( isset( $input['eddmc_connect_lists'] ) && ! empty( $input['eddmc_connect_lists'] ) ) {
			foreach ( $input['eddmc_connect_lists'] as $list_id ) {
				$id = sanitize_key( $list_id );

				$list = new EDD_MailChimp_List( $id );

				if ( $list->exists() ) {
					$response = $list->api->getLastResponse();
					$response = json_decode( $response['body'] );

					// Ensure it doesn't exist locally
					if ( $list->is_connected() ) {
						continue;
					}

					$list->name = $response->name;

					// Determine if list should be set as default
					// based on if another default list already exists.
					$check = EDD_MailChimp_List::get_default();
					$is_default = $check !== null ? false : true;

					// Insert as new connected list
					$list->connect( $is_default );

					// Find or Create a MailChimp Store for this list and fire up a full sync job
					$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
					$store->sync();
				}
			}
		}

		return $input;
	}

	/**
	 * Show a notice to the user when a list has been queued for sync.
	 *
	 * @return string | void
	 */
	public function display_sync_notice() {
		if ( ! isset( $_GET['edd_mailchimp_list_queued'] ) ) {
			return;
		}

		ob_start();
		?>
		    <div class="notice notice-success">
		        <p><?php _e( 'Your MailChimp list has been queued for syncing.', 'eddmc' ); ?></p>
		    </div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Show a notice to the user when a list has been disconnected.
	 *
	 * @return string | void
	 */
	public function display_disconnected_notice() {
		if ( ! isset( $_GET['edd_mailchimp_list_disconnected'] ) ) {
			return;
		}

		ob_start();
		?>
		    <div class="notice notice-success">
		        <p><?php _e( 'Your MailChimp list has been disconnected from your Easy Digital Downloads store.', 'eddmc' ); ?></p>
		    </div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Add our custom connected lists setting below.
	 *
	 * @return void
	 */
	public function connected_lists() {
		$key = edd_get_option('eddmc_api', false);

		if ( ! $key ) {
		  return;
		}

		$connected_list_ids = array();
		$lists = EDD_MailChimp_List::connected();
		?>

		<h2><?php _e('Connected Lists', 'eddmc'); ?></h2>

		<?php if ( empty( $lists ) ) : ?>
			<p><?php _e('There are currently no MailChimp lists connected to Easy Digital Downloads.', 'eddmc'); ?></p>
		<?php else: ?>

			<script>
				(function($){
					$(document).ready(function(){
						$('.edd-mailchimp-disconnect-list').click(function() {
							if ( ! confirm("<?php _e('Are you sure you want to disconnect this list?', 'eddmc');?>" ) ) {
								return false;
							}
						});
					});
				})(jQuery);
			</script>

			<p><?php _e('These are the MailChimp lists that are currently connected to Easy Digital Downloads.', 'eddmc'); ?></p>
			<table class="is-edd-mailchimp-table is-edd-mailchimp-connected-lists-table form-table wp-list-table widefat fixed posts">
				<thead>
					<tr>
						<th><?php _e('Default', 'eddmc'); ?></th>
						<th><?php _e('Connected List Name', 'eddmc'); ?></th>
						<th><?php _e('Status', 'eddmc'); ?></th>
						<th><?php _e('Actions', 'eddmc'); ?></th>
					</tr>
				</thead>
			<?php foreach ($lists as $list): ?>
				<?php
					$force_list_sync_url = add_query_arg( array(
						'settings-updated' => false,
						'tab'              => 'extensions',
						'edd-action' => 'mailchimp_force_list_sync',
						'mailchimp_list_remote_id' => $list->remote_id,
					) );

					// Remove the section from the tabs so we always end up at the main section
					$force_list_sync_url = remove_query_arg( 'section', $force_list_sync_url );

					$disconnect_list_url = add_query_arg( array(
						'settings-updated' => false,
						'tab'              => 'extensions',
						'edd-action' => 'mailchimp_disconnect_list',
						'mailchimp_list_remote_id' => $list->remote_id,
					) );

					// Remove the section from the tabs so we always end up at the main section
					$disconnect_list_url = remove_query_arg( 'section', $disconnect_list_url );
				?>
				<?php $connected_list_ids[] = $list->remote_id; ?>
				<tr>
					<td>
						<input type="radio" name="edd_settings[eddmc_default_list]" value="<?php esc_attr_e($list->remote_id); ?>" <?php checked( $list->is_default, 1 ); ?>  />
					</td>
					<td>
						<span class="is-mailchimp-list-name">
							<?php echo $list->name; ?>
						</span>
						<span class="is-mailchimp-list-id">ID: <?php echo $list->remote_id; ?></span>
					</td>

					<td>
						<?php echo $list->sync_status; ?>
						<span class="is-last-sync-date">
							<strong><?php _e('Last Synced', 'eddmc'); ?>:</strong>
							<?php
								if ( $list->synced_at == '0000-00-00 00:00:00') {
									_e('Never', 'eddmc');
								} else {
									echo date( 'F jS, Y \a\t g:iA', strtotime( $list->synced_at ) );
								}
							?>
						</span>
					</td>

					<td>
						<a href="<?php echo esc_url($force_list_sync_url); ?>"><?php _e('Force Sync Now', 'eddmc'); ?></a> |
						<a class="edd-mailchimp-disconnect-list" style="color: red;" href="<?php echo esc_url($disconnect_list_url); ?>"><?php _e('Disconnect', 'eddmc'); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		<?php endif; ?>

		<?php
			try {
				$result = EDD_MailChimp_List::all();
			} catch (Exception $e) {
				_e('Please supply a valid MailChimp API key.', 'eddmc');
				return;
			}
		?>

		<h2><?php _e('Available Lists', 'eddmc'); ?></h2>
		<p><?php _e('Select the checkbox next to the MailChimp lists that you would like to connect to Easy Digital Downloads.', 'eddmc'); ?></p>

		<div class="is-edd-mailchimp-table-container">
			<table class="is-edd-mailchimp-table is-edd-mailchimp-lists-table form-table wp-list-table widefat fixed posts">
				<thead>
					<tr>
						<th><?php _e('MailChimp List Name', 'eddmc'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
			<?php
			

			if ( isset( $result['lists'] ) && ! empty( $result['lists'] ) ) :

				foreach ( $result['lists'] as $list ) :

					if ( in_array( $list['id'], $connected_list_ids ) ) {
						continue;
					}

					?>

					<tr>
						<td>
							<input type="checkbox" name="edd_settings[eddmc_connect_lists][]" value="<?php esc_attr_e($list['id']); ?>" />
						</td>
						<td>
							<span class="is-mailchimp-list-name"><?php echo $list['name']; ?></span>
							<span class="is-mailchimp-list-id">ID: <?php echo $list['id']; ?></span>
						</td>
					</tr>

			<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
