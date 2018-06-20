<?php
/*
Plugin Name: Login Watcher
Plugin URI: https://www.brassworks.jp/
Description: A simple login history plugin.
Author: akahige
Author URI: https://www.brassworks.jp/
Version: 20180619
Text Domain: login-watcher
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

require_once(plugin_dir_path(__FILE__) . 'constants.php');
add_option('login_watcher_table_name', LOGIN_WATCHER_TABLE_NAME);

class LoginWatcher {
  /*
   * create table on activate
   */
  public static function activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE " . self::tableName() . " (
      ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      user_login varchar(255) NOT NULL,
      user_id bigint(20) UNSIGNED NOT NULL,
      remote_ip varchar(43),
      user_agent text,
      logged_in_at timestamp NOT NULL,
      UNIQUE KEY ID (ID)
    ) CHARACTER SET ". $charset_collate . ";";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  /*
   * save login history at logged in.
   */
  public static function saveLoginHistory($user_login, $current_user) {
    global $wpdb;

    $history = array(
      'user_login' => $user_login,
      'user_id' => $current_user->ID,
      'remote_ip' => $_SERVER['REMOTE_ADDR'],
      'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    );
    
    $wpdb->insert(self::tableName(), $history);
  }

  /*
   * list of login histories.
   */
  public static function showLoginHistory() {
    $template = file_get_contents(plugin_dir_path(__FILE__) . 'templates/login_history.html');

    $result = self::queryLoginHistories('desc', 25);
    $histories = '';
    foreach ($result as $history) {
      $histories .= '<tr><td>' . $history->logged_in_at. '</td><td>' . $history->user_login . '</td><td>' . $history->remote_ip . '</td><td>' . $history->user_agent . '</td></tr>';
    }

    $output = str_replace('%%page_title%%', get_admin_page_title(), $template);
    $output = str_replace('%%login_histories%%', $histories, $output);
    $output = str_replace('%%csv_download_link%%', plugin_dir_url(__FILE__) . 'download.php', $output);
    echo $output;
  }

  public static function showLoginHistoryMenu() {
    $title = __('Login History', 'login_history');
    add_menu_page($title, $title, 'manage_options', 'login_watcher_login_history', 'LoginWatcher::showLoginHistory', 'dashicons-welcome-learn-more', 81);
  }

  public static function downloadCSV() {
    $csv_header = 'timestamp,user_login,remote_ip,user_agent';

    $lines = array();
    $result = self::queryLoginHistories();
    foreach ($result as $history) {
      $output_values = array($history->logged_in_at, $history->user_login, $history->remote_ip, $history->user_agent);
      $lines[] = implode(',', array_map(function($col) { return '"' . $col . '"'; }, $output_values));
    }

    $csv_body = implode("\n", $lines) . "\n";

		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=login_history.csv'); 

    echo $csv_header;
    echo $csv_body;
  }

  private static function queryLoginHistories($order = 'asc', $limit = 0) {
    global $wpdb;

    $sql = "SELECT * FROM " . self::tableName() . " order by logged_in_at " . $order;
    if (!empty($limit)) {
      $sql .= " limit " . $limit;
    }

    return $wpdb->get_results($sql);
  }

  private static function tableName() {
    global $wpdb;
    return $wpdb->prefix . LOGIN_WATCHER_TABLE_NAME;
  }
}

register_activation_hook( __FILE__, 'LoginWatcher::activate');
add_action('wp_login', 'LoginWatcher::saveLoginHistory', 10, 2);
add_action('admin_menu', 'LoginWatcher::showLoginHistoryMenu');