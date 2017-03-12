<?php

/**
 * Database Class
 *
 * @package     EDD MailChimp
 * @copyright   Copyright (c) 2017, Dave Kiss
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       3.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Database {
  /**
   * DB Version
   *
   * @var [type]
   */
  protected static $_version;

  public function __construct() {
    add_action( 'plugins_loaded', array($this, 'update_db_version_if_not_exists'), 1 );
    add_action( 'plugins_loaded', array($this, 'migrate'), 11 );
    add_action( 'wpmu_new_blog',  array($this, 'install_on_new_blog'), 10, 6);

    register_activation_hook( EDD_MAILCHIMP_BASENAME, array($this, 'activation') );
  }


  /**
   * Helper function to handle DB creation depending on single or multisite installation
   *
   * @param  [type] $network_wide [description]
   * @return [type]               [description]
   */
  public function activation( $network_wide ) {
    if ( is_multisite() && $network_wide ) { // See if being activated on the entire network or one blog
      global $wpdb;

      // Get this so we can switch back to it later
      $original_blog_id = get_current_blog_id();

      // Get all blogs in the network and activate plugin on each one
      $blogs = $wpdb->get_results("
          SELECT blog_id
          FROM {$wpdb->blogs}
          WHERE site_id = '{$wpdb->siteid}'
          AND spam = '0'
          AND deleted = '0'
          AND archived = '0'
      ");

      foreach ( $blogs as $blog ) {
        switch_to_blog( $blog->blog_id );
        self::update_tables();
        self::update_db_version();
      }

      // Switch back to the current blog
      // @link http://wordpress.stackexchange.com/a/89114
      switch_to_blog( $original_blog_id );
      $GLOBALS['_wp_switched_stack'] = array();
      $GLOBALS['switched']           = FALSE;

    } else {
      // Running on a single blog
      self::update_tables();
      self::update_db_version();
    }
  }


  /**
   * [install_on_new_blog description]
   * @param  [type] $blog_id [description]
   * @param  [type] $user_id [description]
   * @param  [type] $domain  [description]
   * @param  [type] $path    [description]
   * @param  [type] $site_id [description]
   * @param  [type] $meta    [description]
   * @return [type]          [description]
   */
  public function install_on_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    global $wpdb;

    if ( is_plugin_active_for_network( EDD_MAILCHIMP_BASENAME ) ) {

      $original_blog_id = get_current_blog_id();

      switch_to_blog( $blog_id );
      self::update_tables();
      self::update_db_version();
      switch_to_blog( $original_blog_id );

    }
  }


  /**
   * Create tables and define defaults when plugin is activated.
   *
   * @access public
   * @return void
   */
  public static function update_tables() {
    global $wpdb;

    $sql = 'CREATE TABLE '.$wpdb->prefix.'edd_mailchimp_lists (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    remote_id varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "",
    name varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "",
    is_default tinyint(2) NOT NULL default "0",
    sync_status varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT "",
    connected_at datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
    synced_at datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
    PRIMARY KEY  (id)
    ) CHARACTER SET utf8 COLLATE utf8_general_ci;
    CREATE TABLE '.$wpdb->prefix.'edd_mailchimp_downloads_lists (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    download_id bigint(20) unsigned NOT NULL,
    list_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY  (id),
    KEY `download_id` (`download_id`)
    KEY `list_id` (`list_id`)
    ) CHARACTER SET utf8 COLLATE utf8_general_ci;
    ';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  /**
   * Gets version number if it exists in the database.
   *
   * @since 1.2
   * @return bool
   */
  public static function get_db_version() {
    return get_site_option('edd_mailchimp_version');
  }

  /**
   * Updates the version number stored in the database.
   *
   * @access public
   * @static
   * @return bool
   */
  public static function update_db_version() {
    return update_site_option('edd_mailchimp_version', EDD_MAILCHIMP_VERSION);
  }

  /**
   * Inserts the database version if it does not exist.
   *
   * @return void
   */
  public static function update_db_version_if_not_exists() {
    if (self::get_db_version() === FALSE) {
      self::update_db_version();
      self::$_version = self::get_db_version();
    }
  }

  /**
   * Perform database migrations
   * 
   * @return [type] [description]
   */
  public function migrate() {
    self::$_version = self::get_db_version();
    self::update_db_version();
  }
}
