<?php
/**
 * EDD MailChimp class
 *
 * @copyright   Copyright (c) 2016, Pippin Williamson, Dave Kiss
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
*/

use \DrewM\MailChimp\MailChimp;

class EDD_MailChimp {

	public $api;

	/**
	 * Text shown on the checkout, if none is set in the settings
	 */
	public $checkout_label;

	/**
	 * Class constructor
	 */
	public function __construct() {

		$label = edd_get_option('eddmc_label');

		if( ! empty( $label ) ) {
			$this->checkout_label = trim( $label );
		} else {
			$this->checkout_label = __( 'Signup for the newsletter', 'eddmc' );
		}

		$key = edd_get_option('eddmc_api');

		if ( $key ) {
			$this->api = new MailChimp( trim( $key ) );
		}

		add_action( 'init', array( $this, 'textdomain' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_filter( 'edd_metabox_fields_save', array( $this, 'save_metabox' ) );
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );
		add_action( 'edd_purchase_form_before_submit', array( $this, 'checkout_fields' ), 100 );
		add_action( 'edd_checkout_before_gateway', array( $this, 'checkout_signup' ), 10, 3 );
		add_action( 'edd_complete_download_purchase', array( $this, 'completed_download_purchase_signup' ), 10, 3 );

		add_filter( 'edd_settings_sections_extensions', array( $this, 'subsection' ), 10, 1 );
		add_filter( 'edd_settings_extensions_sanitize', array( $this, 'save_settings' ) );
	}

	/**
	 * Load the plugin's textdomain
	 */
	public function textdomain() {
		load_plugin_textdomain( 'eddmc', false, EDD_MAILCHIMP_PATH . '/languages/' );
	}

	/**
	 * Output the signup checkbox on the checkout screen, if enabled
	 */
	public function checkout_fields() {
		if( ! $this->show_checkout_signup() ) {
			return;
		}

		ob_start(); ?>
		<fieldset id="edd_mailchimp">
			<p>
				<input name="edd_mailchimp_signup" id="edd_mailchimp_signup" type="checkbox" checked="checked"/>
				<label for="edd_mailchimp_signup"><?php echo $this->checkout_label; ?></label>
			</p>
		</fieldset>
		<?php
		echo ob_get_clean();
	}


	/**
	 * Check if a customer needs to be subscribed at checkout
	 */
	public function checkout_signup( $posted, $user_info, $valid_data ) {

		// Check for global newsletter
		if( isset( $posted['edd_mailchimp_signup'] ) ) {
			$this->subscribe_email( $user_info );
		}
	}


	/**
	 * Check if a customer needs to be subscribed on completed purchase of specific products
	 */
	public function completed_download_purchase_signup( $download_id = 0, $payment_id = 0, $download_type = 'default' ) {

		$user_info = edd_get_payment_meta_user_info( $payment_id );
		$lists     = get_post_meta( $download_id, '_edd_mailchimp', true );

		if( 'bundle' == $download_type ) {

			// Get the lists of all items included in the bundle

			$downloads = edd_get_bundled_products( $download_id );
			if( $downloads ) {
				foreach( $downloads as $d_id ) {
					$d_lists = get_post_meta( $d_id, '_edd_mailchimp', true );
					if ( is_array( $d_lists ) ) {
						$lists = array_merge( $d_lists, (array) $lists );
					}
				}
			}
		}

		if( empty( $lists ) ) {
			return;
		}

		$lists = array_unique( $lists );

		foreach( $lists as $list ) {
			$this->subscribe_email( $user_info, $list );
		}
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
	 * Retrieve the MailChimp lists
	 *
	 * Must return an array like this:
	 *   array(
	 *     'some_id'  => 'value1',
	 *     'other_id' => 'value2'
	 *   )
	 */
	public function get_lists() {
		$lists = array();

		$list_data = get_transient( 'edd_mailchimp_list_data' );

		if( false === $list_data && ! empty( $this->api ) ) {
			$list_data = $this->api->get('lists', array( 'count' => 100 ) );
			set_transient( 'edd_mailchimp_list_data', $list_data, 24*24*24 );
		}

		if( ! empty( $list_data ) ) {
			foreach( $list_data['lists'] as $key => $list ) {
				$lists[ $list['id'] ] = $list['name'];
			}
		}

		return $lists;
	}


	/**
	* Retrieve the list of categories associated with a list id
	*
	* @param  string $list_id       List id for which categories should be returned
	* @return array  $category_data Data about the category
	*/
	public function get_interests( $list_id = '' ) {

		global $post;

		$settings = (array) get_post_meta( $post->ID, '_edd_mailchimp', true );

		if( ! empty( $this->api ) ) {

			// Uncomment for testing only:
			// delete_transient('edd_mailchimp_interest_categories_' . $list_id);

			$interest_categories = get_transient( 'edd_mailchimp_interest_categories_' . $list_id );

			if( false === $interest_categories ) {
				$interest_categories = $this->api->get( "lists/$list_id/interest-categories" );
				set_transient( 'edd_mailchimp_interest_categories_' . $list_id, $interest_categories, 24*24*24 );
			}

			$category_data = array();

			if( $interest_categories && ! empty( $interest_categories['categories'] ) ) {

				foreach( $interest_categories['categories'] as $category ) {

					$category_id   = $category['id'];
					$category_name = $category['title'];

					$interests = $this->api->get( "lists/$list_id/interest-categories/$category_id/interests" );

					if ( $interests && ! empty( $interests['interests'] ) ) {
						foreach ( $interests['interests'] as $interest ) {
							$interest_id   = $interest['id'];
							$interest_name = $interest['name'];
						}
					}

					$category_data["$list_id|$interest_id"] = $category_name . ' - ' . $interest_name;
				}
			}
		}

		return $category_data;
	}


	/**
	 * Display the metabox, which is a list of newsletter lists
	 */
	public function render_metabox() {

		global $post;

		echo '<p>' . __( 'Select the lists you wish buyers to be subscribed to when purchasing.', 'eddmc' ) . '</p>';

		$checked = (array) get_post_meta( $post->ID, '_edd_mailchimp', true );

		foreach( $this->get_lists() as $list_id => $list_name ) {
			echo '<label>';
				echo '<input type="checkbox" name="_edd_mailchimp' . '[]" value="' . esc_attr( $list_id ) . '"' . checked( true, in_array( $list_id, $checked ), false ) . '>';
				echo '&nbsp;' . $list_name;
			echo '</label><br/>';

			$interests = $this->get_interests( $list_id );
			if( ! empty( $interests ) ) {
				foreach ( $interests as $group_id => $group_name ){
					echo '<label>';
						echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="_edd_mailchimp_interests["'. $list_id .'"]" value="' . esc_attr( $group_id ) . '"' . checked( true, in_array( $group_id, $checked ), false ) . '>';
						echo '&nbsp;' . $group_name;
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
		$fields[] = '_edd_mailchimp_interests';
		return $fields;
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
				'id'      => 'eddmc_list',
				'name'    => __( 'Choose a list', 'edda'),
				'desc'    => __( 'Select the list you wish to subscribe buyers to', 'eddmc' ),
				'type'    => 'select',
				'options' => $this->get_lists()
			),
			array(
				'id'      => 'eddmc_label',
				'name'    => __( 'Checkout Label', 'eddmc' ),
				'desc'    => __( 'This is the text shown next to the signup option', 'eddmc' ),
				'type'    => 'text',
				'size'    => 'regular'
			),
		);

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$eddmc_settings = array( 'mailchimp' => $eddmc_settings );
		}

		return array_merge( $settings, $eddmc_settings );
	}

	/**
	 * Flush the list transient on save
	 */
	public function save_settings( $input ) {
		if( isset( $input['eddmc_api'] ) ) {
			delete_transient( 'edd_mailchimp_list_data' );
		}
		return $input;
	}

	/**
	 * Determines if the checkout signup option should be displayed
	 */
	public function show_checkout_signup() {
		$show = edd_get_option('eddmc_show_checkout_signup');
		return ! empty( $show );
	}

	/**
	 * Subscribe an email to a list
	 *
	 * @param  array   $user_info       Customer data containing the user ID, email, first name, and last name
	 * @param  boolean $list_id         MailChimp List ID to subscribe the user to
	 * @param  boolean $opt_in_override false (deprecated)
	 * @return boolean                  Was the customer subscribed?
	 */
	public function subscribe_email( $user_info = array(), $list_id = false, $opt_in_override = false ) {

		// Make sure an API key has been entered
		if( empty( $this->api ) ) {
			return false;
		}

		// Retrieve the global list ID if none is provided
		if( ! $list_id ) {
			$global_list_id = edd_get_option('eddmc_list');
			$list_id = ! empty( $global_list_id ) ? $global_list_id : false;
			if( ! $list_id ) {
				return false;
			}
		}

		$merge_fields = array( 'FNAME' => $user_info['first_name'], 'LNAME' => $user_info['last_name'] );

		// todo: get interests for this list here
		//
		// $interests = array( 'unique_interest_id' => true );
		$result = $this->api->post("lists/$list_id/members", apply_filters( 'edd_mc_subscribe_vars', array(
			'email_address' => $user_info['email'],
			'status'        => 'subscribed',
			'merge_fields'  => $merge_fields,
			'interests'     => $interests,
		) ) );

		if( $result ) {
			return true;
		}

		return false;
	}
}
