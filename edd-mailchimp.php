<?php
/*
Plugin Name: Easy Digital Downloads - MailChimp
Plugin URL: http://easydigitaldownloads.com/extension/mail-chimp
Description: Include a MailChimp signup option with your Easy Digital Downloads checkout
Version: 2.5.6
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: Pippin Williamson, Dave Kiss
*/

if ( version_compare( PHP_VERSION, '5.3.3', '<' ) ) {
  add_action( 'admin_notices', 'eddmc_below_php_version_notice' );
  function eddmc_below_php_version_notice() {
    echo '<div class="error"><p>' . __( 'Your version of PHP is below the minimum version of PHP required by Easy Digital Downloads - MailChimp. Please contact your host and request that your version be upgraded to 5.3.3 or later.', 'eddmc' ) . '</p></div>';
  }
  return;
}

define( 'EDD_MAILCHIMP_PRODUCT_NAME', 'Mail Chimp' );
define( 'EDD_MAILCHIMP_PATH', dirname( __FILE__ ) );
define( 'EDD_MAILCHIMP_VERSION', '2.5.6' );

class EDD_MailChimp_Extension {

  private static $instance;

  /**
   * Setup the instance using the correct class
   *
   * @return [type] [description]
   */
  public function instance() {
    if ( ! isset( self::$instance ) AND ! ( self::$instance instanceof EDD_MailChimp ) ) {
      self::$instance = new self;
      self::$instance->_include_files();

      if ( class_exists( 'EDD_License' ) && is_admin() ) {
        $eddmc_license = new EDD_License( __FILE__, EDD_MAILCHIMP_PRODUCT_NAME, EDD_MAILCHIMP_VERSION, 'Pippin Williamson' );
      }
    }

    return self::$instance;
  }


  public function __construct() {
    add_action('plugins_loaded', array( $this, 'init') );
  }


  /**
   * Load either the API2 or API3 classes, depending on if
   * the user has run the upgrade routine.
   *
   * @return void
   */
  public function init() {

    if ( class_exists('Easy_Digital_Downloads') && function_exists('edd_has_upgrade_completed') ) {

      // DEV ONLY
      // $completed_upgrades = get_option( 'edd_completed_upgrades' );

      // foreach ($completed_upgrades as $i => $value) {
      //   if ($value == 'upgrade_mailchimp_groupings_settings') {
      //     unset($completed_upgrades[$i]);
      //   }
      // }

      // update_option( 'edd_completed_upgrades', $completed_upgrades );
      // DEV ONLY

      if ( edd_has_upgrade_completed( 'upgrade_mailchimp_groupings_settings' ) ) {
        // Use the new MailChimp class
        if( ! class_exists( 'EDD_MailChimp' ) ) {
          include( EDD_MAILCHIMP_PATH . '/includes/class-edd-mailchimp.php' );
          $GLOBALS['eddmc'] = new EDD_MailChimp;
        }

      } else {
        // Load API2
        // Require upgrade routine
        if( ! class_exists( 'EDD_MailChimp_V3_Upgrade' ) ) {
          include( EDD_MAILCHIMP_PATH . '/includes/class-edd-mailchimp-v3-upgrade.php' );
          new EDD_MailChimp_V3_Upgrade;
        }

        // Require deprecated classes
        if( ! class_exists( 'EDD_Newsletter' ) ) {
          include( EDD_MAILCHIMP_PATH . '/includes/deprecated/class-edd-newsletter.php' );
        }

        if( ! class_exists( 'EDD_MailChimp' ) ) {
          include( EDD_MAILCHIMP_PATH . '/includes/deprecated/class-edd-mailchimp.php' );
          $GLOBALS['eddmc'] = new EDD_MailChimp('mailchimp', 'MailChimp');
        }
      }
    }
  }


  /**
   * Include files required by the MailChimp extension.
   *
   * @return void
   */
  private function _include_files() {
    // Require the drewm/mailchimp-api wrapper lib
    require('vendor/autoload.php');

    // Require deprecated API library while we transition
    // everything over to API3
    if( ! class_exists( 'EDD_MailChimp_API' ) ) {
      include( EDD_MAILCHIMP_PATH . '/includes/deprecated/class-edd-mailchimp-api.php' );
    }

    if( ! class_exists( 'EDD_MC_Ecommerce_360' ) ) {
      include( EDD_MAILCHIMP_PATH . '/includes/class-edd-ecommerce360.php' );
    }

    if ( ! class_exists( 'EDD_MC_Tools' ) && class_exists( 'Easy_Digital_Downloads' ) ) {
      if ( ( defined ( 'EDD_VERSION' ) && version_compare( EDD_VERSION, '2.4.2', '>=' ) ) && is_admin() ) {
        include( EDD_MAILCHIMP_PATH . '/includes/class-edd-mailchimp-tools.php' );
      }
    }

    $edd_mc360 = new EDD_MC_Ecommerce_360;

    if ( class_exists( 'EDD_MC_Tools' ) ) {
      $edd_mc_tools = new EDD_MC_Tools();
    }
  }
}

EDD_MailChimp_Extension::instance();
