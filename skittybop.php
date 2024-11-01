<?php
/**
 * Plugin Name: Skittybop
 * Plugin URI: https://skittybop.com
 * Description: Adds video calls to WordPress!
 * Version: 1.0.4
 * Requires at least: 4.6.0
 * Tags: Skittybop, jitsi, video, audio, conference, operators, support
 * License: GPL V3
 * Author: Remwes LLC <plugins@remwes.com>
 * Author URI: https://remwes.com
 * Text Domain: skittybop
 * Domain Path: /languages
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Skittybop')) :

    /**
     * Main Skittybop Class
     */
    class Skittybop
    {
        private static $instance;

        public $ajax;

        public $version;
        public $file;
        public $basename;
        public $plugin_dir;
        public $plugin_url;
        public $includes_dir;
        public $includes_url;
        public $lang_dir;
        public $name;
        public $domain;
        public $role;

        /**
         * Main Skittybop Instance
         *
         * @return object the instance
         * @since 1.0.0
         *
         * @package Skittybop
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new Skittybop;
            }
            return self::$instance;
        }

        private function __construct()
        {
            //setup global plugin information
            $this->version = '1.0.4';
            $this->name = apply_filters('skittybop_name', __('Skittybop', "skittybop"));
            $this->domain = apply_filters('skittybop_domain', 'skittybop');

            $this->file = __FILE__;
            $this->basename = apply_filters('skittybop_plugin_basename', plugin_basename($this->file));
            $this->plugin_dir = apply_filters('skittybop_plugin_dir_path', plugin_dir_path($this->file));
            $this->plugin_url = apply_filters('skittybop_plugin_dir_url', plugin_dir_url($this->file));
            $this->includes_dir = apply_filters('skittybop_includes_dir', trailingslashit($this->plugin_dir . 'includes'));
            $this->includes_url = apply_filters('skittybop_includes_url', trailingslashit($this->plugin_url . 'includes'));
            $this->lang_dir = apply_filters('skittybop_lang_dir', trailingslashit($this->plugin_dir . 'languages'));

            // include all required files
            $this->includes();

            //register lifecycle hooks
            register_activation_hook($this->file, array($this, 'activate'));
            register_deactivation_hook($this->file, array($this, 'deactivate'));
            register_uninstall_hook($this->file, array($this, 'uninstall'));
            add_filter('heartbeat_settings', array($this, 'skittybop_heartbeat_settings'), 99, 1);

            //register non lifecycle hooks
            add_action('admin_init', array($this, 'hooks'));
            add_action('init', array($this, 'hooks'));

            $this->ajax = new SkittybopAjax();
        }

        /**
         * Includes all required files
         *
         * @package Skittybop
         * @since 1.0.0
         */
        private function includes()
        {
            require($this->includes_dir . 'skittybop-constants.php');
            require($this->includes_dir . 'skittybop-functions.php');
            require($this->includes_dir . 'skittybop-ajax.php');
        }

        /**
         * Register hooks for actions and filters
         *
         * @package Skittybop
         * @since 1.0.0
         */
        public function hooks()
        {
            $this->check_version();

            register_setting( 'skittybop-settings', 'skittybop_api_key', array($this, 'skittybop_validate_api_key_callback') );

            //register generic hooks
            add_action('set_logged_in_cookie', array($this, 'login'), 10, 6);
            add_action('wp_logout', array($this, 'logout'));

            //register hooks for the admin panel
            if (is_admin()) {
                add_action('admin_menu', array($this, 'skittybop_register_menu'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_filter('admin_footer_text', array($this, 'skittybop_add_dialogs'));

                add_filter('set-screen-option', array($this, 'skittybop_set_call_history_per_page'), 10, 3);
                add_filter('set-screen-option', array($this, 'skittybop_set_operators_per_page'), 10, 3);

                add_action('admin_notices', array($this, 'skittybop_subscription_notice'));
            }

            //register hooks for the front site and logged in users
            if (is_user_logged_in()) {
                add_action( 'template_redirect', array($this, 'popup_page_detect') );
                add_filter( 'page_template', array($this, 'popup_page_template') );

                add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_action('wp_footer', array($this, 'skittybop_add_button'));
                add_action('wp_footer', array($this, 'skittybop_add_dialogs'));
            }
        }

        /**
         * Handles plugin's activation
         */
        public function activate()
        {
            //migrate database schema
            $this->migrate_db();

            //initialize operator role
            $this->roles();

            update_option(SkittybopOption::ENABLED, true);
            do_action('skittybop_activate');
        }

        /**
         * Handles plugin's deactivation
         */
        public function deactivate()
        {
            update_option(SkittybopOption::ENABLED, false);
            do_action('skittybop_deactivate');
        }

        /**
         * Handles plugin's uninstallation
         */
        public function uninstall()
        {
            //cleanup database schema
            $this->cleanup_db();

            //cleanup operator role
            remove_role($this->role->name);

            update_option(SkittybopOption::ENABLED, false);
            do_action('skittybop_uninstall');
        }

        /**
         * Checks plugin version against db and updates
         *
         */
        public function check_version()
        {
            update_option(SkittybopOption::VERSION, skittybop()->version);
        }

        /**
         * Callback function for the action hook - set_logged_in_cookie.
         *
         * @param $logged_in_cookie
         * @param $expire
         * @param $expiration
         * @param $user_id
         * @param $logged_in_text
         * @param $token
         *
         * @return void
         */
        public function login($logged_in_cookie, $expire, $expiration, $user_id, $logged_in_text, $token)
        {
            //check the user is an operator
            $user = get_user_by('id', $user_id);
            if (!isset($user)) {
                return;
            }

            if (in_array(SkittybopRole::OPERATOR, (array)$user->roles)) {
                update_user_meta($user_id, SkittybopOption::ONLINE, true);
            }
        }

        /**
         * Presents admins a notice when no api key has been configured in the settings
         */
        public function skittybop_subscription_notice() {
            $apiKey = get_option('skittybop_api_key', null);
            $isAdmin = current_user_can(SkittybopRole::ADMINISTRATOR);

            if (!$isAdmin || !empty($apiKey)) {
                return;
            }

            $message = "<b>" . __("Important", "skittybop") . "!</b> ";
            $message .= __("It looks like you haven't set up your API Key yet. Skittybop plugin uses the", "skittybop") . " <a href='" . esc_attr(SKITTYBOP_SITE_URL) . "' target='_blank'>" . esc_attr(SKITTYBOP_SITE_NAME) . "</a> " . __("service.", "skittybop");
            $message .= " " . __("To get started, please follow these steps:", "skittybop");
            $message .= "<ol>";
            $message .= "<li>" . __("Go to", "skittybop") . " <a href='" . esc_attr(SKITTYBOP_APP_URL) . "' target='blank'>" . esc_attr(SKITTYBOP_APP_NAME) . "</a> " . __("and log in or register if you don't have an account.", "skittybop") . "</li>";
            $message .= "<li>" . __("After logging in, create a new application to generate your API Key.", "skittybop") . "</li>";
            $message .= "<li>" . __("Once you have your API Key, navigate to the", "skittybop") . " <a href='" . admin_url('admin.php?page=' . esc_attr(SkittybopMenus::SETTINGS)) . "'>" . __("Settings", "skittybop") . "</a> " . __("of this application and enter it there.", "skittybop") . "</li>";
            $message .= "</ol>";

            echo '<div class="notice notice-warning is-dismissible"><p>'.wp_kses_post($message).'</p></div>';
        }

        public function skittybop_validate_api_key($apiKey) {
            $url = SKITTYBOP_APP_API_URL . "/apps/validateApiKey";
            $user = wp_get_current_user();
            if (!isset($url) || !isset($apiKey) || !isset($user) || !isset($user->ID)) {
                return false;
            }
            $headers = array(
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $apiKey
            );

            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => wp_json_encode(array()),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $status = wp_remote_retrieve_response_code($response);

            return $status === 200;
        }

        public function skittybop_validate_api_key_callback( $input ) {
            $input = sanitize_text_field( $input );
            $isValid = !empty($input) && $this->skittybop_validate_api_key($input);
            if (!$isValid) {
                add_settings_error(
                    'skittybop_api_key', // Setting slug
                    'invalid_api_key', // Error code
                    __( 'Invalid API Key. Please enter follow the instructions to generate your API Key.', "skittybop" ), // Error message
                    'error' // Type of message (error, updated)
                );
                return empty($input)? $input : get_option( 'skittybop_api_key' );
            }

            return $input;
        }


        /**
         * Fires on logout.
         * Saves logout time of current user.
         */
        public function logout($user_id)
        {
            $user = get_user_by('id', $user_id);
            if (!isset($user) || !isset($user->roles)) {
                return;
            }

            if (in_array(SkittybopRole::OPERATOR, (array)$user->roles)) {
                update_user_meta($user_id, SkittybopOption::ONLINE, false);
            }
        }

        /**
         * Loads plugin's translation
         *
         * @package Skittybop
         * @since 1.0.0
         * @uses get_locale()
         * @uses load_textdomain()
         */
        public function load_textdomain()
        {
            $locale = apply_filters('skittybop_load_textdomain_get_locale', get_locale(), $this->domain);
            $mofile = sprintf('%1$s-%2$s.mo', $this->domain, $locale);
            $mofile_global = WP_LANG_DIR . '/skittybop/' . $mofile;

            if (!load_textdomain($this->domain, $mofile_global)) {
                load_plugin_textdomain($this->domain, false, basename($this->plugin_dir) . '/languages');
            }
        }

        /**
         * Includes all required css files
         *
         * @return void
         */
        public function enqueue_styles()
        {
            if (is_user_logged_in()) {
                if ($this->is_skittybop_page()) {
                    wp_enqueue_style('datatables-css', $this->plugin_url . "assets/css/datatables.css", '', $this->version, 'screen');
                }
                wp_enqueue_style('skittybop-css', $this->plugin_url . "assets/css/skittybop.css", '', $this->version, 'screen');
            }
        }

        /**
         * Includes all required javascript files
         *
         * @return void
         */
        public function enqueue_scripts()
        {
            if (is_user_logged_in()) {
                wp_enqueue_script('heartbeat');
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-resizable');
                wp_enqueue_script('jquery-ui-dialog');

                $date_format = get_option('date_format');
                $time_format = get_option('time_format');
                $datetime_format = $date_format . ' ' . $time_format;

                $args = array(
                    'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
                    'name' => esc_js($this->name),
                    'is_operator' => current_user_can(SkittybopRole::OPERATOR),
                    'is_administrator' => current_user_can(SkittybopRole::ADMINISTRATOR),
                    'plugin_url' => esc_js(skittybop()->plugin_url),
                    'datetime_format' => esc_js($datetime_format),
                    'status' => array(
                        'pending' => intval(SkittybopCallStatus::PENDING),
                        'accepted' => intval(SkittybopCallStatus::ACCEPTED),
                        'canceled' => intval(SkittybopCallStatus::CANCELED),
                        'failed' => intval(SkittybopCallStatus::FAILED),
                        'rejected' => intval(SkittybopCallStatus::REJECTED),
                    ),
                    'buttons' => array(
                        "skittybop" => esc_js(SKITTYBOP_BUTTON),
                    ),
                    'img' => array(
                        'trash' => esc_js(SkittybopImage::TRASH),
                        'camera' => esc_js(SkittybopImage::CAMERA),
                    ),
                    'lang' => array(
                        'pending' => esc_js(__("Pending", "skittybop")),
                        'accepted' => esc_js(__("Accepted", "skittybop")),
                        'canceled' => esc_js(__("Canceled", "skittybop")),
                        'failed' => esc_js(__("Failed", "skittybop")),
                        'rejected' => esc_js(__("Rejected", "skittybop")),
                        'pop_out' => esc_js(__("Pop Out", "skittybop")),
                    )
                );

                $params = apply_filters('skittybop_custom_jitsi_settings', array());
                $params = wp_parse_args($params, $this->get_default_settings());

                $user = wp_get_current_user();
                $user_name = esc_js($user->display_name);
                $avatar_url = esc_js(get_avatar_url($user->ID));

                $params['user'] = $user_name;
                $params['avatar'] = $avatar_url;

                $args['jitsi'] = $params;

                // Enqueues the Jitsi IFrame API (https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe)
                // Source code available at https://github.com/jitsi/jitsi-meet/tree/master/modules/API/external/external_api.js
                wp_enqueue_script('skittybop-jitsi-js', 'https://8x8.vc/external_api.js', array(), null, true);
                wp_enqueue_script('skittybop-call-js', $this->plugin_url . 'assets/js/skittybop.call.js', array('skittybop-jitsi-js', 'jquery-ui-dialog'), $this->version, true);
                wp_localize_script('skittybop-call-js', 'args', $args);

                if ($this->is_skittybop_page()) {
                    wp_enqueue_script( 'moment' );
                    wp_enqueue_script('datatables-js', $this->plugin_url . 'assets/js/dataTables.js', array('moment'), $this->version, true);
                }

                if ($this->is_skittybop_history_page()) {
                    wp_enqueue_script('skittybop-history-js', $this->plugin_url . 'assets/js/skittybop.history.js', array('datatables-js'), $this->version, true);
                    wp_localize_script('skittybop-history-js', 'args', $args);
                }

                if ($this->is_skittybop_operators_page()) {
                    wp_enqueue_script('skittybop-operators-js', $this->plugin_url . 'assets/js/skittybop.operators.js', array('datatables-js'), $this->version, true);
                    wp_localize_script('skittybop-operators-js', 'args', $args);
                }
            }
        }

        /**
         * Checks and create the required roles.
         *
         * @access public
         * @return void
         */
        public function roles()
        {
            $this->role = get_role(SkittybopRole::OPERATOR);
            if (!is_a($this->role, 'WP_Role')) {
                $this->role = add_role(SkittybopRole::OPERATOR, SkittybopRoleLabel::OPERATOR);
            }
            if (is_a($this->role, 'WP_Role')) {
                $this->role->add_cap('read');
                $this->role->add_cap('level_0');
                $this->role->add_cap(SkittybopCapability::MANAGE);
            }
            $admin_role = get_role(SkittybopRole::ADMINISTRATOR);
            if (is_a($admin_role, 'WP_Role')) {
                $admin_role->add_cap(SkittybopCapability::MANAGE);
                $admin_role->add_cap(SkittybopCapability::MANAGE_OPERATORS);
                $admin_role->add_cap(SkittybopCapability::MANAGE_SETTINGS);
            }
        }

        /**
         * Migrates the database schema to the latest one.
         *
         * @return void
         */
        private function migrate_db()
        {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            global $wpdb;
            $table = $wpdb->prefix . SKITTYBOP_TABLE_CALLS;
            $sql = "CREATE TABLE {$table} (
				id bigint(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                room varchar(36) NOT NULL,
                user_id bigint(11) NOT NULL,
                operator_id bigint(11) NOT NULL,
                status int(1) NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT now() NOT NULL,
				started_at TIMESTAMP NULL,
				ended_at TIMESTAMP NULL,
                UNIQUE KEY `operator_id_room_unique` (`operator_id`, `room`),
                INDEX user_id_idx (user_id),
                INDEX room_idx (room),
                INDEX operator_id_idx (operator_id),
                INDEX status_idx (status)
		) DEFAULT CHARSET=utf8;";
            dbDelta($sql);
        }

        /**
         * Cleans up the database upon plugin's uninstallation.
         *
         * @return void
         */
        private function cleanup_db()
        {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare("DROP TABLE IF EXISTS %i;", $wpdb->prefix . SKITTYBOP_TABLE_CALLS)
            );
        }

        public function skittybop_register_menu()
        {
            global $skittybop_history_page;
            global $skittybop_operators_page;

            $skittybop_history_page = add_menu_page(__('Skittybop', "skittybop"), __('Skittybop', "skittybop"), SkittybopCapability::MANAGE,
                'skittybop', array($this, 'skittybop_admin_page_history'), 'dashicons-video-alt2', 20);

            $skittybop_operators_page = add_submenu_page('skittybop', __('Operators', "skittybop"), __('Operators', "skittybop"),
                SkittybopCapability::MANAGE_OPERATORS, SkittybopMenus::OPERATORS, array($this, 'skittybop_admin_page_operators'));

            add_submenu_page('skittybop', __('Settings', "skittybop"), __('Settings', "skittybop"),
                SkittybopCapability::MANAGE_SETTINGS, SkittybopMenus::SETTINGS, array($this, 'skittybop_admin_page_settings'));

            add_action("load-$skittybop_history_page", array($this, "skittybop_call_history_screen_options"));
            add_action("load-$skittybop_operators_page", array($this, "skittybop_operators_screen_options"));


        }

        public function skittybop_call_history_screen_options() {
            global $skittybop_history_page;

            $screen = get_current_screen();
            if(!is_object($screen) || $screen->id != $skittybop_history_page) {
                return;
            }

            $args = array(
                'label' => __('Number of items per page', "skittybop"),
                'default' => 10,
                'option' => 'skittybop_call_history_per_page'
            );
            add_screen_option( 'per_page', $args );
        }

        public function skittybop_operators_screen_options() {
            global $skittybop_operators_page;

            $screen = get_current_screen();
            if(!is_object($screen) || $screen->id != $skittybop_operators_page) {
                return;
            }

            $args = array(
                'label' => __('Number of items per page', "skittybop"),
                'default' => 10,
                'option' => 'skittybop_operators_per_page'
            );
            add_screen_option( 'per_page', $args );
        }

        public function skittybop_set_call_history_per_page($keep, $option, $value) {
            return $value;
        }

        public function skittybop_set_operators_per_page($keep, $option, $value) {
            return $value;
        }

        public function skittybop_admin_page_history()
        {
            $content = "";
            if ($this->is_skittybop_page()) {
                $template = $this->plugin_dir . '/templates/history.php';
                if (file_exists($template)) {
                    ob_start();
                    include($template);
                    $content = ob_get_clean();
                }
            }

            $this->print_template($content);
        }

        public function skittybop_admin_page_operators()
        {
            $content = "";
            if ($this->is_skittybop_page()) {
                $template = $this->plugin_dir . '/templates/operators.php';
                if (file_exists($template)) {
                    ob_start();
                    include($template);
                    $content = ob_get_clean();
                }
            }

            $this->print_template($content);
        }

        public function skittybop_admin_page_settings()
        {
            $content = "";
            if ($this->is_skittybop_page()) {
                $template = $this->plugin_dir . '/templates/settings.php';
                if (file_exists($template)) {
                    ob_start();
                    include($template);
                    $content = ob_get_clean();
                }
            }

            $this->print_form_template($content);
        }

        public function skittybop_add_button($footer)
        {
            $html = '<div class="skittybop-button-wrapper">';
            $html .= '<a id="' . esc_attr(SKITTYBOP_BUTTON) . '" href="#" class="skittybop-button skittybop-red  skittybop-button-footer"><img src="' .
                esc_attr(SkittybopImage::CAMERA) . '"></a>';
            $html .= '</div>';

            if ($footer) {
                return $footer . $html;
            } else {
                $this->print_button_with_image($html);
            }
        }

        public function skittybop_add_dialogs($footer)
        {
            $html = '<div id="skittybop-dialog-outgoing" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("Please wait for the video call to begin.", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopCancelCallButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Cancel", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-incoming" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("You have an incoming video call.", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopRejectCallButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Reject", "skittybop") . '</div>';
            $html .= '<div id="skittybopAcceptCallButton" class="skittybop-button skittybop-green skittybop-dialog-button">' . __("Accept", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-call" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div id="skittybopVideoCall"> </div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-answered" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("The video call is no longer available.", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopCloseAnsweredCallButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Close", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-confirm-delete" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("Are you sure you want to proceed with deleting the selected video calls?", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopCancelDeleteButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Cancel", "skittybop") . '</div>';
            $html .= '<div id="skittybopConfirmDeleteButton" class="skittybop-button skittybop-green skittybop-dialog-button">' . __("Confirm", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-no-operator" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("Sorry, no operators are available right now. Please try again later.", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopCloseNoOperatorButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Close", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div id="skittybop-dialog-service-unavailable" title="'. esc_attr(skittybop()->name) .'" class="skittybop-dialog-wrapper">';
            $html .= '<div class="skittybop-dialog-text">' . __("The service is currently unavailable. Please try again later.", "skittybop") . '</div>';
            $html .= '<div class="skittybop-dialog-buttons">';
            $html .= '<div id="skittybopCloseServiceUnavailableButton" class="skittybop-button skittybop-red skittybop-dialog-button">' . __("Close", "skittybop") . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= wp_nonce_field('skittybop-change-call-status', '_wpnonce_skittybop_change_call_status');
            $html .= wp_nonce_field('skittybop-change-call-timestamp', '_wpnonce_skittybop_change_call_timestamp');
            $html .= wp_nonce_field('skittybop-call', '_wpnonce_skittybop_call');

            if ($footer) {
                return $footer . $html;
            } else {
                $this->print_template($html);
            }
        }

        public function is_skittybop_page()
        {
            return $this->is_skittybop_history_page() || $this->is_skittybop_operators_page() || $this->is_skittybop_settings_page();
        }

        public function is_skittybop_history_page()
        {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
            return ($screen && $screen->id === 'toplevel_page_skittybop') ;
        }

        public function is_skittybop_operators_page()
        {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
            return ($screen && $screen->id === 'skittybop_page_' . SkittybopMenus::OPERATORS) ;
        }

        public function is_skittybop_settings_page()
        {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
            return ($screen && $screen->id === 'skittybop_page_' . SkittybopMenus::SETTINGS) ;
        }

        public function is_skittybop_popup_page()
        {
            global $wp;
            $popout_page = "skittybop/popout";
            $current_url = home_url( $wp->request );
            return (isset($wp->query_vars['pagename']) && $wp->query_vars['pagename'] == $popout_page) ||
                substr_compare($current_url, $popout_page, -strlen($popout_page)) === 0;
        }

        public function get_default_settings()
        {
            return array(
                'enabled' => true,
                'meet_members_enabled' => true,
                'room' => '',
                'domain' => SKITTYBOP_SERVER_DOMAIN,
                'film_strip_only' => false,
                'width' => '100%',
                'height' => '100%',
                'start_audio_only' => false,
                'mobile_open_in_browser' => true,
                'parent_node' => '',
                'default_language' => 'en',
                'background_color' => '#464646',
                'show_watermark' => true,
                'show_brand_watermark' => false,
                'brand_watermark_link' => '',
                'settings' => 'devices,language,moderator,profile,calendar,sounds',
                'disable_video_quality_label' => false,
                'toolbar' => 'camera,chat,closedcaptions,desktop,download,etherpad,filmstrip,fullscreen,hangup,livestreaming,microphone,mute-everyone,mute-video-everyone,participants-pane,profile,raisehand,recording,security,select-background,settings,shareaudio,sharedvideo,shortcuts,stats,tileview,toggle-camera,videoquality,__end'
            );
        }

        public function skittybop_heartbeat_settings($settings)
        {
            $settings['interval'] = 15;
            return $settings;
        }

        public function popup_page_detect()
        {
            if (!$this->is_skittybop_popup_page()){
                return;
            }

            global $wp, $wp_query;

            $post_id = -99;
            $post = new stdClass();
            $post->ID = $post_id;
            $post->post_author = 1;
            $post->post_date = current_time( 'mysql' );
            $post->post_date_gmt = current_time( 'mysql', 1 );
            $post->post_title = $this->name;
            $post->post_content = '';
            $post->post_status = 'publish';
            $post->comment_status = 'closed';
            $post->ping_status = 'closed';
            $post->post_name = 'popup-' . wp_rand( 1, 99999 );
            $post->post_type = 'page';
            $post->filter = 'raw'; // important!

            // Convert to WP_Post object
            $wp_post = new WP_Post( $post );

            // Add the fake post to the cache
            wp_cache_add( $post_id, $wp_post, 'posts' );

            // Update the main query
            $wp_query->post = $wp_post;
            $wp_query->posts = array( $wp_post );
            $wp_query->queried_object = $wp_post;
            $wp_query->queried_object_id = $post_id;
            $wp_query->found_posts = 1;
            $wp_query->post_count = 1;
            $wp_query->max_num_pages = 1;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_single = false;
            $wp_query->is_attachment = false;
            $wp_query->is_archive = false;
            $wp_query->is_category = false;
            $wp_query->is_tag = false;
            $wp_query->is_tax = false;
            $wp_query->is_author = false;
            $wp_query->is_date = false;
            $wp_query->is_year = false;
            $wp_query->is_month = false;
            $wp_query->is_day = false;
            $wp_query->is_time = false;
            $wp_query->is_search = false;
            $wp_query->is_feed = false;
            $wp_query->is_comment_feed = false;
            $wp_query->is_trackback = false;
            $wp_query->is_home = false;
            $wp_query->is_embed = false;
            $wp_query->is_404 = false;
            $wp_query->is_paged = false;
            $wp_query->is_admin = false;
            $wp_query->is_preview = false;
            $wp_query->is_robots = false;
            $wp_query->is_posts_page = false;
            $wp_query->is_post_type_archive = false;

            // Update globals
            $GLOBALS['wp_query'] = $wp_query;
            $wp->register_globals();
        }

        public function popup_page_template( $page_template ) {
            if ($this->is_skittybop_popup_page()){
                return $this->plugin_dir . 'templates/popup.php';
            }
            return $page_template;
        }

        public function print_template($template) {
            $allowed_tags = wp_kses_allowed_html('post');
            $allowed_tags['input'] = array(
                'type' => array(),
                'name' => array(),
                'value' => array(),
                'id' => array(),
                'class' => array(),
            );

            echo wp_kses($template, $allowed_tags);
        }

        public function print_form_template($template) {
            $allowed_tags = wp_kses_allowed_html('post');
            $allowed_tags['input'] = array(
                'type' => array(),
                'name' => array(),
                'value' => array(),
                'id' => array(),
                'class' => array(),
            );
            $allowed_tags['form'] = array(
                'method' => array(),
                'action' => array(),
                'id' => array()
            );

            echo wp_kses($template, $allowed_tags);
        }

        public function print_button_with_image($content) {
            $allowed_tags = wp_kses_allowed_html('post');
            $allowed_protocols = wp_allowed_protocols();

            array_push($allowed_protocols, 'data');

            echo wp_kses($content, $allowed_tags, $allowed_protocols);
        }
    }

    function skittybop()
    {
        return skittybop::instance();
    }

    skittybop();

endif;

