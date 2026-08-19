<?php
/**
 * LXP Bookmark Prompt — "save your way back into class" banner.
 *
 * Drop this on the page claim links point at (by default /student-courses/).
 * It renders only when the URL carries a `claim` token, which is the single
 * moment the student's claim link is in the address bar — right after they
 * join, having been sent here by the Class Join widget's ticket screen.
 *
 * Why a banner and not a "bookmark this" button: no browser exposes an API to
 * add a bookmark. `window.external.AddFavorite` (IE) and `window.sidebar.addPanel`
 * (Firefox, removed in 23) are both long dead, and a synthesised Ctrl+D
 * KeyboardEvent is untrusted so browser chrome ignores it. The only thing that
 * works is the user's own keystroke on the page they are standing on.
 *
 * Visibility by login state is deliberately NOT handled here — use Elementor's
 * own display conditions. The `claim` check below is not a visibility rule but
 * a functional one: with no token in the URL there is nothing to bookmark and
 * nothing for the confirm button to strip.
 *
 * @see docs/student-privacy-zone-a-context.md
 */

namespace Edudeme\Elementor;

use Elementor\Controls_Manager;

class LXP_Bookmark_Prompt_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'lxp-bookmark-prompt'; }
	public function get_title()      { return esc_html__( 'LXP Bookmark Prompt', 'tinylxp' ); }
	public function get_icon()       { return 'eicon-bookmark'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {

		// ── Content ───────────────────────────────────────────────────────
		$this->start_controls_section( 'section_content', [
			'label' => esc_html__( 'Content', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'heading', [
			'label'   => esc_html__( 'Heading', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( '⭐ Save this page!', 'tinylxp' ),
		] );

		$this->add_control( 'message', [
			'label'       => esc_html__( 'Message', 'tinylxp' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => esc_html__( 'Press {shortcut} to bookmark this page, so you can get back into class next time. On a tablet, use your browser menu and choose "Add bookmark".', 'tinylxp' ),
			'description' => esc_html__( 'Use {shortcut} where the keyboard shortcut should appear — it becomes Ctrl+D, or ⌘D on Apple devices.', 'tinylxp' ),
		] );

		$this->add_control( 'button_label', [
			'label'   => esc_html__( 'Confirm Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( "I've bookmarked it", 'tinylxp' ),
		] );

		$this->add_control( 'button_help', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'This button is a confirmation, not a dismissal. Clicking it removes the secret token from the address bar — so a student who clicks it before bookmarking will save a link that cannot sign them back in. Keep the wording an assertion ("I have done this"), not a close action.', 'tinylxp' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
		] );

		$this->end_controls_section();

		// ── Style ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => esc_html__( 'Style', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bg_color', [
			'label'   => esc_html__( 'Background', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#fef7e0',
		] );

		$this->add_control( 'border_color', [
			'label'   => esc_html__( 'Border', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#f9e0a2',
		] );

		$this->add_control( 'text_color', [
			'label'   => esc_html__( 'Text Color', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#3c4043',
		] );

		$this->add_control( 'btn_bg_color', [
			'label'   => esc_html__( 'Button Background', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#1a73e8',
		] );

		$this->add_control( 'btn_text_color', [
			'label'   => esc_html__( 'Button Text', 'tinylxp' ),
			'type'    => Controls_Manager::COLOR,
			'default' => '#ffffff',
		] );

		$this->add_control( 'max_width', [
			'label'      => esc_html__( 'Max Width', 'tinylxp' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [ 'px' => [ 'min' => 240, 'max' => 1200 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 640 ],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$is_edit = \Elementor\Plugin::$instance->editor->is_edit_mode();

		// Nothing to bookmark without a claim token in the URL. Render anyway in
		// the Elementor editor, otherwise the widget is invisible and unselectable
		// while being styled.
		if ( empty( $_GET['claim'] ) && ! $is_edit ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		$uid          = 'lxp-bm-' . $this->get_id();
		$heading      = esc_html( $settings['heading'] );
		$button_label = esc_html( $settings['button_label'] );

		$bg        = esc_attr( $settings['bg_color'] );
		$border    = esc_attr( $settings['border_color'] );
		$text_col  = esc_attr( $settings['text_color'] );
		$btn_bg    = esc_attr( $settings['btn_bg_color'] );
		$btn_text  = esc_attr( $settings['btn_text_color'] );

		$width      = isset( $settings['max_width']['size'] ) ? $settings['max_width']['size'] : 640;
		$width_unit = isset( $settings['max_width']['unit'] ) ? $settings['max_width']['unit'] : 'px';
		$max_width  = esc_attr( $width . $width_unit );

		// {shortcut} is the only markup we inject, so escape first and substitute
		// after — author-supplied text never reaches the page unescaped.
		$message = str_replace(
			'{shortcut}',
			'<strong class="lxp-bm-shortcut">Ctrl+D</strong>',
			esc_html( $settings['message'] )
		);
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="lxp-bm" style="
			max-width:<?php echo $max_width; ?>;margin:0 auto 20px;padding:16px 18px;
			background:<?php echo $bg; ?>;border:1px solid <?php echo $border; ?>;
			border-radius:8px;color:<?php echo $text_col; ?>;
			font-family:'Google Sans',Roboto,Arial,sans-serif;">

			<?php if ( $heading ) : ?>
			<div class="lxp-bm-heading" style="font-size:16px;font-weight:500;margin-bottom:6px">
				<?php echo $heading; ?>
			</div>
			<?php endif; ?>

			<div class="lxp-bm-message" style="font-size:14px;line-height:1.5;margin-bottom:12px">
				<?php echo $message; ?>
			</div>

			<button type="button" class="lxp-bm-done" style="
				padding:8px 16px;font-size:14px;font-weight:500;border:none;border-radius:6px;
				cursor:pointer;background:<?php echo $btn_bg; ?>;color:<?php echo $btn_text; ?>;">
				<?php echo $button_label; ?>
			</button>
		</div>

		<script>
		(function() {
			var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!root) return;

			// Apple platforms use ⌘D. navigator.platform is deprecated but remains
			// the broadest check; userAgentData is Chromium-only.
			var isMac = /Mac|iPhone|iPad|iPod/.test(
				(window.navigator.userAgentData && window.navigator.userAgentData.platform) ||
				window.navigator.platform || ''
			);
			if (isMac) {
				root.querySelectorAll('.lxp-bm-shortcut').forEach(function(el) {
					el.textContent = '⌘D';
				});
			}

			var done = root.querySelector('.lxp-bm-done');
			if (!done) return;

			done.addEventListener('click', function() {
				// Strip the secret from the address bar but keep class_code, so the
				// Student Courses widget stays open on this student's class.
				// replaceState rewrites the current history entry, so the token does
				// not linger in browser history either.
				try {
					var url = new URL(window.location.href);
					url.searchParams.delete('claim');
					window.history.replaceState({}, '', url.pathname + (url.search || '') + url.hash);
				} catch (err) { /* older browser — leaving the URL as-is is harmless */ }

				if (root.parentNode) { root.parentNode.removeChild(root); }
			});
		})();
		</script>
		<?php
	}
}
