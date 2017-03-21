<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Settings {

	public function __construct() {
		add_filter( 'edd_settings_sections_extensions', array( $this, 'subsection' ), 10, 1 );
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );
		add_filter( 'edd_settings_extensions_sanitize', array( $this, 'save_settings' ) );

		add_action( 'edd_settings_tab_bottom_extensions_mailchimp', array( $this, 'connected_lists' ) );
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
				'desc'    => __( 'Allow customers to signup for the list selected below during checkout?', 'eddmc' ),
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
					$list = json_decode( $response['body'] );

					// Ensure it doesn't exist locally
					if ( $list->is_connected() ) {
						continue;
					}

					// Determine if list should be set as default
					// based on if another default list already exists.
					$check = EDD_MailChimp_List::default();
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
						<a href="#"><?php _e('Force Sync Now', 'eddmc'); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		<?php endif; ?>


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
			$result = EDD_MailChimp_List::all();

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

			<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
