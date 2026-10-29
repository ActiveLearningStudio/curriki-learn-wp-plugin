<?php

namespace Edudeme\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LXP Course HTML Widget
 *
 * An Elementor HTML widget that resolves {{lp_*}} tokens against the LP_Course
 * object for the course currently being viewed. Designed for use in Elementor
 * Theme Builder single-course templates.
 *
 * Why not shortcodes? Elementor's element caching and page cache plugins can
 * cache already-resolved shortcode output, causing Course A's data to appear
 * on Course B. This widget's render() runs server-side on every request
 * (Elementor default: dynamic content = no element caching) and resolves tokens
 * directly from the LP_Course object using get_queried_object_id() — immune to
 * Elementor setting a different global $post for its template.
 *
 * Supported tokens:
 *  {{lp_course_title}}         — Course title
 *  {{lp_course_excerpt}}       — Course excerpt / short description
 *  {{lp_course_image_url}}     — Featured image URL (full size) or LP placeholder
 *  {{lp_course_level}}         — Course level (Beginner / Intermediate / Advanced)
 *  {{lp_course_duration}}      — Human-readable duration, e.g. "4 Weeks"
 *  {{lp_course_lesson_count}}  — Number of lessons in the course
 *  {{lp_course_student_count}} — Number of enrolled students (real + fake)
 *  {{lp_course_button}}        — LearnPress enroll / start / resume / continue button HTML
 *  {{lp_course_tags}}          — Comma-separated list of course tags
 *  {{#lp_course_tags}}...{{lp_course_tag}}...{{/lp_course_tags}} — Repeat inner HTML once per tag
 *  {{lp_course_tags_loop}}...{{tag}}...{{/lp_course_tags_loop}} — Alternate tag loop syntax
 *  {{lp_course_outcome}}       — Course outcome (from lxp_course_outcome post meta)
 *  {{lp_course_description}}    — Full course description (WYSIWYG post_content, rendered)
 *
 * Conditional blocks (keep/strip per course):
 *  {{#lp_course_control_deploy}}...{{/lp_course_control_deploy}}
 *    — Kept unless "Show 'Deploy to learners' button" is unticked on the course
 *      (lxp_course_hide_deploy post meta = '1').
 *
 * Section loop (iterates LP course sections ordered by section_order ASC):
 *  {{#lp_course_sections}}...{{/lp_course_sections}}
 *    Inner tokens per iteration:
 *      {{lp_section_number}}              — Zero-padded ordinal: 01, 02, 03…
 *      {{lp_section_index}}               — Plain 1-based index: 1, 2, 3…
 *      {{lp_section_title}}               — LP section name
 *      {{lp_section_description}}         — LP section description (may be empty)
 *      {{lp_section_first_lesson_title}}  — First lesson post title
 *      {{lp_section_first_lesson_excerpt}}— First lesson post excerpt
 *      {{lp_section_first_lesson_url}}    — First lesson permalink
 *    Conditional blocks (keep/strip per iteration):
 *      {{#lp_section_is_last}}...{{/lp_section_is_last}}         — last section only
 *      {{#lp_section_is_not_last}}...{{/lp_section_is_not_last}} — all except last
 *
 * Backwards-compatible aliases (old names still work):
 *  {{lp_title}}, {{lp_excerpt}}, {{lp_image_url}}, {{lp_level}},
 *  {{lp_duration}}, {{lp_lesson_count}}, {{lp_student_count}}, {{lp_enroll_button}}
 */
class LXP_Course_HTML_Widget extends Widget_Base {

	public function get_name() {
		return 'lxp-course-html';
	}

	public function get_title() {
		return esc_html__( 'Course HTML', 'tiny-lxp-platform' );
	}

	public function get_icon() {
		return 'eicon-code';
	}

	public function get_categories() {
		return [ 'edudeme-addons' ];
	}

	/**
	 * Do NOT declare is_dynamic_content() — Elementor's default is dynamic (true),
	 * meaning it will never cache this widget's output in its element cache.
	 * This is intentional: each course page must render fresh.
	 */

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Content', 'tiny-lxp-platform' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'html_content',
			[
				'label'       => esc_html__( 'HTML', 'tiny-lxp-platform' ),
				'type'        => Controls_Manager::CODE,
				'language'    => 'html',
				'rows'        => 20,
				'default'     => '',
				'placeholder' => implode( "\n", [
					'Available tokens:',
					'  {{lp_course_title}}         — Course title',
					'  {{lp_course_excerpt}}       — Course excerpt',
					'  {{lp_course_image_url}}     — Featured image URL',
					'  {{lp_course_level}}         — Course level',
					'  {{lp_course_duration}}      — Course duration',
					'  {{lp_course_lesson_count}}  — Number of lessons',
					'  {{lp_course_student_count}} — Enrolled students',
					'  {{lp_course_button}}        — Enroll / start / resume button',
					'  {{lp_course_tags}}          — Course tags (comma-separated)',
					'  {{#lp_course_tags}}<span>{{lp_course_tag}}</span>{{/lp_course_tags}}',
					'  {{lp_course_tags_loop}}<span>{{tag}}</span>{{/lp_course_tags_loop}}',
					'  {{lp_course_outcome}}       — Course outcome',
					'  {{lp_course_description}}   u{2014} Course description (full)',
					'  {{#lp_course_control_deploy}}<a href="...">Deploy to learners</a>{{/lp_course_control_deploy}} — shown unless hidden on the course',
					'  {{#lp_course_sections}}<div>{{lp_section_number}} {{lp_section_title}} {{lp_section_first_lesson_title}} <a href="{{lp_section_first_lesson_url}}">Preview</a></div>{{/lp_course_sections}}',
				] ),
				'dynamic'     => [ 'active' => false ],
			]
		);

		$this->add_control(
			'editor_message',
			[
				'label'       => esc_html__( 'Editor Preview Message', 'tiny-lxp-platform' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Course HTML — preview visible on the frontend course page.', 'tiny-lxp-platform' ),
				'description' => esc_html__( 'Shown in the Elementor editor instead of live course data.', 'tiny-lxp-platform' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Resolve the LP_Course object for the course currently being viewed.
	 *
	 * get_queried_object_id() reads WP_Query::$queried_object_id — set at
	 * query time from the URL. Elementor cannot change this when rendering a
	 * Theme Builder template, unlike global $post which Elementor replaces with
	 * the template post.
	 *
	 * @return \LP_Course|null
	 */
	private function get_current_course() {
		$id = absint( get_queried_object_id() );
		if ( $id > 0 && get_post_type( $id ) === LP_COURSE_CPT && function_exists( 'learn_press_get_course' ) ) {
			return learn_press_get_course( $id ) ?: null;
		}
		return null;
	}

	/**
	 * Fetch course tags for the current course.
	 *
	 * @param int $course_id Course post ID.
	 * @return array<int,\WP_Term>
	 */
	private function get_course_tags( $course_id ) {
		$course_tags = wp_get_post_terms( $course_id, 'course_tag' );

		if ( is_wp_error( $course_tags ) || ! is_array( $course_tags ) ) {
			return [];
		}

		return $course_tags;
	}

	/**
	 * Replace repeated course-tag HTML blocks before scalar token replacement.
	 *
	 * @param string               $html        Raw widget HTML.
	 * @param array<int,\WP_Term> $course_tags Course tag terms.
	 * @return string
	 */
	private function replace_course_tag_loops( $html, $course_tags ) {
		$patterns = [
			'/\{\{#lp_course_tags\}\}(.*?)\{\{\/lp_course_tags\}\}/s',
			'/\{\{lp_course_tags_loop\}\}(.*?)\{\{\/lp_course_tags_loop\}\}/s',
		];

		foreach ( $patterns as $pattern ) {
			$html = preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( $course_tags ) {
					$tag_template = $matches[1] ?? '';

					if ( empty( $course_tags ) ) {
						return '';
					}

					$tag_output = '';

					foreach ( $course_tags as $course_tag ) {
						$tag_name = isset( $course_tag->name ) ? esc_html( $course_tag->name ) : '';

						$tag_output .= str_replace(
							[ '{{lp_course_tag}}', '{{lp_course_tag_name}}', '{{tag}}' ],
							[ $tag_name, $tag_name, $tag_name ],
							$tag_template
						);
					}

					return $tag_output;
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Keep or strip {{#lp_course_control_deploy}}...{{/lp_course_control_deploy}} blocks.
	 *
	 * Shown unless the course's "Curriki Deploy Button" meta box was unticked.
	 *
	 * @param string $html      Raw widget HTML.
	 * @param int    $course_id Course post ID.
	 * @return string
	 */
	private function replace_course_deploy_control( $html, $course_id ) {
		if ( strpos( $html, '{{#lp_course_control_deploy}}' ) === false ) {
			return $html;
		}

		$show = '1' !== (string) get_post_meta( $course_id, 'lxp_course_hide_deploy', true );

		return preg_replace(
			'/\{\{#lp_course_control_deploy\}\}(.*?)\{\{\/lp_course_control_deploy\}\}/s',
			$show ? '$1' : '',
			$html
		);
	}

	/**
	 * Fetch and structure LP course sections data for the section loop.
	 *
	 * @param int $course_id Course post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_course_sections_data( $course_id ) {
		if ( ! class_exists( 'LP_Section_DB' ) || ! class_exists( 'LP_Section_Filter' ) ) {
			return [];
		}

		// LP lesson URLs are nested under the course: /{course-slug}/lessons/{lesson-slug}/
		// get_permalink( $lesson_id ) returns the bare WP post URL — wrong for LP4.
		// Load the LP_Course object once here so get_item_link() is available in the loop.
		$course_obj = function_exists( 'learn_press_get_course' ) ? learn_press_get_course( absint( $course_id ) ) : null;

		$filter                    = new \LP_Section_Filter();
		$filter->section_course_id = absint( $course_id );
		$filter->limit             = -1;

		$result   = \LP_Section_DB::getInstance()->get_sections_by_course_id( $filter );
		$sections = ( ! is_wp_error( $result ) && isset( $result['results'] ) ) ? $result['results'] : [];

		if ( empty( $sections ) ) {
			return [];
		}

		$total = count( $sections );
		$data  = [];

		foreach ( $sections as $i => $section ) {
			$index      = $i + 1;
			$section_id = absint( $section['section_id'] ?? 0 );

			$first_item = null;
			if ( $section_id && class_exists( 'LP_Section_Items_Filter' ) ) {
				$items_filter             = new \LP_Section_Items_Filter();
				$items_filter->section_id = $section_id;
				$items_filter->limit      = 1;
				$items_result = \LP_Section_DB::getInstance()->get_section_items_by_section_id( $items_filter );
				if ( ! is_wp_error( $items_result ) && ! empty( $items_result['results'] ) ) {
					$first_item = $items_result['results'][0];
				}
			}

			$first_lesson_id  = $first_item ? absint( $first_item['ID'] ) : 0;
			$first_lesson_url = '';
			if ( $first_lesson_id ) {
				// Prefer LP4's get_item_link() which builds the correct nested URL:
				// /{course-slug}/lessons/{lesson-slug}/
				// Fall back to get_permalink() if the course object isn't available.
				if ( $course_obj && method_exists( $course_obj, 'get_item_link' ) ) {
					$first_lesson_url = esc_url( $course_obj->get_item_link( $first_lesson_id ) );
				} else {
					$first_lesson_url = esc_url( get_permalink( $first_lesson_id ) );
				}
			}

			$data[] = [
				'index'                => $index,
				'number'               => str_pad( $index, 2, '0', STR_PAD_LEFT ),
				'is_last'              => ( $index === $total ),
				'title'                => esc_html( $section['section_name'] ?? '' ),
				'description'          => wp_kses_post( $section['section_description'] ?? '' ),
				'first_lesson_title'   => $first_item ? esc_html( $first_item['post_title'] ?? '' ) : '',
				'first_lesson_excerpt' => $first_item ? wp_kses_post( $first_item['post_excerpt'] ?? '' ) : '',
				'first_lesson_url'     => $first_lesson_url,
			];
		}

		return $data;
	}

	/**
	 * Replace {{#lp_course_sections}}...{{/lp_course_sections}} loops.
	 *
	 * @param string $html      Raw widget HTML.
	 * @param int    $course_id Course post ID.
	 * @return string
	 */
	private function replace_course_section_loops( $html, $course_id ) {
		if ( strpos( $html, '{{#lp_course_sections}}' ) === false ) {
			return $html;
		}

		$sections = $this->get_course_sections_data( $course_id );

		return preg_replace_callback(
			'/\{\{#lp_course_sections\}\}(.*?)\{\{\/lp_course_sections\}\}/s',
			static function ( $matches ) use ( $sections ) {
				$template = $matches[1];

				if ( empty( $sections ) ) {
					return '';
				}

				$output = '';

				foreach ( $sections as $section ) {
					$chunk = $template;

					// Resolve conditional blocks.
					if ( $section['is_last'] ) {
						$chunk = preg_replace( '/\{\{#lp_section_is_not_last\}\}.*?\{\{\/lp_section_is_not_last\}\}/s', '', $chunk );
						$chunk = preg_replace( '/\{\{#lp_section_is_last\}\}(.*?)\{\{\/lp_section_is_last\}\}/s', '$1', $chunk );
					} else {
						$chunk = preg_replace( '/\{\{#lp_section_is_last\}\}.*?\{\{\/lp_section_is_last\}\}/s', '', $chunk );
						$chunk = preg_replace( '/\{\{#lp_section_is_not_last\}\}(.*?)\{\{\/lp_section_is_not_last\}\}/s', '$1', $chunk );
					}

					// Resolve scalar tokens.
					$chunk = str_replace(
						[
							'{{lp_section_number}}',
							'{{lp_section_index}}',
							'{{lp_section_title}}',
							'{{lp_section_description}}',
							'{{lp_section_first_lesson_title}}',
							'{{lp_section_first_lesson_excerpt}}',
							'{{lp_section_first_lesson_url}}',
						],
						[
							$section['number'],
							(string) $section['index'],
							$section['title'],
							$section['description'],
							$section['first_lesson_title'],
							$section['first_lesson_excerpt'],
							$section['first_lesson_url'],
						],
						$chunk
					);

					$output .= $chunk;
				}

				return $output;
			},
			$html
		);
	}

	/**
	 * Build the token → value map for the current course.
	 *
	 * @param \LP_Course               $course
	 * @param array<int,\WP_Term>|null $course_tags
	 * @return array<string,string>
	 */
	private function build_token_map( $course, $course_tags = null ) {
		$course_id = $course->get_id();

		if ( null === $course_tags ) {
			$course_tags = $this->get_course_tags( $course_id );
		}

		// Duration: LP_Course::get_duration() returns seconds; raw meta is the display string.
		$raw_duration = get_post_meta( $course_id, '_lp_duration', true );
		$duration     = '';
		if ( $raw_duration ) {
			$parts  = explode( ' ', trim( $raw_duration ) );
			$number = floatval( $parts[0] ?? 0 );
			$type   = $parts[1] ?? '';
			if ( $number && $type && class_exists( '\\LP_Datetime' ) && method_exists( '\\LP_Datetime', 'get_string_plural_duration' ) ) {
				$duration = \LP_Datetime::get_string_plural_duration( $number, $type );
			} else {
				$duration = $raw_duration;
			}
		}

		// Enroll / start / resume / continue button.
		// setup_postdata() is required so that get_the_ID() inside the LP callback
		// resolves the actual course, not the Elementor template post.
		$course_post = get_post( $course_id );
		if ( $course_post ) {
			$GLOBALS['post'] = $course_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $course_post );
		}
		ob_start();
		do_action( 'learn-press/course-buttons' );
		$button_html = ob_get_clean();
		wp_reset_postdata();

		// Course tags: comma-separated names from the 'course_tag' taxonomy.
		$tag_names = [];
		foreach ( $course_tags as $course_tag ) {
			if ( isset( $course_tag->name ) ) {
				$tag_names[] = esc_html( $course_tag->name );
			}
		}
		$tags_string = implode( ', ', $tag_names );

		// Course outcome: stored in post meta under 'lxp_course_outcome'.
		$outcome = esc_html( get_post_meta( $course_id, 'lxp_course_outcome', true ) ?: '' );

		// Course description: full WYSIWYG post_content, run through the_content filters
		// so Gutenberg blocks and shortcodes inside the description are rendered.
		//
		// Two issues to guard against:
		// 1. wpautop (priority 10 on the_content) injects <br>/<p> inside <style> blocks,
		//    breaking CSS. Extract style blocks before the filter runs, restore after.
		// 2. wp_kses_post() strips <style> entirely (not in wp_kses_allowed_html('post')).
		//    Skip the inner kses — the outer wp_kses() in render() allows <style>.
		$style_blocks  = [];
		$raw_desc      = get_post_field( 'post_content', $course_id );
		$raw_protected = preg_replace_callback(
			'/<style\b[^>]*>[\s\S]*?<\/style>/i',
			static function ( $m ) use ( &$style_blocks ) {
				$placeholder                  = "\x02STYLE_BLOCK_" . count( $style_blocks ) . "\x03";
				$style_blocks[ $placeholder ] = $m[0];
				return $placeholder;
			},
			$raw_desc
		);
		$processed   = apply_filters( 'the_content', $raw_protected );
		$description = str_replace( array_keys( $style_blocks ), array_values( $style_blocks ), $processed );

		return [
			// New lp_course_* tokens.
			'{{lp_course_title}}'         => esc_html( $course->get_title() ),
			'{{lp_course_excerpt}}'       => wp_kses_post( get_the_excerpt( $course_id ) ),
			'{{lp_course_image_url}}'     => esc_url( $course->get_image_url( 'full' ) ),
			'{{lp_course_level}}'         => esc_html( get_post_meta( $course_id, '_lp_level', true ) ?: '' ),
			'{{lp_course_duration}}'      => esc_html( $duration ),
			'{{lp_course_lesson_count}}'  => absint( $course->count_items( LP_LESSON_CPT ) ),
			'{{lp_course_student_count}}' => absint( $course->count_students() ),
			'{{lp_course_button}}'        => $button_html,
			'{{lp_course_tags}}'          => $tags_string,
			'{{lp_course_outcome}}'       => $outcome,
			'{{lp_course_description}}'   => $description,
			// Backwards-compatible aliases — old token names still resolve.
			'{{lp_title}}'         => esc_html( $course->get_title() ),
			'{{lp_excerpt}}'       => wp_kses_post( get_the_excerpt( $course_id ) ),
			'{{lp_image_url}}'     => esc_url( $course->get_image_url( 'full' ) ),
			'{{lp_level}}'         => esc_html( get_post_meta( $course_id, '_lp_level', true ) ?: '' ),
			'{{lp_duration}}'      => esc_html( $duration ),
			'{{lp_lesson_count}}'  => absint( $course->count_items( LP_LESSON_CPT ) ),
			'{{lp_student_count}}' => absint( $course->count_students() ),
			'{{lp_enroll_button}}' => $button_html,
		];
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$html     = $settings['html_content'] ?? '';

		if ( empty( trim( $html ) ) ) {
			return;
		}

		$course = $this->get_current_course();

		if ( ! $course ) {
			// In Elementor editor or on a non-course page, show the placeholder message.
			echo '<p style="color:#888;font-style:italic;">' . esc_html( $settings['editor_message'] ) . '</p>';
			return;
		}

		$course_tags = $this->get_course_tags( $course->get_id() );
		$html        = $this->replace_course_tag_loops( $html, $course_tags );
		$html        = $this->replace_course_section_loops( $html, $course->get_id() );
		$html        = $this->replace_course_deploy_control( $html, $course->get_id() );
		$map         = $this->build_token_map( $course, $course_tags );
		$output      = str_replace( array_keys( $map ), array_values( $map ), $html );

		// Allow normal post HTML plus embedded <style> blocks for hero/widget CSS.
		// Also allow <form>, <input>, <button> so LP enroll/purchase button forms survive.
		$allowed_html = wp_kses_allowed_html( 'post' );
		$allowed_html['style']  = [ 'type' => true, 'media' => true ];
		$allowed_html['form']   = [ 'name' => true, 'class' => true, 'method' => true, 'action' => true, 'enctype' => true, 'style' => true, 'id' => true ];
		$allowed_html['input']  = [ 'type' => true, 'name' => true, 'value' => true, 'class' => true, 'id' => true, 'style' => true ];
		$allowed_html['button']  = [ 'type' => true, 'class' => true, 'id' => true, 'name' => true, 'value' => true, 'style' => true, 'data-course-id' => true, 'data-id' => true ];
		$allowed_html['section'] = [ 'id' => true, 'class' => true, 'style' => true ];
		$allowed_html['hr']      = [ 'class' => true, 'style' => true ];
		$allowed_html['center']  = [];

		// Allow <source> so [video]/[audio] shortcode output (which nests <source src>) survives kses.
		// Core's 'post' allowlist permits <video>/<audio>/<track> but NOT <source>, so without this
		// the media URL is stripped and the player initializes with nothing to play.
		$allowed_html['source'] = [ 'src' => true, 'type' => true, 'media' => true, 'srcset' => true, 'sizes' => true ];
		// Ensure <video>/<audio> keep the attrs MediaElement.js relies on (core post allowlist omits class).
		$allowed_html['video']  = array_merge( $allowed_html['video'] ?? [], [
			'class' => true, 'id' => true, 'controls' => true, 'preload' => true, 'poster' => true,
			'width' => true, 'height' => true, 'playsinline' => true, 'autoplay' => true, 'loop' => true, 'muted' => true,
		] );
		$allowed_html['audio']  = array_merge( $allowed_html['audio'] ?? [], [
			'class' => true, 'id' => true, 'controls' => true, 'preload' => true, 'autoplay' => true, 'loop' => true, 'muted' => true,
		] );

		echo wp_kses( $output, $allowed_html );
	}
}
