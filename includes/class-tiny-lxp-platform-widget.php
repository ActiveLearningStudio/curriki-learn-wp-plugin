<?php

/**
 * @since      1.0.0
 * @package    Tiny_LXP_Platform
 * @subpackage Tiny_LXP_Platform/includes
 * @author     Waqar Muneer <waqarmuneer@gmail.com>
 */
class Tiny_LXP_Widget
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

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_elementor_widgets($widgets_manager) {
        // Optional: Check if Elementor is actually loaded
        if (!did_action('elementor/loaded')) {
            return;
        }

        require_once plugin_dir_path(dirname( __FILE__ )). 'lms/templates/tinyLxpTheme/lxp/functions.php';
        require_once( __DIR__ . '/widgets/lxp-course-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-course-html-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-lesson-html-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-reports-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-student-assignment-calandar.php' );
        require_once( __DIR__ . '/widgets/lxp-student-progress-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-student-grades-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-student-grade-summary-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-student-courses-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-student-access-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-class-join-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-bookmark-prompt-widget.php' );
        require_once( __DIR__ . '/widgets/lxp-teacher-signup-widget.php' );

        $widgets_manager->register( new \Edudeme\Elementor\LXP_Course_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Course_HTML_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Lesson_HTML_Widget() );
        // assignments list pending, submitted
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Reports_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\Assignment_Calander_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\Student_Progress_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\Student_Grades_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\Student_Grade_Summay_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Student_Courses_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Student_Access_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Class_Join_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Bookmark_Prompt_Widget() );
        $widgets_manager->register( new \Edudeme\Elementor\LXP_Teacher_Signup_Widget() );
    }

    public function widget_dependencies($hook)
    {
        $plugin_path = plugins_url('widgets/assets', __FILE__);
        if (is_page('learner-assignments')) {
            wp_register_style( 'calendar',          plugins_url( 'widgets/assets/style/calendar.css', __FILE__ ) );
            wp_register_style( 'newAssignment',     plugins_url( 'widgets/assets/style/newAssignment.css', __FILE__ ) );
            wp_register_style( 'calendar-style',    plugins_url( 'widgets/assets/style/calendar-style.css', __FILE__ ) );

            // /* JS */
            // Webshim for date input polyfill (if needed)
            wp_enqueue_script('webshim', $plugin_path.'/js/polyfiller.js', [], '5.4.3', true);
            wp_enqueue_script('bootstrap-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js', [], '6.1.15');
            wp_enqueue_script('lxp-calendar', $plugin_path.'/js/custom-calandar.js', [], '1.0.0', true );
            wp_enqueue_script('fullcalendar', $plugin_path.'/js/index.global.min.js', [], '6.1.15', true);

            // Pass PHP data to JS
            $user_id = get_current_user_id();

            wp_localize_script('lxp-calendar', 'LXP_Calendar_Data', [
                'user_id' => $user_id,
                'api_url' => rest_url('lms/v1/assignments/calendar/events'),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]);
        } elseif (is_page('learner')) {
            wp_register_style( 'widget-style-1', 	plugins_url( '/widgets/assets/style/main.css', __FILE__ ) );
            wp_register_style( 'student-dashboard', plugins_url( '/widgets/assets/style/studentDashboard.css', __FILE__ ) );
            wp_register_style( 'lxp-common',        plugins_url( '/widgets/assets/style/common.css', __FILE__ ) );
        }
    }
}