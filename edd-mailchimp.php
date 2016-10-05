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

/*
|--------------------------------------------------------------------------
| LICENSING / UPDATES
|--------------------------------------------------------------------------
*/

// Require the drewm/mailchimp-api wrapper lib
require('vendor/autoload.php');

// Also require deprecated API library while we transition
// everything over ot API3
if( ! class_exists( 'EDD_MailChimp_API' ) ) {
  include( EDD_MAILCHIMP_PATH . '/includes/deprecated/class-edd-mailchimp-api.php' );
}

if ( class_exists( 'EDD_License' ) && is_admin() ) {
  $eddmc_license = new EDD_License( __FILE__, EDD_MAILCHIMP_PRODUCT_NAME, '2.5.6', 'Pippin Williamson' );
}



if ( edd_has_upgrade_completed( 'upgrade_mailchimp_groupings_settings' ) ) {
  // Use the new MailChimp class
  if( ! class_exists( 'EDD_MailChimp' ) ) {
    include( EDD_MAILCHIMP_PATH . '/includes/class-edd-mailchimp.php' );
    $edd_mc = new EDD_MailChimp;
  }

} else {

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
    $edd_mc = new EDD_MailChimp('mailchimp', 'MailChimp');
  }
}


if( ! class_exists( 'EDD_MC_Ecommerce_360' ) ) {
	include( EDD_MAILCHIMP_PATH . '/includes/class-edd-ecommerce360.php' );
}

if ( ! class_exists( 'EDD_MC_Tools' ) && class_exists( 'Easy_Digital_Downloads' ) ) {
	if ( ( defined ( 'EDD_VERSION' ) && version_compare( EDD_VERSION, '2.4.2', '>=' ) ) && is_admin() ) {
		include( EDD_MAILCHIMP_PATH . '/includes/class-edd-mailchimp-tools.php' );
	}
}

$edd_mc360    = new EDD_MC_Ecommerce_360;

if ( class_exists( 'EDD_MC_Tools' ) ) {
	$edd_mc_tools = new EDD_MC_Tools();
}
