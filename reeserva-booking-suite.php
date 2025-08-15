
<?php
/**
 * Plugin Name: Reeserva Booking Suite
 * Description: Minimal base plugin with GitHub self-updater wired to simoneomedia/reeserva1.
 * Version: 1.0.0
 * Author: Reeserva
 * Text Domain: reeserva
 * Update URI: https://github.com/simoneomedia/reeserva1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define('RSV_VER','1.0.0');
define('RSV_PATH', plugin_dir_path(__FILE__));
define('RSV_URL',  plugin_dir_url(__FILE__));

require_once RSV_PATH.'includes/updater.php';

add_action('init', function(){
    if ( class_exists('Reeserva_GitHub_Updater') ) {
        new Reeserva_GitHub_Updater(__FILE__, [
            'owner'  => 'simoneomedia',
            'repo'   => 'reeserva1',
            'branch' => 'main',
            'channel'=> 'stable',
        ]);
    }
});

add_action('admin_menu', function(){
    add_menu_page('Reeserva','Reeserva','manage_options','rsv_dashboard', function(){
        echo '<div class="wrap"><h1>Reeserva Booking Suite</h1>';
        echo '<p>Version: '.esc_html(RSV_VER).'</p>';
        echo '<p>GitHub Updater is active. Push a release tag higher than the installed version (e.g. v1.0.1) to see updates in Dashboard → Updates.</p>';
        echo '</div>';
    }, 'dashicons-calendar-alt', 26);
});
