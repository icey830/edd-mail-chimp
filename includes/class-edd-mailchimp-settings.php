<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Settings {

  public function __construct() {
    add_filter( 'edd_settings_sections_extensions', array( $this, 'subsection' ), 10, 1 );
    add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );
    add_filter( 'edd_settings_extensions_sanitize', array( $this, 'save_settings' ) );
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
      // array(
      //   'id'      => 'eddmc_list',
      //   'name'    => __( 'Choose a list', 'eddmc'),
      //   'desc'    => __( 'Select the list you wish to subscribe buyers to', 'eddmc' ),
      //   'type'    => 'select',
      //   'options' => $this->_get_lists()
      // ),
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
   * Flush the list transient on save
   */
  public function save_settings( $input ) {
    if ( isset( $input['eddmc_api'] ) ) {
      delete_transient( 'edd_mailchimp_list_data' );
    }
    return $input;
  }
}
