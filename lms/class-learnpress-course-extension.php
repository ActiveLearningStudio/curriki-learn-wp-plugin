<?php

class TL_LearnPress_Course_Extension {

	/**
	 * Register hooks directly (like TL_Post_Type pattern) so they work
	 * regardless of the loader->run() path.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_course_shortcodes' ], 5 );
		add_action( 'init', [ $this, 'register_course_thumbnail_image_size' ] );
		add_filter( 'elementor/widget/render_content', [ $this, 'process_html_widget_shortcodes' ], 10, 2 );
		// Course pages must never be served from a page cache — Elementor Theme Builder
		// uses one shared template for all lp_course posts, so a cached render of
		// Course A gets served for Course B, C, etc. (our shortcodes won't re-fire).
		add_action( 'template_redirect', [ $this, 'disable_page_cache_on_course' ] );
	}

	/**
	 * Register a WP intermediate image size matching LearnPress's expected
	 * course thumbnail dimensions ("course_thumbnail_dimensions" setting,
	 * default 500x300).
	 *
	 * Without this, LearnPress\Models\PostModel::get_image_url( [500, 300] )
	 * generates the "-500x300" file on demand at render time, writing
	 * straight to disk without calling wp_update_attachment_metadata() —
	 * so WP Offload Media (which only hooks wp_update_attachment_metadata)
	 * never syncs that size to S3. Registering it here makes WordPress
	 * generate it at normal upload time instead, through the metadata
	 * pipeline Offload Media already watches.
	 *
	 * crop is hardcoded true to match get_image_url()'s
	 * resize( $w, $h, true ) and produce the "-{w}x{h}" filename it
	 * looks for via file_exists().
	 */
	public function register_course_thumbnail_image_size() {
		$width  = 500;
		$height = 300;

		if ( class_exists( '\LP_Settings' ) ) {
			$size_img_setting = \LP_Settings::get_option( 'course_thumbnail_dimensions', [] );
			$width            = absint( $size_img_setting['width'] ?? 500 ) ?: 500;
			$height           = absint( $size_img_setting['height'] ?? 300 ) ?: 300;
		}

		add_image_size( 'tinylxp_course_thumbnail', $width, $height, true );
	}

	/**
	 * Tell page cache plugins not to cache lp_course pages.
	 *
	 * DONOTCACHEPAGE is respected by WP Super Cache, W3 Total Cache, WP Rocket,
	 * Autoptimize, and most other cache plugins. LiteSpeed Cache requires its own
	 * action hook. Both are no-ops if the respective plugin is not active.
	 */
	public function disable_page_cache_on_course() {
		if ( ! is_singular( LP_COURSE_CPT ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		// LiteSpeed Cache specific — safe no-op if plugin is absent.
		do_action( 'litespeed_control_set_nocache', 'lp_course: dynamic shortcodes per course' );
	}

	public function enqueue_student_course_styles() {
		if (!is_singular(LP_COURSE_CPT) || !is_user_logged_in()) {
			return;
		}

		$userdata = get_userdata(get_current_user_id());
		if (!$userdata || !in_array('lxp_student', (array) $userdata->roles, true)) {
			return;
		}

		$style_url = plugin_dir_url(dirname(__FILE__) . '/../TinyLxp-wp-plugin.php') . 'public/css/lxp-student-course.css';
		wp_enqueue_style('tinylxp-student-course', $style_url, array(), null);
	}

	public static function create_grades_table() {
		global $wpdb;

		$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tiny_lms_grades(
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lesson_id bigint(20) default NULL,
			score FLOAT default NULL,
			user_id bigint(20) default NULL,
			PRIMARY KEY (id)
		)");
	}

	private function add_meta_box( $args = array() ) {
		if ( is_array( $args ) && ! empty( $args ) ) {
			call_user_func_array( 'add_meta_box', $args );
		}
	}

	public function add_meta_boxes() {
		$this->course_outcome_metabox();
		$this->course_audience_metabox();
	}

	public function course_outcome_metabox() {
		$this->add_meta_box( array(
			'lxp-course-outcome',
			esc_html__( 'Course Outcome', 'tiny-lxp-platform' ),
			array( $this, 'course_outcome_metabox_html' ),
			TL_COURSE_CPT,
			'side',
			'default',
		) );
	}

	public function course_outcome_metabox_html( $post = null ) {
		$outcome = '';
		if ( $post && isset( $post->ID ) ) {
			$outcome = get_post_meta( $post->ID, 'lxp_course_outcome', true );
		}

		wp_nonce_field( 'save_lxp_course_outcome', 'lxp_course_outcome_nonce' );
		?>
		<p>
			<label for="lxp_course_outcome"><strong><?php echo esc_html__( 'Outcome text', 'tiny-lxp-platform' ); ?></strong></label>
		</p>
		<textarea id="lxp_course_outcome" name="lxp_course_outcome" rows="4" style="width:100%;" placeholder="<?php echo esc_attr__( 'e.g. Professional Certificate', 'tiny-lxp-platform' ); ?>"><?php echo esc_textarea( $outcome ); ?></textarea>
		<p style="margin-top:8px;color:#666;">
			<?php echo esc_html__( 'Used by the lp_course_outcome token in the Course HTML widget.', 'tiny-lxp-platform' ); ?>
		</p>
		<?php
	}

	public function save_course_outcome_meta( $post_id = null, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || ! isset( $post->post_type ) || $post->post_type !== TL_COURSE_CPT ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['lxp_course_outcome_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lxp_course_outcome_nonce'] ) ), 'save_lxp_course_outcome' ) ) {
			return;
		}

		$outcome = isset( $_POST['lxp_course_outcome'] )
			? sanitize_textarea_field( wp_unslash( $_POST['lxp_course_outcome'] ) )
			: '';

		if ( '' === $outcome ) {
			delete_post_meta( $post_id, 'lxp_course_outcome' );
			return;
		}

		update_post_meta( $post_id, 'lxp_course_outcome', $outcome );
	}

	public function course_audience_metabox() {
		$this->add_meta_box( array(
			'lxp-course-audience',
			esc_html__( 'Curriki Audience', 'tiny-lxp-platform' ),
			array( $this, 'course_audience_metabox_html' ),
			TL_COURSE_CPT,
			'side',
			'default',
		) );
	}

	public function course_audience_metabox_html( $post = null ) {
		$selected = array();
		if ( $post && isset( $post->ID ) ) {
			$terms = wp_get_object_terms( $post->ID, TL_COURSE_AUDIENCE_TAXONOMY, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) ) {
				$selected = $terms;
			}
		}

		wp_nonce_field( 'save_lxp_course_audience', 'lxp_course_audience_nonce' );
		?>
		<p style="margin-top:0;">
			<?php echo esc_html__( 'Who this course is written for.', 'tiny-lxp-platform' ); ?>
		</p>
		<?php foreach ( lxp_get_course_audience_terms() as $term ) : ?>
			<p style="margin:0 0 6px;">
				<label>
					<input type="checkbox" name="lxp_course_audience[]" value="<?php echo esc_attr( $term['slug'] ); ?>" <?php checked( in_array( $term['slug'], $selected, true ) ); ?> />
					<?php echo esc_html( $term['name'] ); ?>
				</label>
			</p>
		<?php endforeach; ?>
		<p style="margin-top:8px;color:#666;">
			<?php echo esc_html__( 'These add a Student or PD label beside the course title when a teacher picks courses for a class. They do not restrict who can assign it — leaving both unticked simply shows no label.', 'tiny-lxp-platform' ); ?>
		</p>
		<?php
	}

	/**
	 * Store the audience terms without disturbing the rest of the course's categories.
	 *
	 * These two terms live in LearnPress's own course_category taxonomy, next to
	 * subject categories such as Math. A plain wp_set_object_terms() call defaults
	 * to $append = false and would wipe every one of those. So: append what was
	 * ticked, then remove only the ones that were unticked. Never a single
	 * replacing call.
	 */
	public function save_course_audience_terms( $post_id = null, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || ! isset( $post->post_type ) || $post->post_type !== TL_COURSE_CPT ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['lxp_course_audience_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lxp_course_audience_nonce'] ) ), 'save_lxp_course_audience' ) ) {
			return;
		}
		if ( ! taxonomy_exists( TL_COURSE_AUDIENCE_TAXONOMY ) ) {
			return;
		}

		$submitted = isset( $_POST['lxp_course_audience'] ) && is_array( $_POST['lxp_course_audience'] )
			? array_map( 'sanitize_title', wp_unslash( $_POST['lxp_course_audience'] ) )
			: array();

		$checked   = array();
		$unchecked = array();

		foreach ( lxp_get_course_audience_terms() as $term ) {
			if ( in_array( $term['slug'], $submitted, true ) ) {
				$checked[] = $term['slug'];
			} else {
				$unchecked[] = $term['slug'];
			}
		}

		if ( ! empty( $checked ) ) {
			wp_set_object_terms( $post_id, $checked, TL_COURSE_AUDIENCE_TAXONOMY, true );
		}
		if ( ! empty( $unchecked ) ) {
			wp_remove_object_terms( $post_id, $unchecked, TL_COURSE_AUDIENCE_TAXONOMY );
		}
	}

	public function modify_list_row_actions($actions, $post) {
		if ($post->post_type == TL_COURSE_CPT && current_user_can('grades_lxp_course')) {
			$actions['duplicate'] = '<a href="' . site_url() . '/wp-admin/admin.php?page=grades&course_id=' . $post->ID . '" title="" rel="permalink">GradeBook</a>';
		}

		return $actions;
	}

	/**
	 * Return the LP_Course object for the course currently being viewed.
	 *
	 * LP_Global::course() and learn_press_get_course() (called with no args) both
	 * fall back to get_the_ID(), which returns the Elementor template post ID when
	 * rendering a Theme Builder template — not the actual course being visited.
	 *
	 * get_queried_object_id() reads directly from WP_Query::$queried_object_id,
	 * which is set once at query time to the URL-level post and cannot be
	 * overridden by Elementor. We pass that ID explicitly to learn_press_get_course()
	 * so LP builds its cached LP_Course object for the correct course.
	 *
	 * @return LP_Course|null LP_Course object, or null if not on a course page.
	 */
	private function get_current_course() {
		$id = absint( get_queried_object_id() );
		if ( $id > 0 && get_post_type( $id ) === LP_COURSE_CPT && function_exists( 'learn_press_get_course' ) ) {
			return learn_press_get_course( $id ) ?: null;
		}
		return null;
	}

	/**
	 * Register per-field LP course shortcodes for use in Elementor HTML widgets.
	 * LearnPress v4 removed these shortcodes; we re-implement them using LP v4 APIs.
	 *
	 * All shortcodes use LP_Course object methods wherever available:
	 *  - get_title()        — LP_Course native
	 *  - get_image_url()    — LP_Course native (returns URL or LP placeholder)
	 *  - count_students()   — LP_Course native (real + fake enrolled count)
	 *  - count_items()      — LP_Course native, filtered by post type
	 *  - _lp_level meta     — no LP_Course method; post meta is the canonical store
	 *  - _lp_duration meta  — LP_Course::get_duration() returns seconds; the raw
	 *                         meta string is the human-readable source of truth
	 */
	public function register_course_shortcodes() {
		add_shortcode( 'lp_course_title', function() {
			$course = $this->get_current_course();
			return $course ? esc_html( $course->get_title() ) : '';
		} );

		add_shortcode( 'lp_course_excerpt', function() {
			$course = $this->get_current_course();
			// LP_Course has no get_excerpt(); use WP's function with the course ID.
			return $course ? wp_kses_post( get_the_excerpt( $course->get_id() ) ) : '';
		} );

		add_shortcode( 'lp_course_featured_image_url', function() {
			$course = $this->get_current_course();
			// LP_Course::get_image_url() returns the featured image URL or LP's placeholder.
			return $course ? esc_url( $course->get_image_url( 'full' ) ) : '';
		} );

		add_shortcode( 'lp_course_level', function() {
			$course = $this->get_current_course();
			if ( ! $course ) {
				return '';
			}
			// LP_Course has no get_level() method; level is stored in post meta.
			$level = get_post_meta( $course->get_id(), '_lp_level', true );
			return esc_html( $level ?: '' );
		} );

		add_shortcode( 'lp_course_duration', function() {
			$course = $this->get_current_course();
			if ( ! $course ) {
				return '';
			}
			// LP_Course::get_duration() returns seconds; use raw meta for the display string.
			$raw = get_post_meta( $course->get_id(), '_lp_duration', true );
			if ( ! $raw ) {
				return '';
			}
			// Meta is stored as e.g. "4 week"; LP_Datetime handles correct pluralization.
			$parts  = explode( ' ', trim( $raw ) );
			$number = floatval( $parts[0] ?? 0 );
			$type   = $parts[1] ?? '';
			if ( $number && $type && class_exists( 'LP_Datetime' ) && method_exists( 'LP_Datetime', 'get_string_plural_duration' ) ) {
				return esc_html( LP_Datetime::get_string_plural_duration( $number, $type ) );
			}
			return esc_html( $raw );
		} );

		add_shortcode( 'lp_course_students', function() {
			$course = $this->get_current_course();
			// LP_Course::count_students() returns real + fake enrolled count.
			return $course ? absint( $course->count_students() ) : 0;
		} );

		add_shortcode( 'lp_course_lessons', function() {
			$course = $this->get_current_course();
			return $course ? absint( $course->count_items( LP_LESSON_CPT ) ) : '';
		} );

		add_shortcode( 'learn_press_button_course', function() {
			ob_start();
			do_action( 'learn-press/course/buttons' );
			return ob_get_clean();
		} );
	}

	/**
	 * Process WordPress shortcodes inside Elementor's HTML widget content.
	 * Elementor does not call do_shortcode() on HTML widget output by default.
	 *
	 * @param string                    $content Widget rendered HTML.
	 * @param \Elementor\Widget_Base    $widget  The widget instance.
	 * @return string
	 */
	public function process_html_widget_shortcodes( $content, $widget ) {
		if ( $widget->get_name() === 'html' ) {
			return do_shortcode( $content );
		}

		return $content;
	}
}