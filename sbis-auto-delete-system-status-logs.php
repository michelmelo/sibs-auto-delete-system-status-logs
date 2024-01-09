<?php
/**
 * Plugin Name: Sibs Auto Delete Status Logs for WooCommerce
 * Plugin URI: https://michelmelo.pt
 * Description: This plugin deletes WooCommerce status log files automatically after a time period specified by the administrator.
 * Version:1.3.0
 * Author:michelmelo
 * Author URI:https://michelmelo.pt.
 */

define('SIBS_AUTODELETE_VERSION', '1.3.0');
define('SIBS_AUTODELETE_PLUGIN_FILE', __FILE__);
define('SIBS_AUTODELETE_PLUGIN_DIR', plugins_url('/', __FILE__));
define('SIBS_AUTODELETE_TRANSLATE', plugins_url('/', __FILE__));

load_plugin_textdomain(SIBS_AUTODELETE_TRANSLATE, false, dirname(plugin_basename(__FILE__)) . '/languages/');


add_action('admin_bar_menu', 'sibs_auto_delete_register_in_wp_admin_bar', 50);
function sibs_auto_delete_register_in_wp_admin_bar($wp_admin_bar)
{
    if (have_current_user_access_to_pexlechris_adminer()) {
        $args = [
            'id'    => 'autoDelete',
            'title' => __('Auto Delete', 'sibs-auto-delete-system-status-logs'),
            'href'  => esc_url(site_url() . '/wp-admin/options-general.php?page=sys-autodelete-statuslogs-setting'),
        ];
        $args2 = [
            'id'    => 'sibslogs',
            'title' => esc_html__('Sibs log', 'sibs-auto-delete-system-status-logs'),
            'href'  => esc_url(site_url() . '/wp-admin/admin.php?page=wc-status&tab=logs'),
        ];
        $wp_admin_bar->add_node($args);
        $wp_admin_bar->add_node($args2);
    }
}

// can be overridden in a must-use plugin
if (! function_exists('have_current_user_access_to_pexlechris_adminer')) {
    function have_current_user_access_to_pexlechris_adminer()
    {
        foreach (pexlechris_adminer_access_capabilities() as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }

        return false;
    }
}

function sibs_auto_delete_statuslogs_activation_logic()
{
    if (! is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Auto Delete status Logs for WooCommerce requires WooCommerce Plugin in order for it to work properly!', 'sibs-auto-delete-system-status-logs'));
    }
}
register_activation_hook(__FILE__, 'sibs_auto_delete_statuslogs_activation_logic');

add_filter('cron_schedules', 'sibs_auto_delete_statuslogs_add_every_twentyfour_hours');
function sibs_auto_delete_statuslogs_add_every_twentyfour_hours($schedules)
{
    $schedules['every_twentyfour_hours'] = [
        'interval' => 86400,
        'display'  => __('Every Day', 'sibs-auto-delete-system-status-logs'),
    ];

    return $schedules;
}

// Schedule an action if it's not already scheduled
if (! wp_next_scheduled('sibs_auto_delete_statuslogs_add_every_twentyfour_hours')) {
    wp_schedule_event(time(), 'every_twentyfour_hours', 'sibs_auto_delete_statuslogs_add_every_twentyfour_hours');
}

/*
 ** calculate datetime
 * @package  Auto Delete System Status Logs
 * @since 1.1.1
*/
if (! function_exists('sibs_auto_delete_statuslogs_remove_files_from_dir_older_than_seven_days')) {
    function sibs_auto_delete_statuslogs_remove_files_from_dir_older_than_seven_days($dir, $seconds = 3600)
    {
        //$files = glob(rtrim($dir, '/')."/webhooks-delivery-*");
        $files = glob(rtrim($dir, '/') . '/*.log');
        $now   = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $seconds) {
                    unlink($file);
                }
            } else {
                sibs_auto_delete_statuslogs_remove_files_from_dir_older_than_seven_days($file, $seconds);
            }
        }
    }
}

function sibs_auto_delete_statuslogs_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=sys-autodelete-statuslogs-setting">' . __('Settings', 'sibs-auto-delete-system-status-logs') . '</a>';
    array_unshift($links, $settings_link);

    return $links;
}

$plugin = plugin_basename(__FILE__);

add_filter("plugin_action_links_$plugin", 'sibs_auto_delete_statuslogs_settings_link');

add_action('sibs_auto_delete_statuslogs_add_every_twentyfour_hours', 'sibs_auto_delete_statuslogs_every_twentyfour_hours_event_func');
function sibs_auto_delete_statuslogs_every_twentyfour_hours_event_func()
{
    $uploads     = wp_upload_dir();
    $upload_path = $uploads['basedir'];
    $dir         = $upload_path . '/wc-logs/';

    $sys_autodelete_intervaldays = (int) get_option('sys_autodelete_intervaldays');
    $week_days                   = 1;

    if (! empty($sys_autodelete_intervaldays)) {
        $total_days = $sys_autodelete_intervaldays;
    } else {
        $total_days = $week_days;
    }
    sibs_auto_delete_statuslogs_remove_files_from_dir_older_than_seven_days($dir, (60 * 60 * 24 * $total_days)); // 1 day
}

function sibs_auto_delete_statuslogs_atonce()
{
    $log_dir = WC_LOG_DIR;

    foreach (scandir($log_dir) as $file) {
        $path = pathinfo($file);

        // Only delete log files, don't delete the test.log file
        if ($path['extension'] === 'log' && $path['filename'] !== 'test-log') {
            unlink("{$log_dir}/{$file}");
        }
    }
}

function sibs_auto_delete_statuslogs_register_options_page()
{
    add_options_page('Sibs Auto Delete Status Logs', 'Sibs Auto Delete Status Logs', 'manage_options', 'sys-autodelete-statuslogs-setting', 'sibs_auto_delete_statuslogs_options_page');
}
add_action('admin_menu', 'sibs_auto_delete_statuslogs_register_options_page');

function sibs_auto_delete_statuslogs_register_settings()
{
    register_setting('sibs_auto_delete_statuslogs_options_group', 'sys_autodelete_intervaldays');
}
add_action('admin_init', 'sibs_auto_delete_statuslogs_register_settings');

function sibs_auto_delete_statuslogs_options_page()
{
    echo '<div class="sys-autodelete-autoexpired-main">
            <div class="sys-autodelete-autoexpired"><form class="sys-autodelete-clearlog-form" method="post" action="options.php">
            <h1>' . __('Sibs Auto Delete Status Logs for WooCommerce', 'sibs-auto-delete-system-status-logs') . '</h1>';
    settings_fields('sibs_auto_delete_statuslogs_options_group');
    do_settings_sections('sibs_auto_delete_statuslogs_options_group');

    echo ' <div class="sys-autodelete-form-field" style="margin-top:50px">
                <label for="sys_autodelete_set_interval">' . __('Schedule days to auto delete status logs', 'sibs-auto-delete-system-status-logs') . '</label>';
    echo "<input class='sys-autodelete-input-field' type='text' id='sys_autodelete_set_interval' name='sys_autodelete_intervaldays' 
			   value='" . get_option('sys_autodelete_intervaldays') . "' /> 
            </div>";
    submit_button();
    echo '</form> </div></div>';
    echo '	<div class="sysautodelete-divider" style="margin:10px;font-weight:bold;"> ' . __('OR', 'sibs-auto-delete-system-status-logs') . ' </div>
		 <h1 class="sysautodelete-title">' . __('Clear log files right now!', 'sibs-auto-delete-system-status-logs') . '</h1>  
		<div class="sys-autodelete-form-field" style="margin-top:50px">';
    echo "<form action=' " . sibs_auto_delete_statuslogs_atonce() . "' method='post'>";
    echo '<input type="hidden" name="action" value="my_action">';
    echo '<input type="submit" class="sysautodelete-clearbtn" value="' . __('Clear All', 'sibs-auto-delete-system-status-logs') . '" onclick="sysautodelete_showMessage()">';
    echo '</form>
		 </div>'; ?>
       
<script type="text/javascript">
function sysautodelete_showMessage() { alert("<?php echo __('Status Logs Cleared', 'sibs-auto-delete-system-status-logs')?>");}</script>
    <?php
}
