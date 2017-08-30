<?php
/**
 * Upgrade database to v3 class.
 *
 * @copyright   Copyright (c) 2017, Dave Kiss
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       2.6
*/

class EDD_MailChimp_V3_Upgrade {

	private $interest_categories;

	/**
	 * EDD Products to check for MailChimp Settings
	 *
	 * @var array
	 */
	private $products;


	/**
	 * Current step in the upgrade routine
	 *
	 * @var int
	 */
	private $step;

	/**
	 * Number of EDD Products to process
	 *
	 * @var int
	 */
	private $total_products;

	/**
	 * Sets up the checkout label
	 */
	public function __construct() {
		add_action( 'admin_notices', array($this, 'show_upgrade_notices') );
		add_action( 'edd_upgrade_mailchimp_api3', array($this, 'upgrade_to_api3') );
		add_action( 'edd_upgrade_mailchimp_api3_groupings', array($this, 'convert_grouping_data') );
	}


	/**
	 * Add an upgrade notice if the user is on an old version
	 *
	 * @since  2.6
	 * @return [type] [description]
	 */
	public function show_upgrade_notices() {
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'edd-upgrades' ) {
			return; // Don't show notices on the upgrades page
		}

		if ( ! edd_has_upgrade_completed( 'upgrade_mailchimp_api3' ) ) {
			printf(
				'<div class="updated"><p>' . __( 'Easy Digital Downloads needs to upgrade your MailChimp settings, click <a href="%s">here</a> to start the upgrade.', 'eddmc' ) . '</p></div>',
				esc_url( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=upgrade_mailchimp_api3' ) )
			);
		}
	}

	/**
	 * Init the upgrade process to API3
	 *
	 * @return void
	 */
	public function upgrade_to_api3() {

		// Make sure the Global list has been connected first.
		if ( ! edd_has_upgrade_completed( 'upgrade_mailchimp_api3_default_list' ) ) {
			$this->connect_global_list();
		}

		$next_upgrade_url = admin_url( 'index.php?page=edd-upgrades&edd-upgrade=upgrade_mailchimp_api3_groupings' );
		self::redirect( $next_upgrade_url );
	}

	/**
	 * Move the user's global MailChimp list to a new connected list record
	 * and peform a full sync on the corresponding store.
	 *
	 * @return [type] [description]
	 */
	public function connect_global_list() {
		self::authorize_action();
		self::authenticate_action();
		self::configure_php_settings();

		$list_id = edd_get_option('eddmc_list');

		if ( $list_id ) {
			$list = new EDD_MailChimp_List( $list_id );

			if ( ! $list->is_connected() && $list->exists() ) {
				$response = $list->api->getLastResponse();
				$record = json_decode( $response['body'], true );

				$list->name = $record['name'];

				$mark_as_default = true;
				$list->connect( $mark_as_default );

				// Find or Create a MailChimp Store for this list and fire up a full sync job
				$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
				$store->sync();
			}

			edd_delete_option( 'eddmc_list' );
		}

		// Mark this one as done.
		edd_set_upgrade_complete( 'upgrade_mailchimp_api3_default_list' );
	}


	/**
	 * Convert saved grouping data preferences to the new format
	 * that MailChimp requires for API calls.
	 *
	 * @return [type] [description]
	 */
	public function convert_grouping_data() {
		self::authorize_action();
		self::authenticate_action();
		self::configure_php_settings();

		global $wpdb;

		$this->set_step();
		$this->products();
		$this->total_products();

		if ( ! empty( $this->products->posts ) ) {
			foreach( $this->products->posts as $product ) {

				$settings = get_post_meta( $product->ID, '_edd_mailchimp', true );

				if ( empty( $settings ) ) {
					continue;
				}

				$download = new EDD_MailChimp_Download( (int) $product->ID );

				foreach ( (array) $settings as $index => $list ) {

					// Account for weird empty list values
					if ( empty( $list ) ) {
						continue;
					}

					if ( strpos( $list, '|' ) != false ) {

						// This is an old style setting for MailChimp API v2,
						// so we need to convert here. $list may look like any
						// of the following entries:
						//
						// array (size=5)
						//   0 => string '097846e40a' (length=10)
						//   1 => string '097846e40a|18033|Donating' (length=25)
						//   2 => string '097846e40a|18033|Events' (length=23)
						//   3 => string '097846e40a|19129|Invisible 3' (length=28)
						//   4 => string '33e84889b3' (length=10)
						//
						// Since MailChimp doesn't allow looking up interest categories by the
						// group ID that we previously stored, we need to fetch them all and
						// store the Interest ID if the stored name matches the interest name.
						//
						// Our old format is as follows:
						// $groups_data["$list_id|$grouping_id|$group_name"] = $grouping_name . ' - ' . $group_name;
						//
						// Grouping ID => Interest Category ID
						// Group Name  => Interest name
						$parts          = explode( '|', $list );
						$list_id        = $parts[0];
						$interest_name  = $parts[2];

						// Connect the list locally and begin sync if not exists.
						$list = new EDD_MailChimp_List( $list_id );

						if ( ! $list->is_connected() && $list->exists ) {
							$response = $list->api->getLastResponse();
							$record = json_decode( $response['body'], true );

							$list->name = $record['name'];
							$list->connect();

							// Also find/create the MailChimp Store
							$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );

							// @todo Make sure multiple store sync jobs can be queued at the same time.
							$store->sync();
						}

						$interests = $list->interests();

						// Compare the interest name to the stored group name
						// If they are the same, migrate that over to the new post meta.
						foreach ( $interests as $interest ) {
							if ( strtolower( $interest->interest_name ) === strtolower( $interest_name ) ) {
								$download->add_preferred_interest( (int) $interest->id );
							}
						}

					} else {

						// Connect the list locally and begin sync if not exists.
						$list = new EDD_MailChimp_List( $list );

						if ( ! $list->is_connected() && $list->exists() ) {
							$response = $list->api->getLastResponse();
							$record = json_decode( $response['body'], true );

							$list->name = $record['name'];
							$list->connect();

							$store = EDD_MailChimp_Store::find_or_create( $list->remote_id );
							$store->sync();
						}

					}

					delete_transient( 'edd_mailchimp_groupings_' . $list->remote_id);

					// Assign this list as a download-specific preference.
					$download->add_preferred_list( (int) $list->id );
				}

			}

			$this->step++;
			$redirect = add_query_arg( array(
				'page'        => 'edd-upgrades',
				'edd-upgrade' => 'upgrade_mailchimp_api3_groupings',
				'step'        => $this->step,
				'total'       => $this->total_products
			), admin_url( 'index.php' ) );

			self::redirect( $redirect );
		} else {

			// No more products found, finish up
			delete_transient( 'edd_mailchimp_list_data' );
			self::mark_as_complete();
			self::redirect( admin_url() );
		}
	}

	/**
	 * Prevent the upgrade if the user does not have the proper permissions
	 * to manage shop settings.
	 *
	 * @return bool
	 */
	private function authorize_action() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( __( 'You do not have permission to do shop upgrades', 'eddmc' ), __( 'Error', 'eddmc' ), array( 'response' => 403 ) );
		}

		return true;
	}


	/**
	 * Ensure the user has an API key set in order to perform the upgrade
	 *
	 * @return bool
	 */
	private function authenticate_action() {
		$key = edd_get_option('eddmc_api');

		if ( empty( $key ) ) {
			wp_die( __( 'Please make sure to set your MailChimp API key on the Easy Digital Downloads extension settings page and try running this upgrade again.', 'eddmc' ), __( 'Error', 'eddmc' ), array( 'response' => 403 ) );
		}

		$this->key = trim( $key );
		return true;
	}

	/**
	 * Set up PHP so as to prevent timeouts or early exits
	 *
	 * @return void
	 */
	private function configure_php_settings() {
		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit(0);
		}
	}

	/**
	 * Set the current step in upgrade routine
	 *
	 * @return class
	 */
	private function set_step() {
		$this->step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		return $this;
	}

	/**
	 * Get all of the EDD Products that we will use for this conversion.
	 *
	 * @return class
	 */
	private function products() {
		$products = new WP_Query( array(
			'post_type'        => 'download',
			'posts_per_page'   => 20,
			'paged'            => $this->step
		) );

		$this->products = $products;
		return $this;
	}

	/**
	 * Number of products that need to be processed.
	 *
	 * @return class
	 */
	private function total_products() {
		if ( isset( $_GET['total'] ) && absint( $_GET['total'] ) > 0) {
			$this->total_products = absint( $_GET['total'] );
		} else {
			$this->total_products = $this->products->found_posts;
		}
		return $this;
	}


	/**
	 * Mark the upgrade routine as completed.
	 *
	 * @return void
	 */
	private static function mark_as_complete() {
		edd_set_upgrade_complete( 'upgrade_mailchimp_api3' );
		delete_site_option( 'edd_doing_upgrade' );
	}


	/**
	 * Redirect the user to the appropriate page based on the next
	 * step in the upgrade routine.
	 *
	 * @param  string $url redirect url location
	 * @return void
	 */
	private static function redirect( $url ) {
		wp_redirect( $url ); exit;
	}
}
