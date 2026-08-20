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

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.spvsoftwareproducts.com/php/wordpress-tiny-lxp-platform
 * @since      1.0.0
 * @package    Tiny_LXP_Platform
 * @subpackage Tiny_LXP_Platform/admin
 * @author     Waqar Muneer <waqarmuneer@gmail.com>
 */
use ceLTIc\LTI\Util;

class Tiny_LXP_Platform_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_scripts($hook)
    {
        if (($hook === 'post-new.php') || ($hook === 'post.php')) {
            wp_enqueue_media(); // wp.media picker for the AI Video background-clip field
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/tiny-lxp-platform-post.css', array(), $this->version);
            wp_enqueue_script("script-ck-editor", 'https://cdn.ckeditor.com/4.20.1/full/ckeditor.js', array(), $this->version);
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/tiny-lxp-platform-post.js', array('wp-element', 'wp-editor', 'wp-rich-text'), $this->version, false);
        } elseif (
            ($hook === "settings_page_{$this->plugin_name}-settings") ||
            ($hook === "settings_page_{$this->plugin_name}-edit") ||
            ($hook === 'curriki-learn_page_curriki-learn-remotion-settings')
        ) {
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/tiny-lxp-platform-settings.js');
        }
    }

    public function options_page()
    {
        $menu = add_options_page('Tiny LXP Tools', 'Tiny LXP Tools', 'manage_options', Tiny_LXP_Platform::get_plugin_name(),
            array($this, 'view_page_html'));
        add_action("load-{$menu}", array($this, 'load_tools_table'));

        $submenu = add_submenu_page(null, 'Add Tiny LXP Tool', 'Add New', 'manage_options', Tiny_LXP_Platform::get_plugin_name() . '-edit',
            array($this, 'edit_page_html'));
        add_action("load-{$submenu}", array($this, 'load_submenu'));

        $submenu = add_submenu_page(null, 'Tiny LXP Platform Settings', 'Settings', 'manage_options',
            Tiny_LXP_Platform::get_plugin_name() . '-settings', array($this, 'options_page_html')
        );
        add_action("load-{$submenu}", array($this, 'load_submenu'));
        add_menu_page(
            'Edlink Integrations',  // Page title
            'Edlink Settings',       // Menu title
            'manage_options',        // Capability required to access this menu
            'edlink_options',        // Menu slug
            'edlink_options_page'    // Function that displays the page content
        );

        // Display the settings page content
        function edlink_options_page() {
            ?>
            <form action="options.php" method="post">
                <h2>Edlink Integrations</h2>
                <?php
                settings_fields('edlink_options_group');  // Output security fields for the registered setting
                do_settings_sections('edlink_options');   // Output setting sections and fields
                submit_button('Save Settings');           // Save button
                ?>
            </form>
            <?php
        }

        // Callback function for the section description
        function edlink_settings_section_cb() {
            echo '<p>Configure your Edlink API credentials below.</p>';
        }

        // Render the Application Id field
        function edlink_application_id_render() {
            $options = get_option('edlink_options');
            ?>
            <input type="text" name="edlink_options[edlink_application_id]" value="<?php echo isset($options['edlink_application_id']) ? esc_attr($options['edlink_application_id']) : ''; ?>" />
            <?php
        }

        // Render the Application Secrets field
        function edlink_application_secrets_render() {
            $options = get_option('edlink_options');
            ?>
            <input type="text" name="edlink_options[edlink_application_secrets]" value="<?php echo isset($options['edlink_application_secrets']) ? esc_attr($options['edlink_application_secrets']) : ''; ?>" />
            <?php
        }

        // Render the Enable Edlink SSO checkbox field
        function edlink_sso_enable_render() {
            $options = get_option('edlink_options');
            ?>
            <input type="checkbox" name="edlink_options[edlink_sso_enable]" value="1" <?php checked(1, isset($options['edlink_sso_enable']) ? $options['edlink_sso_enable'] : 0, true); ?> />
            <label for="edlink_sso_enable">Enable Edlink SSO</label>
            <?php
        }

        function count_people_by_roles_from_edlink($access_token, $value) {
            $count = 0;
            $next = null;
            $api_url = 'https://ed.link/api/v2/graph/people';

            do {
                // Construct URL with pagination and $first
                // Build the query parameters
                $params = array(
                    '$filter' => json_encode(array(
                        'roles' => array(
                            array(
                                'operator' => 'equals',
                                'value' => $value
                            )
                        )
                    )),
                    '$first' => 10000
                );

                // Add the query parameters to the URL
                $url = add_query_arg($params, $api_url);
                
                //$url = $api_url . '?$first=' . $first . '&$filter=' .$filter;
                if ($next) {
                    // Parse the URL to extract the query part
                    $parsed_url = parse_url($next);

                    // Extract the query string part and parse it into an array
                    $query_string = $parsed_url['query'];

                    parse_str($query_string, $query_params);

                    // Get the value of $cursor
                    $cursor_value = isset($query_params['$cursor']) ? $query_params['$cursor'] : null;
                    $url .= '&cursor=' . $cursor_value;            
                }

                // Prepare request headers
                $args = array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token
                    )
                );

                // Make API request
                $response = wp_remote_get( $url, $args );

                if ( is_wp_error( $response ) ) {
                    return 'Error: ' . $response->get_error_message().'<br/> Please refresh the page again!';
                }

                // Parse the response
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( !empty( $data['$data'] ) ) {
                    // Count current page's $data
                    $count += count( $data['$data'] );
                }

                // Update next page token for the loop
                $next = isset($data['$next']) ? $data['$next'] : null;

            } while ( $next );  // Continue loop until there are no more pages

            // Return total count of people    
            return $count;
        }

        function count_schools_from_edlink($access_token, $value) {
            $count = 0;
            $next = null;
            $api_url = 'https://ed.link/api/v2/graph/schools';

            do {
                // Construct URL with pagination and $first
                // Build the query parameters
                $params = array(            
                    '$first' => 10000
                );

                // Add the query parameters to the URL
                $url = add_query_arg($params, $api_url);
                
                //$url = $api_url . '?$first=' . $first . '&$filter=' .$filter;
                if ($next) {
                    // Parse the URL to extract the query part
                    $parsed_url = parse_url($next);

                    // Extract the query string part and parse it into an array
                    $query_string = $parsed_url['query'];

                    parse_str($query_string, $query_params);

                    // Get the value of $cursor
                    $cursor_value = isset($query_params['$cursor']) ? $query_params['$cursor'] : null;
                    $url .= '&cursor=' . $cursor_value;            
                }

                // Prepare request headers
                $args = array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token
                    )
                );

                // Make API request
                $response = wp_remote_get( $url, $args );

                if ( is_wp_error( $response ) ) {
                    return 'Error: ' . $response->get_error_message().'<br/> Please refresh the page again!';
                }

                // Parse the response
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( !empty( $data['$data'] ) ) {
                    // Count current page's $data
                    $count += count( $data['$data'] );
                }

                // Update next page token for the loop
                $next = isset($data['$next']) ? $data['$next'] : null;

            } while ( $next );  // Continue loop until there are no more pages

            // Return total count of people    
            return $count;
        }

        function get_schools_list_from_edlink($access_token) {
            $api_url = 'https://ed.link/api/v2/graph/schools';
            $error = '';
            $data = [];
            // Prepare request headers
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                )
            );

            // Make API request
            $response = wp_remote_get( $api_url, $args );

            if ( is_wp_error( $response ) ) {
                $error = 'Error: ' . $response->get_error_message().'<br/> Please refresh the page again!';
            }

            // Parse the response
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( !empty( $data['$data'] ) ) {        
                $data = $data['$data'];
            }
            return array('error' => $error, 'schools' => $data);   
        }
    }

    /**
     * Register the "Curriki Learn" top-level admin menu and the
     * "Workbooks" submenu page.
     * Hooked to admin_menu via Tiny_LXP_Platform::define_admin_hooks().
     */
    public function register_curriki_learn_menu() {
        add_menu_page(
            __( 'Curriki Learn', 'tiny-lxp-platform' ),
            __( 'Curriki Learn', 'tiny-lxp-platform' ),
            'manage_options',
            'curriki-learn',
            array( $this, 'workbooks_page' ),
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'curriki-learn',
            __( 'Workbooks', 'tiny-lxp-platform' ),
            __( 'Workbooks', 'tiny-lxp-platform' ),
            'manage_options',
            'curriki-learn-workbooks',
            array( $this, 'workbooks_page' )
        );

		add_submenu_page(
			'curriki-learn',
            __( 'Remotion Lambda Settings', 'tiny-lxp-platform' ),
            __( 'Remotion Lambda Settings', 'tiny-lxp-platform' ),
            'manage_options',
            'curriki-learn-remotion-settings',
            array( $this, 'remotion_settings_page_html' )
        );

        add_submenu_page(
            'curriki-learn',
			__( 'Block Reference', 'tiny-lxp-platform' ),
			__( 'Block Reference', 'tiny-lxp-platform' ),
			'manage_options',
			'curriki-learn-block-reference',
			array( $this, 'block_reference_page' )
		);

        add_submenu_page(
            'curriki-learn',
            __( 'Video Layout Reference', 'tiny-lxp-platform' ),
            __( 'Video Layout Reference', 'tiny-lxp-platform' ),
            'manage_options',
            'curriki-learn-video-layouts',
            array( $this, 'video_layouts_reference_page' )
        );

        add_submenu_page(
            'curriki-learn',
            __( 'Teacher Signup', 'tiny-lxp-platform' ),
            __( 'Teacher Signup', 'tiny-lxp-platform' ),
            'manage_options',
            'curriki-learn-teacher-signup',
            array( $this, 'teacher_signup_settings_page_html' )
        );

        // Remove the auto-created duplicate top-level submenu item.
        remove_submenu_page( 'curriki-learn', 'curriki-learn' );
    }

    /**
     * Get the LearnPress courses that are relevant to a user's workbook.
     *
     * @param int $user_id Selected user ID.
     * @return array<int, array<string, mixed>>
     */
    private function get_workbook_courses_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return array();
        }

        if ( ! class_exists( 'LP_User_Items_DB' ) || ! class_exists( 'LP_User_Items_Filter' ) ) {
            return array();
        }

        $lp_user_items_db = LP_User_Items_DB::getInstance();
        $filter           = new LP_User_Items_Filter();
        $filter->user_id  = $user_id;
        $filter->statues  = array( LP_COURSE_ENROLLED, LP_COURSE_FINISHED );
        $total_rows       = 0;
        $results          = $lp_user_items_db->get_user_courses( $filter, $total_rows );
        $course_ids       = is_array( $results ) ? array_map( 'absint', wp_list_pluck( $results, 'item_id' ) ) : array();

        if ( empty( $course_ids ) ) {
            return array();
        }

        $posts   = get_posts(
            array(
                'post_type'      => 'lp_course',
                'post__in'       => $course_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            )
        );
        $courses = array();

        foreach ( $posts as $post ) {
            $courses[] = array(
                'id'    => (int) $post->ID,
                'title' => (string) $post->post_title,
                'slug'  => (string) $post->post_name,
            );
        }

        return $courses;
    }

    /**
     * Build the frontend workbook URL for a selected user and course.
     *
     * @param array<string, mixed> $course Course data with id and slug keys.
     * @param int                  $user_id Selected user ID.
     * @return string
     */
    private function get_admin_workbook_url( $course, $user_id ) {
        $user_id   = absint( $user_id );
        $course_id = isset( $course['id'] ) ? absint( $course['id'] ) : 0;
        $slug      = isset( $course['slug'] ) ? sanitize_title( (string) $course['slug'] ) : '';

        if ( '' !== $slug ) {
            return add_query_arg( 'view_user_id', $user_id, home_url( '/courses/' . $slug . '/learner-workbook/' ) );
        }

        return add_query_arg(
            array(
                'course_id'     => $course_id,
                'view_user_id'  => $user_id,
            ),
            home_url( '/capstone-journal/' )
        );
    }

    /**
     * Render the admin Workbooks page.
     */
    public function workbooks_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'tiny-lxp-platform' ) );
        }

        $selected_user_id = absint( isset( $_GET['workbook_user_id'] ) ? $_GET['workbook_user_id'] : 0 );
        $selected_user    = $selected_user_id > 0 ? get_userdata( $selected_user_id ) : false;
        $users            = get_users(
            array(
                'orderby' => 'display_name',
                'order'   => 'ASC',
            )
        );
        $courses          = $selected_user ? $this->get_workbook_courses_for_user( $selected_user_id ) : array();

        foreach ( $courses as &$course ) {
            $course['workbook_url'] = $this->get_admin_workbook_url( $course, $selected_user_id );
        }
        unset( $course );

        include plugin_dir_path( __FILE__ ) . 'partials/workbooks-admin.php';
    }

    /**
     * Render the block reference page.
     */
    public function block_reference_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'tiny-lxp-platform' ) );
        }

        include plugin_dir_path( __FILE__ ) . 'partials/block-reference-admin.php';
    }

    /**
     * Render the video layout reference page.
     */
    public function video_layouts_reference_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'tiny-lxp-platform' ) );
        }

        include plugin_dir_path( __FILE__ ) . 'partials/video-layouts-reference-admin.php';
    }

    /**
     * Render the Workbook Submissions admin page (list or detail view).
     */
    public function workbook_submissions_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'tiny-lxp-platform' ) );
        }

        $view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';

        if ( 'detail' === $view ) {
            $id = absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 );

            require_once plugin_dir_path( __FILE__ ) . '../lms/repositories/class-workbook-submission-repository.php';
            $repo       = new TL_Workbook_Submission_Repository();
            $submission = $id > 0 ? $repo->get_by_id( $id ) : null;

            // Attach joined display fields if row found.
            if ( $submission ) {
                $user           = get_userdata( (int) $submission->user_id );
                $submission->display_name = $user ? $user->display_name : '';
                $submission->user_email   = $user ? $user->user_email   : '';
                $lesson_post              = get_post( (int) $submission->lesson_id );
                $course_post              = get_post( (int) $submission->course_id );
                $submission->lesson_title = $lesson_post ? $lesson_post->post_title : '';
                $submission->course_title = $course_post ? $course_post->post_title : '';
            }

            include plugin_dir_path( __FILE__ ) . 'partials/workbook-submission-detail-admin.php';
            return;
        }

        // List view.
        require_once plugin_dir_path( __FILE__ ) . '../lms/repositories/class-workbook-submission-repository.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-workbook-submission-list-table.php';

        $repo       = new TL_Workbook_Submission_Repository();
        $list_table = new TL_Workbook_Submission_List_Table( $repo );
        $list_table->prepare_items();

        include plugin_dir_path( __FILE__ ) . 'partials/workbook-submissions-admin.php';
    }

    /**
     * Render the Capstone Submissions admin page (list or detail view).
     */
    public function capstone_submissions_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'tiny-lxp-platform' ) );
        }

        $view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';

        if ( 'detail' === $view ) {
            $id = absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 );

            require_once plugin_dir_path( __FILE__ ) . '../lms/repositories/class-capstone-submission-repository.php';
            $repo       = new TL_Capstone_Submission_Repository();
            $submission = $id > 0 ? $repo->get_by_id( $id ) : null;

            if ( $submission ) {
                $user           = get_userdata( (int) $submission->user_id );
                $submission->display_name = $user ? $user->display_name : '';
                $submission->user_email   = $user ? $user->user_email   : '';
                $lesson_post              = get_post( (int) $submission->lesson_id );
                $course_post              = get_post( (int) $submission->course_id );
                $submission->lesson_title = $lesson_post ? $lesson_post->post_title : '';
                $submission->course_title = $course_post ? $course_post->post_title : '';
            }

            include plugin_dir_path( __FILE__ ) . 'partials/capstone-submission-detail-admin.php';
            return;
        }

        // List view.
        require_once plugin_dir_path( __FILE__ ) . '../lms/repositories/class-capstone-submission-repository.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-capstone-submission-list-table.php';

        $repo       = new TL_Capstone_Submission_Repository();
        $list_table = new TL_Capstone_Submission_List_Table( $repo );
        $list_table->prepare_items();

        include plugin_dir_path( __FILE__ ) . 'partials/capstone-submissions-admin.php';
    }

    public function network_options_page()
    {
        $menu = add_submenu_page('settings.php', 'Network Tiny LXP Tools', 'Network Tiny LXP Tools', 'manage_options',
            Tiny_LXP_Platform::get_plugin_name(), array($this, 'view_page_html'));
        add_action("load-{$menu}", array($this, 'load_tools_table'));

        $submenu = add_submenu_page(null, 'Add Network Tiny LXP Tool', 'Add New', 'manage_options',
            Tiny_LXP_Platform::get_plugin_name() . '-edit', array($this, 'edit_page_html'));
        add_action("load-{$submenu}", array($this, 'load_submenu'));

        $submenu = add_submenu_page(null, 'Network Tiny LXP Platform Settings', 'Network Settings', 'manage_options',
            Tiny_LXP_Platform::get_plugin_name() . '-settings', array($this, 'options_page_html')
        );
        add_action("load-{$submenu}", array($this, 'load_submenu'));
        add_action('network_admin_edit_' . Tiny_LXP_Platform::get_plugin_name() . '-settings', array($this, 'save_network_options'));
    }

    public function save_network_options()
    {
        $rawoptions = $_POST[Tiny_LXP_Platform::get_settings_name()];
        $options = array();
        foreach ($rawoptions as $option => $value) {
            $option = sanitize_text_field($option);
            switch ($option) {
                case 'privatekey':
                    $value = sanitize_textarea_field($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
                    break;
            }
            $options[$option] = $value;
        }
        if (is_multisite()) {
            update_site_option(Tiny_LXP_Platform::get_settings_name(), $options);
        } else {
            update_option(Tiny_LXP_Platform::get_settings_name(), $options);
        }
        add_action('all_admin_notices', 'save_network_notice_success');
        wp_redirect('settings.php?page=' . Tiny_LXP_Platform::get_plugin_name() . '-settings');
        exit;
    }

    public function save_network_notice_success()
    {
        echo('    <div class="notice notice-success is-dismissible">' . "\n");
        echo('        <p>' . esc_html__('Settings updated.', Tiny_LXP_Platform::get_plugin_name()) . '</p>' . "\n");
        echo('    </div>' . "\n");
    }

    public function load_tools_table()
    {
        $screen = get_current_screen();
        add_filter("manage_{$screen->id}_columns", array('Tiny_LXP_Platform_Tool_List_Table', 'define_columns'), 10, 0);
        add_screen_option('per_page',
            array('label' => __('Tools', Tiny_LXP_Platform::get_plugin_name()), 'default' => 5, 'option' => Tiny_LXP_Platform::get_plugin_name() . '-tool_per_page'));
        $screen->add_help_tab(array(
            'id' => Tiny_LXP_Platform::get_plugin_name() . '-display',
            'title' => __('Screen Display', Tiny_LXP_Platform::get_plugin_name()),
            'content' => '<p>' . __('You can select which columns to display and the number of Tiny LXP Tools to list per screen using the Screen Options tab.',
                Tiny_LXP_Platform::get_plugin_name()) . '</p>'
        ));
    }

    public function load_submenu()
    {
        add_filter('submenu_file', array($this, 'submenu_file'), 10, 2);
    }

    function submenu_file($submenu_file, $parent_file)
    {
// Ensure plugin remains highlighted as current in menu
        if ((($parent_file === 'options-general.php') || ($parent_file === 'settings.php')) && empty($submenu_file)) {
            $submenu_file = Tiny_LXP_Platform::get_plugin_name();
        }

        return $submenu_file;
    }

    public function settings_init()
    {
        register_setting(Tiny_LXP_Platform::get_plugin_name(), Tiny_LXP_Platform::get_settings_name());
        $options = Tiny_LXP_Platform_Tool::getOptions();

        add_settings_section(
            'section_general', __('General Settings', Tiny_LXP_Platform::get_plugin_name()), array($this, 'section_general'),
            Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field('field_debug', __('Debug mode?', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_checkbox'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_general',
            array('class' => 'row', 'label_for' => 'id_debug', 'name' => 'debug', 'options' => $options));
        add_settings_field('field_uninstall', __('Delete data on uninstall?', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'field_checkbox'), Tiny_LXP_Platform::get_plugin_name(), 'section_general',
            array('class' => 'row', 'label_for' => 'id_uninstall', 'name' => 'uninstall', 'options' => $options));
        add_settings_field('field_platformguid', __('Platform GUID', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_text'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_general',
            array('class' => 'row', 'label_for' => 'id_platformguid', 'name' => 'platformguid', 'options' => $options));

        add_settings_section(
            'section_privacy', __('Privacy Settings', Tiny_LXP_Platform::get_plugin_name()), array($this, 'section_privacy'),
            Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field('field_name', __('Send user\'s name?', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_checkbox'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_privacy',
            array('class' => 'row', 'label_for' => 'id_sendusername', 'name' => 'sendusername', 'options' => $options));
        add_settings_field('field_id', __('Send user\'s ID?', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_checkbox'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_privacy',
            array('class' => 'row', 'label_for' => 'id_senduserid', 'name' => 'senduserid', 'options' => $options));
        add_settings_field('field_email', __('Send user\'s email?', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'field_checkbox'), Tiny_LXP_Platform::get_plugin_name(), 'section_privacy',
            array('class' => 'row', 'label_for' => 'id_senduseremail', 'name' => 'senduseremail', 'options' => $options));
        add_settings_field('field_role', __('Send user\'s role?', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_checkbox'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_privacy',
            array('class' => 'row', 'label_for' => 'id_senduserrole', 'name' => 'senduserrole', 'options' => $options));
        add_settings_field('field_username', __('Send user\'s username?', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'field_checkbox'), Tiny_LXP_Platform::get_plugin_name(), 'section_privacy',
            array('class' => 'row', 'label_for' => 'id_senduserusername', 'name' => 'senduserusername', 'options' => $options));

        add_settings_section(
            'section_presentation', __('Presentation Settings', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'section_presentation'), Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field('field_target', __('Presentation target', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_target'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_presentation',
            array('class' => 'row', 'label_for' => 'id_presentationtarget', 'name' => 'presentationtarget', 'options' => $options));
        add_settings_field('field_width', __('Width of pop-up window or iframe', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'field_text'), Tiny_LXP_Platform::get_plugin_name(), 'section_presentation',
            array('class' => 'row', 'label_for' => 'id_presentationwidth', 'name' => 'presentationwidth', 'options' => $options));
        add_settings_field('field_height', __('Height of pop-up window or iframe', Tiny_LXP_Platform::get_plugin_name()),
            array($this, 'field_text'), Tiny_LXP_Platform::get_plugin_name(), 'section_presentation',
            array('class' => 'row', 'label_for' => 'id_presentationheight', 'name' => 'presentationheight', 'options' => $options));

        add_settings_section(
            'section_security', __('Security Settings', Tiny_LXP_Platform::get_plugin_name()), array($this, 'section_security'),
            Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field('field_kid', __('Key ID', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_text'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_security',
            array('class' => 'row', 'label_for' => 'id_kid', 'name' => 'kid', 'options' => $options));
        add_settings_field('field_privatekey', __('Private key', Tiny_LXP_Platform::get_plugin_name()), array($this, 'field_textarea'),
            Tiny_LXP_Platform::get_plugin_name(), 'section_security',
            array('class' => 'row', 'label_for' => 'id_privatekey', 'name' => 'privatekey', 'options' => $options));
        // Register Remotion Lambda settings
        register_setting( 'tiny-lxp-platform', 'tl_remotion_region', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'us-east-2' ) );
        register_setting( 'tiny-lxp-platform', 'tl_remotion_function_name', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( 'tiny-lxp-platform', 'tl_remotion_serve_url', array( 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );

        add_settings_section(
            'section_remotion', __( 'Remotion Lambda Settings', Tiny_LXP_Platform::get_plugin_name() ), array( $this, 'section_remotion' ),
            Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field( 'field_remotion_region', __( 'AWS Region', Tiny_LXP_Platform::get_plugin_name() ),
            array( $this, 'field_remotion_region' ), Tiny_LXP_Platform::get_plugin_name(), 'section_remotion',
            array( 'class' => 'row', 'label_for' => 'tl_remotion_region' ) );
        add_settings_field( 'field_remotion_function_name', __( 'Lambda Function Name', Tiny_LXP_Platform::get_plugin_name() ),
            array( $this, 'field_remotion_function_name' ), Tiny_LXP_Platform::get_plugin_name(), 'section_remotion',
            array( 'class' => 'row', 'label_for' => 'tl_remotion_function_name' ) );
        add_settings_field( 'field_remotion_serve_url', __( 'Remotion Serve URL', Tiny_LXP_Platform::get_plugin_name() ),
            array( $this, 'field_remotion_serve_url' ), Tiny_LXP_Platform::get_plugin_name(), 'section_remotion',
            array( 'class' => 'row', 'label_for' => 'tl_remotion_serve_url' ) );

        // Register student import settings
        register_setting( 'tiny-lxp-platform', 'tl_student_default_password', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        add_settings_section(
            'section_student_import',
            __( 'Student Import Settings', Tiny_LXP_Platform::get_plugin_name() ),
            null,
            Tiny_LXP_Platform::get_plugin_name()
        );
        add_settings_field(
            'field_student_default_password',
            __( 'Default Student Password', Tiny_LXP_Platform::get_plugin_name() ),
            [ $this, 'field_student_default_password' ],
            Tiny_LXP_Platform::get_plugin_name(),
            'section_student_import',
            [ 'class' => 'row', 'label_for' => 'tl_student_default_password' ]
        );

        // Register teacher self-signup placement settings.
        // Every self-signed-up teacher is attached to this one school; the
        // public signup form deliberately does not let the caller choose.
        // See Rest_Lxp_Teacher_Signup.
        register_setting( 'tiny-lxp-platform', 'tl_signup_district_id', [
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ] );
        register_setting( 'tiny-lxp-platform', 'tl_signup_school_id', [
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ] );

        // Register a new setting for 'edlink' options
        register_setting('edlink_options_group', 'edlink_options');
        
        // Add a new section for the settings page
        add_settings_section(
            'edlink_settings_section',      // Section ID
            'Edlink API Settings',          // Section Title
            'edlink_settings_section_cb',   // Callback function to explain the section
            'edlink_options'                // Page slug where the section will appear
        );
        
        // Add individual fields (Application ID, Secret, etc.) to the section
        add_settings_field(
            'edlink_application_id',               // Field ID
            'Application ID',                      // Field Title
            'edlink_application_id_render',        // Callback function to render the field
            'edlink_options',               // Page slug where the field will appear
            'edlink_settings_section'       // Section ID
        );
        
        add_settings_field(
            'edlink_application_secrets',
            'Application Secrets',
            'edlink_application_secrets_render',
            'edlink_options',
            'edlink_settings_section'
        );

        // Add a new checkbox field for 'edlink_sso_enable'
        add_settings_field(
            'edlink_sso_enable',
            'Enable Edlink SSO',
            'edlink_sso_enable_render',
            'edlink_options',
            'edlink_settings_section'
        );
    }

    public function section_general()
    {

    }

    public function section_privacy()
    {

    }

    public function section_presentation()
    {

    }

    public function section_security()
    {

    }

    public function section_remotion()
    {
        echo '<p>' . esc_html__( 'Configure the Remotion Lambda deployment that renders AI lesson videos. Run the deploy commands in remotion-video-service/ to obtain these values.', 'tiny-lxp-platform' ) . '</p>';
    }

    public function field_remotion_region()
    {
        $val = get_option( 'tl_remotion_region', 'us-east-2' );
        echo '<input id="tl_remotion_region" type="text" name="tl_remotion_region" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="us-east-2" />';
        echo '<p class="description">' . esc_html__( 'AWS region where the Lambda function is deployed (e.g. us-east-2).', 'tiny-lxp-platform' ) . '</p>';
    }

    public function field_remotion_function_name()
    {
        $val = get_option( 'tl_remotion_function_name', '' );
        echo '<input id="tl_remotion_function_name" type="text" name="tl_remotion_function_name" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="remotion-render-4-0-467-mem2048mb-disk2048mb-120sec" />';
        echo '<p class="description">' . esc_html__( 'Function name returned by: npx remotion lambda functions deploy', 'tiny-lxp-platform' ) . '</p>';
    }

    public function field_remotion_serve_url()
    {
        $val = get_option( 'tl_remotion_serve_url', '' );
        echo '<input id="tl_remotion_serve_url" type="url" name="tl_remotion_serve_url" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="https://s3.amazonaws.com/remotionlambda-xxx/sites/currikilearnai/index.html" />';
        echo '<p class="description">' . esc_html__( 'Serve URL returned by: npx remotion lambda sites create src/index.ts --site-name=currikilearnai', 'tiny-lxp-platform' ) . '</p>';
    }

    public function field_student_default_password( $args ) {
        $val = get_option( 'tl_student_default_password', '' );
        ?>
        <input type="text" id="tl_student_default_password"
               name="tl_student_default_password"
               value="<?php echo esc_attr( $val ); ?>"
               class="regular-text" autocomplete="off" />
        <p class="description">
            <?php esc_html_e( 'Applied to every student created via CSV import. Also stored per-student in their admin record.', 'tiny-lxp-platform' ); ?>
        </p>
        <?php
    }

    public function field_checkbox($args)
    {
        echo('<input id="' . esc_attr($args['label_for']) . '" type="checkbox" aria-required="false" value="true" name="' . Tiny_LXP_Platform::get_settings_name() . '[' . esc_attr($args['name']) . ']"' .
        checked(isset($args['options'][$args['name']]) && ($args['options'][$args['name']] === 'true'), true, false) . '>' . "\n");
    }

    public function field_text($args)
    {
        echo('<input id="' . esc_attr($args['label_for']) . '" type="text" aria-required="false" value="');
        if (isset($args['options'][$args['name']])) {
            echo(esc_attr($args['options'][$args['name']]));
        }
        echo('" name="' . Tiny_LXP_Platform::get_settings_name() . '[' . esc_attr($args['name']) . ']">' . "\n");
    }

    public function field_textarea($args)
    {
        echo('<textarea id=" ' . esc_attr($args['label_for']) . '" name="' . Tiny_LXP_Platform::get_settings_name() . '[' . esc_attr($args['name']) . ']" class="regular-text">');
        if (isset($args['options'][$args['name']])) {
            echo($args['options'][$args['name']]);
        }
        echo('</textarea>' . "\n");
    }

    public function field_target($args)
    {
        echo('<select id="' . esc_attr($args['label_for']) . '" name="' . Tiny_LXP_Platform::get_settings_name() . '[' . esc_attr($args['name']) . ']">' . "\n");
        echo('  <option value="window"' . selected(isset($args['options'][$args['name']]) && ($args['options'][$args['name']] === 'window'),
            true, false) . '>New window</option>' . "\n");
        echo('  <option value="popup"' . selected(isset($args['options'][$args['name']]) && ($args['options'][$args['name']] === 'popup'),
            true, false) . '>Pop-up window</option>' . "\n");
        echo('  <option value="iframe"' . selected(isset($args['options'][$args['name']]) && ($args['options'][$args['name']] === 'iframe'),
            true, false) . '>iFrame</option>' . "\n");
        echo('  <option value="embed"' . selected(isset($args['options'][$args['name']]) && ($args['options'][$args['name']] === 'embed'),
            true, false) . '>Embed</option>' . "\n");
        echo('</select>' . "\n");
    }

    public function options_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors(Tiny_LXP_Platform::get_plugin_name() . '_messages');

        require_once(plugin_dir_path(dirname(__FILE__)) . 'admin/partials/tiny-lxp-platform-admin-settings.php');
    }

    public function remotion_settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors();

        echo('<div class="wrap">' . "\n");
        echo('  <h1>' . esc_html__( 'Remotion Lambda Settings', 'tiny-lxp-platform' ) . '</h1>' . "\n");
        echo('  <form action="' . esc_url( 'options.php' ) . '" method="post">' . "\n");

        settings_fields('tiny-lxp-platform');

        echo('    <table class="form-table" role="presentation">' . "\n");
        echo('      <tbody>' . "\n");

        echo('        <tr>' . "\n");
        echo('          <th scope="row" colspan="2">');
        $this->section_remotion();
        echo('          </th>' . "\n");
        echo('        </tr>' . "\n");

        echo('        <tr>' . "\n");
        echo('          <th scope="row"><label for="tl_remotion_region">' . esc_html__( 'AWS Region', 'tiny-lxp-platform' ) . '</label></th>' . "\n");
        echo('          <td>');
        $this->field_remotion_region();
        echo('          </td>' . "\n");
        echo('        </tr>' . "\n");

        echo('        <tr>' . "\n");
        echo('          <th scope="row"><label for="tl_remotion_function_name">' . esc_html__( 'Lambda Function Name', 'tiny-lxp-platform' ) . '</label></th>' . "\n");
        echo('          <td>');
        $this->field_remotion_function_name();
        echo('          </td>' . "\n");
        echo('        </tr>' . "\n");

        echo('        <tr>' . "\n");
        echo('          <th scope="row"><label for="tl_remotion_serve_url">' . esc_html__( 'Remotion Serve URL', 'tiny-lxp-platform' ) . '</label></th>' . "\n");
        echo('          <td>');
        $this->field_remotion_serve_url();
        echo('          </td>' . "\n");
        echo('        </tr>' . "\n");

        echo('      </tbody>' . "\n");
        echo('    </table>' . "\n");

        submit_button();

        echo('  </form>' . "\n");
        echo('</div>' . "\n");
    }

    /**
     * "Curriki Learn -> Teacher Signup" settings page.
     *
     * Picks the district and school that every self-signed-up teacher is
     * attached to. Hand-built like remotion_settings_page_html() rather than
     * going through do_settings_sections(), because the school select has to be
     * filtered by the chosen district client-side.
     *
     * @see Rest_Lxp_Teacher_Signup
     */
    public function teacher_signup_settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors();

        $selected_district = (int) get_option('tl_signup_district_id', 0);
        $selected_school   = (int) get_option('tl_signup_school_id', 0);

        $districts = get_posts(array(
            'post_type'      => TL_DISTRICT_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $schools = get_posts(array(
            'post_type'      => TL_SCHOOL_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Map each school to its district so the JS below can filter the list,
        // and so we can warn about schools that have no district at all.
        $school_district = array();
        foreach ($schools as $school) {
            $school_district[$school->ID] = (int) get_post_meta($school->ID, 'lxp_school_district_id', true);
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Teacher Signup', 'tiny-lxp-platform'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Teachers who create their own account through the LXP Teacher Signup widget are attached to the school selected here. The public signup form does not ask which school — this is the only place it is decided.', 'tiny-lxp-platform'); ?>
            </p>

            <form action="<?php echo esc_url('options.php'); ?>" method="post">
                <?php settings_fields('tiny-lxp-platform'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="tl_signup_district_id"><?php echo esc_html__('District', 'tiny-lxp-platform'); ?></label></th>
                            <td>
                                <select id="tl_signup_district_id" name="tl_signup_district_id">
                                    <option value="0"><?php echo esc_html__('— Select a district —', 'tiny-lxp-platform'); ?></option>
                                    <?php foreach ($districts as $district) : ?>
                                        <option value="<?php echo esc_attr($district->ID); ?>" <?php selected($selected_district, $district->ID); ?>>
                                            <?php echo esc_html($district->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Narrows the school list below.', 'tiny-lxp-platform'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tl_signup_school_id"><?php echo esc_html__('School', 'tiny-lxp-platform'); ?></label></th>
                            <td>
                                <select id="tl_signup_school_id" name="tl_signup_school_id">
                                    <option value="0"><?php echo esc_html__('— Select a school —', 'tiny-lxp-platform'); ?></option>
                                    <?php foreach ($schools as $school) : ?>
                                        <option value="<?php echo esc_attr($school->ID); ?>"
                                                data-district="<?php echo esc_attr($school_district[$school->ID]); ?>"
                                                <?php selected($selected_school, $school->ID); ?>>
                                            <?php echo esc_html($school->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Every teacher who signs themselves up joins this school.', 'tiny-lxp-platform'); ?></p>
                                <?php
                                // A school with no district makes /classes fatal for the
                                // teacher who lands on it — that template reads the
                                // district post's meta with no null guard. Refuse loudly
                                // here rather than let someone sign up into a dead end.
                                if ($selected_school && empty($school_district[$selected_school])) :
                                    ?>
                                    <div class="notice notice-error inline" style="margin-top:8px">
                                        <p><strong><?php echo esc_html__('This school has no district assigned.', 'tiny-lxp-platform'); ?></strong>
                                        <?php echo esc_html__('Teacher signup will be refused until you set one on the school record — a teacher attached to a district-less school cannot open the Classes page.', 'tiny-lxp-platform'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        (function () {
            var districtSelect = document.getElementById('tl_signup_district_id');
            var schoolSelect   = document.getElementById('tl_signup_school_id');
            if (!districtSelect || !schoolSelect) { return; }

            // Options are hidden rather than removed so switching districts back
            // and forth does not lose the list.
            function filterSchools() {
                var districtId = districtSelect.value;
                var selectedStillVisible = false;

                Array.prototype.forEach.call(schoolSelect.options, function (opt) {
                    if (!opt.value || opt.value === '0') { return; }
                    var match = (districtId === '0') || (opt.getAttribute('data-district') === districtId);
                    opt.hidden = !match;
                    opt.disabled = !match;
                    if (match && opt.selected) { selectedStillVisible = true; }
                });

                // Selecting a district that excludes the current school would
                // otherwise leave an invisible-but-selected value in the form.
                if (!selectedStillVisible) { schoolSelect.value = '0'; }
            }

            districtSelect.addEventListener('change', filterSchools);
            filterSchools();
        })();
        </script>
        <?php
    }

    public function view_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/tiny-lxp-platform-admin-view.php';
    }

    public function edit_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (empty($_REQUEST['tool'])) {
            $tool = new Tiny_LXP_Platform_Tool(Tiny_LXP_Platform::$tinyLxpPlatformDataConnector);
        } else {
            $tool = Tiny_LXP_Platform_Tool::fromRecordId(intval(sanitize_text_field($_REQUEST['tool'])),
                    Tiny_LXP_Platform::$tinyLxpPlatformDataConnector);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], Tiny_LXP_Platform::get_plugin_name() . '-nonce')) {
                add_action('all_admin_notices', array($this, 'error_update'));
            } else {
                $this->update_tool($tool);
            }
        }
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/tiny-lxp-platform-admin-edit.php';
    }

    function error_update()
    {
        $allowed = array('em' => array());
        echo('  <div class="notice notice-error">' . "\n");
        echo('    <p>' . esc_html__('Unable to save the changes.', Tiny_LXP_Platform::get_plugin_name()) . '</p>' . "\n");
        echo('  </div>' . "\n");
    }

    private function update_tool($tool)
    {
        $tool->name = sanitize_text_field($_POST['name']);
        $tool->code = sanitize_text_field($_POST['code']);
        $tool->enabled = !empty($_POST['enabled']) && (sanitize_text_field($_POST['enabled']) === 'true');
        $tool->debugMode = !empty($_POST['debugmode']) && (sanitize_text_field($_POST['debugmode']) === 'true');
        $tool->setSetting('sendUserName',
            (!empty($_POST['sendusername']) && (sanitize_text_field($_POST['sendusername']) === 'true')) ? 'true' : null);
        $tool->setSetting('sendUserId',
            (!empty($_POST['senduserid']) && (sanitize_text_field($_POST['senduserid'] === 'true'))) ? 'true' : null);
        $tool->setSetting('sendUserEmail',
            (!empty($_POST['senduseremail']) && (sanitize_text_field($_POST['senduseremail'] === 'true'))) ? 'true' : null);
        $tool->setSetting('sendUserRole',
            (!empty($_POST['senduserrole']) && (sanitize_text_field($_POST['senduserrole'] === 'true'))) ? 'true' : null);
        $tool->setSetting('sendUserUsername',
            (!empty($_POST['senduserusername']) && (sanitize_text_field($_POST['senduserusername'] === 'true'))) ? 'true' : null);
        $tool->setSetting('presentationTarget', sanitize_text_field($_POST['presentationtarget']));
        $tool->setSetting('presentationWidth', sanitize_text_field($_POST['presentationwidth']));
        $tool->setSetting('presentationHeight', sanitize_text_field($_POST['presentationheight']));
        $tool->messageUrl = esc_url_raw($_POST['messageurl']);
        $tool->useContentItem = !empty($_POST['usecontentitem']) && (sanitize_text_field($_POST['usecontentitem']) === 'true');
        $tool->contentItemUrl = esc_url_raw($_POST['contentitemurl']);
        $tool->setSetting('custom', str_replace("\r\n", '&#13;&#10;', sanitize_textarea_field($_POST['custom'])));
        $tool->setKey(sanitize_text_field($_POST['consumerkey']));
        $tool->secret = sanitize_text_field($_POST['sharedsecret']);
        $tool->initiateLoginUrl = sanitize_text_field($_POST['initiateloginurl']);
        $redirectionUris = trim(sanitize_textarea_field($_POST['redirectionuris']));
        if (!empty($redirectionUris)) {
            $tool->redirectionUris = explode("\r\n", sanitize_textarea_field($_POST['redirectionuris']));
        } else {
            $tool->redirectionUris = array();
        }
        $tool->jku = sanitize_text_field($_POST['jwksurl']);
        $tool->rsaKey = sanitize_textarea_field($_POST['publickey']);
        if (empty($tool->initiateLoginUrl) || empty($tool->redirectionUris)) {
            $tool->ltiVersion = Util::LTI_VERSION1;
            $tool->signatureMethod = 'HMAC-SHA1';
        } else {
            $tool->ltiVersion = Util::LTI_VERSION1P3;
            $tool->signatureMethod = 'RS256';
        }
        $tool->save();
    }

}
