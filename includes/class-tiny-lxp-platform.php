<?php
/*
 *  wordpress-tiny-lxp-platform - Enable WordPress to act as an Tiny LXP Platform.

 *  Copyright (C) 2022  Waqar Muneer
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 *  Contact: Waqar Muneer <waqarmuneer@gmail.com>
 */

use ceLTIc\LTI\Util;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @link       http://www.spvsoftwareproducts.com/php/wordpress-tiny-lxp-platform
 * @since      1.0.0
 * @package    Tiny_LXP_Platform
 * @subpackage Tiny_LXP_Platform/includes
 * @author     Waqar Muneer <waqarmuneer@gmail.com>
 */
class Tiny_LXP_Platform
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Tiny_LXP_Platform_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The name of the post type.
     *
     * @since    2.0.0
     * @access   static
     * @var      string    $version    The name of the post type.
     */
    public static $postType = null;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   static
     * @var      DataConnector_wp    $tinyLxpPlatformDataConnector    The Tiny LXP data connector.
     */
    public static $tinyLxpPlatformDataConnector;
    private $ok = true;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->version = Tiny_LXP_PLATFORM_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

        $this->ok = $this->check_dependencies();
        if ($this->ok && class_exists('DataConnector_wp')) {
            self::$tinyLxpPlatformDataConnector = DataConnector_wp::createDataConnector($wpdb->dbh, $wpdb->base_prefix);
            if (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN) {
                self::$postType = Tiny_LXP_Platform_Tool::POST_TYPE_NETWORK;
            } else {
                self::$postType = Tiny_LXP_Platform_Tool::POST_TYPE;
            }
        }
    }

    public function isOK()
    {
        return $this->ok;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * Autoload file for dependent libraries.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'tiny-lxp-resource/load.php';

        /**
         * Teacher front-end lockdown. Self-registers its hooks rather than going
         * through the loader — see the file header for why an access control must
         * not sit behind the isOK() dependency gate.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-teacher-access.php';

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-tiny-lxp-platform-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-tiny-lxp-platform-public.php';
        
        /**
         * The class responsible for define all widgets and working in admin and public
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-widget.php';

        if (class_exists('ceLTIc\LTI\Tool')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-tool.php';
        }
        if (class_exists('ceLTIc\LTI\Platform')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-platform.php';
        }

        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-tiny-lxp-platform-tool-list-table.php';

        if (class_exists('ceLTIc\LTI\DataConnector\DataConnector')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-tiny-lxp-platform-dataconnector.php';
        }

        $this->loader = new Tiny_LXP_Platform_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Tiny_LXP_Platform_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new Tiny_LXP_Platform_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Tiny_LXP_Platform_Admin(self::get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_init', $plugin_admin, 'settings_init');
        $this->loader->add_action('admin_menu', $plugin_admin, 'options_page');
        $this->loader->add_action('admin_menu', $plugin_admin, 'register_curriki_learn_menu');
        $this->loader->add_action('network_admin_menu', $plugin_admin, 'network_options_page');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('init', 'Tiny_LXP_Platform_Tool', 'register');
        $this->loader->add_filter('posts_orderby', 'Tiny_LXP_Platform_Tool_List_Table', 'tools_orderby', 10, 2);
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Tiny_LXP_Platform_Public(self::get_plugin_name(), $this->get_version());
		$course_extension = new TL_LearnPress_Course_Extension();
        $lesson_extension = new TL_LearnPress_Lesson_Extension();
        $tl_h5p_extension = new TL_H5P_Extension();

        $this->loader->add_action('parse_request', $plugin_public, 'parse_request');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_workbook_scripts');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_capstone_scripts');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_policy_document_scripts');
		$this->loader->add_filter('post_row_actions', $course_extension, 'modify_list_row_actions', 10, 2);
        $this->loader->add_action('wp_enqueue_scripts', $course_extension, 'enqueue_student_course_styles');
        $this->loader->add_action('add_meta_boxes', $course_extension, 'add_meta_boxes');
        $this->loader->add_action('save_post_lp_course', $course_extension, 'save_course_outcome_meta', 10, 2);
        $this->loader->add_filter('tinylxp_lti_launch_metadata', $lesson_extension, 'provide_lti_launch_metadata', 10, 5);
        $this->loader->add_action('add_meta_boxes', $lesson_extension, 'add_meta_boxes');
        $this->loader->add_action('save_post_lp_lesson', $lesson_extension, 'save_tl_post', 10, 2);
        $this->loader->add_action('save_post_lp_lesson', $lesson_extension, 'save_lesson_tagline_meta', 20, 2);
        $this->loader->add_action('save_post_lp_lesson', $lesson_extension, 'save_lesson_policy_meta', 30, 2);
        $this->loader->add_action('rest_lp_lesson_query', $lesson_extension, 'post_meta_request_params', 10, 2);
        $this->loader->add_action('rest_insert_lp_lesson', $lesson_extension, 'insert_post_api', 10, 2);
        // wpautop (the_content @10) mangles <style>/<script> blocks pasted into lesson content;
        // remove it only for that one pass — see TL_LearnPress_Lesson_Extension::remove_wpautop_for_lesson_content().
        $this->loader->add_filter('the_content', $lesson_extension, 'remove_wpautop_for_lesson_content', 9);
        $this->loader->add_filter('the_content', $lesson_extension, 'restore_wpautop_after_lesson_content', 11);
        // TinyMCE's own Visual<->Text reformatting for the lesson content editor — see
        // TL_LearnPress_Lesson_Extension::preserve_lesson_editor_html().
        $this->loader->add_filter('tiny_mce_before_init', $lesson_extension, 'preserve_lesson_editor_html', 10, 2);
        $this->loader->add_action( 'plugins_loaded', $tl_h5p_extension, 'init', 20 );
        // $this->loader->add_action('wp_footer', $lesson_extension, 'render_js_debug_panel', 9999);
        // register_course_shortcodes and elementor/widget/render_content are registered
        // directly in TL_LearnPress_Course_Extension::__construct() to bypass the isOK() guard.

        $widget_path = new Tiny_LXP_Widget(self::get_plugin_name(), $this->get_version());

        $this->loader->add_action('elementor/widgets/register', $widget_path, 'register_elementor_widgets');
        $this->loader->add_action('wp_enqueue_scripts', $widget_path, 'widget_dependencies');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $options = Tiny_LXP_Platform_Tool::getOptions();
        if (!empty($options['debug']) && ($options['debug'] === 'true')) {
            Util::$logLevel = Util::LOGLEVEL_DEBUG;
        }
        $this->loader->run();
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Tiny_LXP_Platform_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    public function error_deactivate()
    {
        $allowed = array('em' => array());
        echo('  <div class="notice notice-error">' . "\n");
        echo('    <p>' . wp_kses(__('The <em>Tiny LXP  Platform</em> plugin has been deactivated because a dependency is missing; either use <em>Composer</em> to install the dependent libraries or activate the <em>ceLTIc Tiny LXP Library</em> plugin.',
                self::get_plugin_name()), $allowed) . '</p>' . "\n");
        echo('  </div>' . "\n");
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     2.0.0
     * @return    string    The name of the plugin.
     */
    public static function get_plugin_name()
    {
        return Tiny_LXP_PLATFORM_NAME;
    }

    /**
     * Retrieve the name of the settings entry for the plugin.
     *
     * @since     2.0.0
     * @return    string    The settings entry name for the plugin.
     */
    public static function get_settings_name()
    {
        return str_replace('-', '_', Tiny_LXP_PLATFORM_NAME) . '_options';
    }

    /**
     * Check that the Tiny LXP class library is available.
     *
     * @since     2.0.1
     * @return    bool    True if the library is found.
     */
    private function check_tiny_lxp_library()
    {
        return class_exists('ceLTIc\LTI\Platform');
    }

    private function check_dependencies()
    {
        $ok = $this->check_tiny_lxp_library();
        if (!$ok) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            add_action('all_admin_notices', array($this, 'error_deactivate'));
            $plugin_name = self::get_plugin_name();
            deactivate_plugins("{$plugin_name}/{$plugin_name}.php");
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }

        return $ok;
    }

}
