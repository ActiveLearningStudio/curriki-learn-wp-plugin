<?php
namespace Edudeme\Elementor;

use Elementor\Controls_Manager;

class LXP_Student_Courses_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'lxp-student-courses'; }
	public function get_title()      { return esc_html__( 'LXP Student Courses', 'tinylxp' ); }
	public function get_icon()       { return 'eicon-library-open'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {

		// ── Content: Settings ─────────────────────────────────────────────
		$this->start_controls_section( 'section_settings', [
			'label' => esc_html__( 'Settings', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'empty_message', [
			'label'   => esc_html__( 'Empty State Message', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Open the link your teacher gave you to see your class.', 'tinylxp' ),
		] );

		$this->add_control( 'open_label', [
			'label'   => esc_html__( 'Open Course Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Open Course', 'tinylxp' ),
		] );

		$this->add_control( 'scope_help', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'This widget shows exactly one class — the one named by the ?class_code= in the page URL, which claim links and the Class Join widget both supply. It never lists a student\'s other classes. If the URL has no class code, it falls back to the student\'s class only when they belong to exactly one.', 'tinylxp' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		] );

		$this->end_controls_section();

		// ── Style: Layout ─────────────────────────────────────────────────
		$this->start_controls_section( 'section_layout', [
			'label' => esc_html__( 'Layout', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'columns', [
			'label'   => esc_html__( 'Columns', 'tinylxp' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '3',
			'options' => [
				'2' => esc_html__( '2', 'tinylxp' ),
				'3' => esc_html__( '3', 'tinylxp' ),
				'4' => esc_html__( '4', 'tinylxp' ),
			],
		] );

		$this->end_controls_section();

		// ── Style: Card Header ────────────────────────────────────────────
		$this->start_controls_section( 'section_header', [
			'label' => esc_html__( 'Card Header', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'use_color_cycle', [
			'label'        => esc_html__( 'Cycle Card Colors', 'tinylxp' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'tinylxp' ),
			'label_off'    => esc_html__( 'No', 'tinylxp' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'header_bg_color', [
			'label'     => esc_html__( 'Header Background Color', 'tinylxp' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#1a73e8',
			'condition' => [ 'use_color_cycle' => '' ],
		] );

		$this->add_control( 'header_text_color', [
			'label'   => esc_html__( 'Header Text Color', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#ffffff',
		] );

		$this->end_controls_section();

		// ── Style: Card Body ──────────────────────────────────────────────
		$this->start_controls_section( 'section_body', [
			'label' => esc_html__( 'Card Body', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'card_bg_color', [
			'label'   => esc_html__( 'Card Background', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#ffffff',
		] );

		$this->add_control( 'body_text_color', [
			'label'   => esc_html__( 'Body Text Color', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#3c4043',
		] );

		$this->add_control( 'meta_text_color', [
			'label'   => esc_html__( 'Meta / Badge Text Color', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#70757a',
		] );

		$this->add_control( 'btn_text_color', [
			'label'   => esc_html__( 'Button Color', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#1a73e8',
		] );

		$this->end_controls_section();
	}

	protected function render() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your courses.', 'tinylxp' ) . '</p>';
			return;
		}

		$settings     = $this->get_settings_for_display();
		$uid          = 'lxp-scw-' . $this->get_id();
		$cols         = absint( $settings['columns'] ) ?: 3;
		$open_label   = esc_html( $settings['open_label'] ?: 'Open Course' );
		$empty_msg    = esc_html( $settings['empty_message'] ?: 'Open the link your teacher gave you to see your class.' );
		$use_cycle    = $settings['use_color_cycle'] === 'yes';

		$palette = [ '#1a73e8', '#0f9d58', '#e37400', '#d93025', '#673ab7', '#00838f', '#c2185b' ];

		$student_post = lxp_get_student_post( get_current_user_id() );

		if ( ! $student_post ) {
			echo '<p>' . $empty_msg . '</p>';
			return;
		}

		$class = $this->resolve_class( $student_post );

		if ( ! $class ) {
			echo '<p>' . $empty_msg . '</p>';
			return;
		}

		$course_ids = get_post_meta( $class->ID, 'lxp_class_course_ids' );
		$course_ids = is_array( $course_ids ) ? array_values( array_filter( array_map( 'absint', $course_ids ) ) ) : [];

		// Batch-fetch this class's LP course posts once.
		$courses_by_id = [];

		if ( ! empty( $course_ids ) ) {
			$course_posts = get_posts( [
				'post_type'      => TL_COURSE_CPT,
				'post__in'       => $course_ids,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
			foreach ( $course_posts as $cp ) {
				$courses_by_id[ $cp->ID ] = $cp;
			}
		}

		$hdr_text   = esc_attr( $settings['header_text_color'] ?: '#ffffff' );
		$card_bg    = esc_attr( $settings['card_bg_color']     ?: '#ffffff' );
		$body_color = esc_attr( $settings['body_text_color']   ?: '#3c4043' );
		$meta_color = esc_attr( $settings['meta_text_color']   ?: '#70757a' );
		$btn_color  = esc_attr( $settings['btn_text_color']    ?: '#1a73e8' );
		$hdr_bg_fixed = esc_attr( $settings['header_bg_color'] ?: '#1a73e8' );

		// ── Inline CSS ────────────────────────────────────────────────────
		?>
		<style>
		#<?php echo esc_attr( $uid ); ?> {
			font-family: 'Google Sans', Roboto, Arial, sans-serif;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-scw__class-title {
			font-size: 22px;
			font-weight: 400;
			color: <?php echo $body_color; ?>;
			margin: 0 0 20px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-scw__grid {
			display: grid;
			gap: 16px;
			grid-template-columns: repeat(<?php echo $cols; ?>, 1fr);
		}
		@media (max-width: 900px) {
			#<?php echo esc_attr( $uid ); ?> .lxp-scw__grid { grid-template-columns: repeat(2, 1fr); }
		}
		@media (max-width: 600px) {
			#<?php echo esc_attr( $uid ); ?> .lxp-scw__grid { grid-template-columns: 1fr; }
		}
		/* ── Course cards ── */
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card {
			border-radius: 8px;
			overflow: hidden;
			background: <?php echo $card_bg; ?>;
			box-shadow: 0 1px 3px rgba(0,0,0,.2), 0 1px 2px rgba(0,0,0,.12);
			display: flex;
			flex-direction: column;
			transition: transform .15s ease, box-shadow .15s ease;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(0,0,0,.2), 0 2px 4px rgba(0,0,0,.12);
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__header {
			position: relative;
			height: 96px;
			padding: 12px 16px;
			display: flex;
			flex-direction: column;
			overflow: hidden;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__header-pattern {
			position: absolute;
			inset: 0;
			background-image: radial-gradient(circle, rgba(255,255,255,.15) 1px, transparent 1px);
			background-size: 18px 18px;
			pointer-events: none;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__title {
			margin-top: auto;
			position: relative;
			z-index: 1;
			font-size: 17px;
			font-weight: 500;
			color: <?php echo $hdr_text; ?>;
			line-height: 1.2;
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__body {
			padding: 12px 16px;
			flex: 1;
			color: <?php echo $body_color; ?>;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__meta {
			font-size: 12px;
			color: <?php echo $meta_color; ?>;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__footer {
			padding: 8px 16px 14px;
			text-align: right;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__btn {
			font-size: 13px;
			font-weight: 500;
			color: <?php echo $btn_color; ?>;
			text-decoration: none;
			letter-spacing: .25px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-course-card__btn:hover {
			text-decoration: underline;
		}
		</style>

		<?php
		// ── HTML ──────────────────────────────────────────────────────────
		// One class, server-rendered. Nothing about any other class the student
		// belongs to reaches the page — not even hidden in the DOM, which was
		// the flaw in the old two-step picker.
		echo '<div class="lxp-scw" id="' . esc_attr( $uid ) . '">';
		echo '<div class="lxp-scw__step lxp-scw__step--2">';
		echo '<h2 class="lxp-scw__class-title">' . esc_html( $class->post_title ) . '</h2>';
		echo '<div class="lxp-courses-panel">';
		echo '<div class="lxp-scw__grid">';

		if ( empty( $course_ids ) ) {
			echo '<p>' . esc_html__( 'No courses assigned to this class yet.', 'tinylxp' ) . '</p>';
		} else {
			$ci = 0;
			foreach ( $course_ids as $cid ) {
				if ( ! isset( $courses_by_id[ $cid ] ) ) {
					continue;
				}
				$cp          = $courses_by_id[ $cid ];
				$hdr_color   = $use_cycle
					? $palette[ $ci % count( $palette ) ]
					: $hdr_bg_fixed;
				$lesson_count = 0;
				if ( function_exists( 'learn_press_get_course' ) ) {
					$lp_course = \learn_press_get_course( $cp->ID );
					if ( $lp_course ) {
						$lesson_count = (int) $lp_course->count_items( LP_LESSON_CPT );
					}
				}
				$noun = $lesson_count === 1 ? 'Lesson' : 'Lessons';
				?>
				<div class="lxp-course-card">
					<div class="lxp-course-card__header" style="background:<?php echo esc_attr( $hdr_color ); ?>">
						<div class="lxp-course-card__header-pattern"></div>
						<span class="lxp-course-card__title"><?php echo esc_html( $cp->post_title ); ?></span>
					</div>
					<div class="lxp-course-card__body">
						<span class="lxp-course-card__meta"><?php echo esc_html( $lesson_count . ' ' . $noun ); ?></span>
					</div>
					<div class="lxp-course-card__footer">
						<a href="<?php echo esc_url( get_permalink( $cp->ID ) ); ?>" class="lxp-course-card__btn">
							<?php echo $open_label; ?>
						</a>
					</div>
				</div>
				<?php
				$ci++;
			}
		}

		echo '</div>'; // .lxp-scw__grid
		echo '</div>'; // .lxp-courses-panel
		echo '</div>'; // .lxp-scw__step--2
		echo '</div>'; // #lxp-scw-{uid}
	}

	/**
	 * Work out which single class this page view is about.
	 *
	 * The student arrived here from a claim link or the Class Join widget, both
	 * of which put ?class_code= in the URL (see
	 * Rest_Lxp_Class_Redemption::build_claim_url() / landing_url()). That code
	 * is the whole context: this page is "your class", not "your classes".
	 *
	 * A code on its own is never enough — membership is always re-checked
	 * against the class's own lxp_student_ids meta, the same assertion
	 * Rest_Lxp_Student::access_login() makes. Otherwise anyone holding a code
	 * could read another class's course list.
	 *
	 * @param \WP_Post $student_post tl_student post for the current user.
	 * @return \WP_Post|null tl_class post, or null if nothing can be shown.
	 */
	private function resolve_class( $student_post ) {
		$code = isset( $_GET['class_code'] ) ? sanitize_text_field( wp_unslash( $_GET['class_code'] ) ) : '';

		// Codes are minted uppercase; a lowercased URL must still resolve.
		$code = strtoupper( trim( $code ) );

		if ( '' !== $code ) {
			$found = get_posts( [
				'post_type'      => TL_CLASS_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => 'lxp_class_code',
				'meta_value'     => $code,
			] );

			if ( empty( $found ) ) {
				return null;
			}

			return $this->is_member( $found[0], $student_post ) ? $found[0] : null;
		}

		// No code in the URL. Only safe fallback is a student who is in exactly
		// one class — still a single-class view, and it keeps a bookmarked URL
		// working after the Bookmark Prompt widget strips the claim token.
		// Two or more classes is ambiguous, and guessing would resurrect the
		// cross-class leak this widget was rewritten to close.
		$classes = lxp_get_student_all_classes( $student_post->ID );

		return ( is_array( $classes ) && 1 === count( $classes ) ) ? $classes[0] : null;
	}

	/**
	 * Is this student on that class's roster?
	 *
	 * @param \WP_Post $class
	 * @param \WP_Post $student_post
	 * @return bool
	 */
	private function is_member( $class, $student_post ) {
		$student_ids = get_post_meta( $class->ID, 'lxp_student_ids' );
		$student_ids = is_array( $student_ids ) ? array_map( 'absint', $student_ids ) : [];

		return in_array( (int) $student_post->ID, $student_ids, true );
	}
}
