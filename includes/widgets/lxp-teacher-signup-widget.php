<?php
/**
 * LXP Teacher Signup — public self-registration for teachers.
 *
 * Collects name, email, password and the grades taught, then hands off to
 * POST /lms/v1/teacher/signup, which creates the account, attaches it to the
 * school an administrator configured under Curriki Learn -> Teacher Signup,
 * signs the teacher in, and returns where to go next (/classes).
 *
 * The form deliberately has no school or district field. Placement is decided
 * server-side from a WP option — a public form that lets the caller name their
 * own school lets anyone join any school.
 *
 * @see lms/lms-rest-apis/teacher-signup.php
 */

namespace Edudeme\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class LXP_Teacher_Signup_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'lxp-teacher-signup'; }
	public function get_title()      { return esc_html__( 'LXP Teacher Signup', 'tinylxp' ); }
	public function get_icon()       { return 'eicon-form-horizontal'; }
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
			'default' => esc_html__( 'Create your teacher account', 'tinylxp' ),
		] );

		$this->add_control( 'intro', [
			'label'   => esc_html__( 'Intro Text', 'tinylxp' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => esc_html__( 'Set up your account to start creating classes and enrolling students.', 'tinylxp' ),
		] );

		$this->add_control( 'grades_label', [
			'label'   => esc_html__( 'Grades Field Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Grades you teach', 'tinylxp' ),
		] );

		$this->add_control( 'pd_label', [
			'label'       => esc_html__( 'Professional Development Label', 'tinylxp' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => esc_html__( 'Professional Development', 'tinylxp' ),
			'description' => esc_html__( 'Ticking this counts instead of a grade, so an administrator can sign up without claiming one.', 'tinylxp' ),
		] );

		$this->add_control( 'button_label', [
			'label'   => esc_html__( 'Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Create my account', 'tinylxp' ),
		] );

		$this->add_control( 'signed_in_text', [
			'label'   => esc_html__( 'Already Signed In Message', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'You are already signed in.', 'tinylxp' ),
		] );

		$this->add_control( 'school_help', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'New teachers are attached to the school selected under Curriki Learn → Teacher Signup in wp-admin. Set that first — signup is refused until a school is chosen, and until that school has a district assigned.', 'tinylxp' ),
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
			'default' => '#ffffff',
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
			'range'      => [ 'px' => [ 'min' => 280, 'max' => 1200 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 520 ],
		] );

		$this->end_controls_section();

		// ── Heading style ─────────────────────────────────────────────────
		// No defaults on purpose: an unset control emits no CSS at all, so the
		// heading carries on inheriting the Text Color above exactly as it did
		// before these controls existed.
		$this->start_controls_section( 'section_style_heading', [
			'label' => esc_html__( 'Heading', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'heading_color', [
			'label'     => esc_html__( 'Color', 'tinylxp' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .lxp-tsu-heading' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'heading_typography',
			'label'    => esc_html__( 'Typography', 'tinylxp' ),
			'selector' => '{{WRAPPER}} .lxp-tsu-heading',
		] );

		$this->end_controls_section();

		// ── Field label style ─────────────────────────────────────────────
		// Targets the labels above the inputs only. The grade checkbox captions
		// are option text, not field labels, and keep their own smaller type.
		$this->start_controls_section( 'section_style_labels', [
			'label' => esc_html__( 'Field Labels', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'label_color', [
			'label'     => esc_html__( 'Color', 'tinylxp' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .lxp-tsu-label' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'label_typography',
			'label'    => esc_html__( 'Typography', 'tinylxp' ),
			'selector' => '{{WRAPPER}} .lxp-tsu-label',
		] );

		$this->end_controls_section();

		// ── Submit button style ──────────────────────────────────────────
		$this->start_controls_section( 'section_style_submit_btn', [
			'label' => esc_html__( 'Create Account Button', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'submit_btn_bg_color', [
			'label'     => esc_html__( 'Background', 'tinylxp' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .lxp-tsu-btn' => 'background: {{VALUE}};',
			],
		] );

		$this->add_control( 'submit_btn_text_color', [
			'label'     => esc_html__( 'Text Color', 'tinylxp' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .lxp-tsu-btn' => 'color: {{VALUE}};',
			],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'submit_btn_typography',
			'label'    => esc_html__( 'Typography', 'tinylxp' ),
			'selector' => '{{WRAPPER}} .lxp-tsu-btn',
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$is_edit  = \Elementor\Plugin::$instance->editor->is_edit_mode();

		// A signed-in visitor has nothing to sign up for. Still render the form
		// in the editor, otherwise it cannot be selected while being styled.
		if ( is_user_logged_in() && ! $is_edit ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html( $settings['signed_in_text'] ),
				esc_url( home_url( '/classes/' ) ),
				esc_html__( 'Go to my classes', 'tinylxp' )
			);
			return;
		}

		$uid          = 'lxp-tsu-' . $this->get_id();
		$heading      = esc_html( $settings['heading'] );
		$intro        = esc_html( $settings['intro'] );
		$grades_label = esc_html( $settings['grades_label'] );
		$pd_label     = esc_html( $settings['pd_label'] );
		$button_label = esc_html( $settings['button_label'] );

		$bg       = esc_attr( $settings['bg_color'] );
		$text_col = esc_attr( $settings['text_color'] );
		$btn_bg   = esc_attr( $settings['btn_bg_color'] );
		$btn_text = esc_attr( $settings['btn_text_color'] );

		$width      = isset( $settings['max_width']['size'] ) ? $settings['max_width']['size'] : 520;
		$width_unit = isset( $settings['max_width']['unit'] ) ? $settings['max_width']['unit'] : 'px';
		$max_width  = esc_attr( $width . $width_unit );

		$grades = function_exists( 'lxp_get_grade_options' ) ? lxp_get_grade_options() : [];

		$signup_url = rest_url( 'lms/v1/teacher/signup' );

		// The endpoint checks is_user_logged_in() inside its callback. Without
		// this header WP core silently demotes a cookie-authenticated request to
		// anonymous instead of erroring — see CLAUDE.md gotcha #16.
		$nonce = wp_create_nonce( 'wp_rest' );
		?>
		<style>
		#<?php echo esc_attr( $uid ); ?> {
			max-width: <?php echo $max_width; ?>;
			margin: 0 auto;
			padding: 0 24px;
			background: <?php echo $bg; ?>;
			color: <?php echo $text_col; ?>;
			border-radius: 8px;
			font-family: 'Google Sans', Roboto, Arial, sans-serif;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-heading { margin-bottom: 6px; }
		/* Base type for the two elements the Style tab exposes. Intentionally
		   NOT prefixed with the wrapper id: an ID selector scores 100 and would
		   beat Elementor's own `{{WRAPPER}} .lxp-tsu-heading` rule, leaving the
		   Heading / Field Labels controls with no visible effect. A single class
		   scores 10, under every form {{WRAPPER}} takes, so whatever is set in
		   the panel wins regardless of stylesheet order. Colour is left out on
		   purpose — it inherits from the wrapper's Text Color, which is what an
		   unset Heading/Label Color has to fall back to. */
		.lxp-tsu-heading {
			font-size: 22px;
			font-weight: 500;
		}
		.lxp-tsu-label {
			font-size: 13px;
			font-weight: 500;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-intro {
			font-size: 14px;
			line-height: 1.5;
			margin-bottom: 20px;
			opacity: .85;
		}
		#<?php echo esc_attr( $uid ); ?> label {
			display: block;
			margin-bottom: 4px;
		}
		#<?php echo esc_attr( $uid ); ?> input[type="text"],
		#<?php echo esc_attr( $uid ); ?> input[type="email"],
		#<?php echo esc_attr( $uid ); ?> input[type="password"] {
			width: 100%;
			box-sizing: border-box;
			padding: 9px 12px;
			font-size: 15px;
			border: 1px solid #dadce0;
			border-radius: 6px;
			margin-bottom: 14px;
			background: #fff;
			color: #3c4043;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-row {
			display: flex;
			gap: 12px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-row > div {
			flex: 1;
		}
		@media (max-width: 480px) {
			#<?php echo esc_attr( $uid ); ?> .lxp-tsu-row { display: block; }
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-grades {
			display: flex;
			flex-wrap: wrap;
			gap: 6px 14px;
			margin-bottom: 10px;
		}
		/* The PD row deliberately shares the grade checkboxes' type treatment —
		   it is one more thing you tick in the same block, not a second kind of
		   control. Only the margins differ. */
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-grades label,
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-pd-row label {
			font-weight: 400;
			font-size: 14px;
			margin: 0;
			display: inline-flex;
			align-items: center;
			gap: 5px;
			cursor: pointer;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-pd-row {
			margin-bottom: 18px;
		}
		#<?php echo esc_attr( $uid ); ?> button.lxp-tsu-btn {
			width: 100%;
			padding: 11px 16px;
			border: none;
			border-radius: 6px;
			cursor: pointer;
		}
		/* font-size/weight/background/color live here instead of the ID-scoped
		   rule above so the Create Account Button section's {{WRAPPER}}
		   .lxp-tsu-btn selectors (specificity 20) can override them; the ID
		   selector above (111) would always win regardless of what the panel
		   sets. This is the fallback used when those controls are left unset —
		   same look as before this section existed. */
		.lxp-tsu-btn {
			font-size: 15px;
			font-weight: 500;
			background: <?php echo $btn_bg; ?>;
			color: <?php echo $btn_text; ?>;
		}
		#<?php echo esc_attr( $uid ); ?> button.lxp-tsu-btn[disabled] {
			opacity: .6;
			cursor: default;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-msg {
			/* Empty at rest — the msg div is always in the DOM (aria-live needs a
			   stable node to announce into), but it should take up no space until
			   JS puts text in it. :empty is what makes that automatic: no extra
			   class to toggle, and it recovers on its own if the text is ever
			   cleared back to ''. */
			display: none;
			font-size: 14px;
			line-height: 1.4;
			margin-top: 12px;
			color: #d93025;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-tsu-msg:not(:empty) {
			display: block;
		}
		</style>

		<div id="<?php echo esc_attr( $uid ); ?>">
			<form class="lxp-tsu-form" autocomplete="off">
				<?php if ( $heading ) : ?>
				<div class="lxp-tsu-heading"><?php echo $heading; ?></div>
				<?php endif; ?>

				<?php if ( $intro ) : ?>
				<div class="lxp-tsu-intro"><?php echo $intro; ?></div>
				<?php endif; ?>

				<div class="lxp-tsu-row">
					<div>
						<label class="lxp-tsu-label" for="<?php echo esc_attr( $uid ); ?>-first"><?php esc_html_e( 'First name', 'tinylxp' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-first" class="lxp-tsu-first" required autocomplete="given-name" />
					</div>
					<div>
						<label class="lxp-tsu-label" for="<?php echo esc_attr( $uid ); ?>-last"><?php esc_html_e( 'Last name', 'tinylxp' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-last" class="lxp-tsu-last" required autocomplete="family-name" />
					</div>
				</div>

				<label class="lxp-tsu-label" for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'tinylxp' ); ?></label>
				<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" class="lxp-tsu-email" required autocomplete="email" />

				<div class="lxp-tsu-row">
					<div>
						<label class="lxp-tsu-label" for="<?php echo esc_attr( $uid ); ?>-pass"><?php esc_html_e( 'Password', 'tinylxp' ); ?></label>
						<input type="password" id="<?php echo esc_attr( $uid ); ?>-pass" class="lxp-tsu-pass" required minlength="8" autocomplete="new-password" />
					</div>
					<div>
						<label class="lxp-tsu-label" for="<?php echo esc_attr( $uid ); ?>-pass2"><?php esc_html_e( 'Confirm password', 'tinylxp' ); ?></label>
						<input type="password" id="<?php echo esc_attr( $uid ); ?>-pass2" class="lxp-tsu-pass2" required minlength="8" autocomplete="new-password" />
					</div>
				</div>

				<label class="lxp-tsu-label"><?php echo $grades_label; ?></label>
				<div class="lxp-tsu-grades">
					<?php foreach ( $grades as $grade ) : ?>
						<label>
							<input type="checkbox" class="lxp-tsu-grade" value="<?php echo esc_attr( $grade ); ?>" />
							<?php echo esc_html( $grade ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<?php // Not a grade, so it is not in lxp_get_grade_options() and never
				      // reaches the `grades` meta. It satisfies the "tick something"
				      // rule on its own, which is the whole point: an administrator
				      // signing up for PD should not have to claim a grade. ?>
				<div class="lxp-tsu-pd-row">
					<label>
						<input type="checkbox" class="lxp-tsu-pd" value="1" />
						<span><?php echo $pd_label; ?></span>
					</label>
				</div>

				<button type="submit" class="lxp-tsu-btn"><?php echo $button_label; ?></button>
				<div class="lxp-tsu-msg" aria-live="polite"></div>
			</form>
		</div>

		<script>
		(function() {
			var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!root) return;

			var form  = root.querySelector('.lxp-tsu-form');
			var btn   = root.querySelector('.lxp-tsu-btn');
			var msg   = root.querySelector('.lxp-tsu-msg');

			var signupUrl = <?php echo wp_json_encode( $signup_url ); ?>;
			var restNonce = <?php echo wp_json_encode( $nonce ); ?>;

			function fail(text) {
				msg.style.color = '#d93025';
				msg.textContent = text || 'Something went wrong. Please try again.';
				btn.removeAttribute('disabled');
			}

			function errorText(r) {
				var d = r.json && r.json.data;
				if (typeof d === 'string') { return d; }
				if (d && typeof d.message === 'string') { return d.message; }
				return 'We could not create your account. Please try again.';
			}

			form.addEventListener('submit', function(e) {
				e.preventDefault();

				var pass  = root.querySelector('.lxp-tsu-pass').value;
				var pass2 = root.querySelector('.lxp-tsu-pass2').value;

				// Mirror of the server checks, purely so the teacher gets an
				// instant answer. The endpoint re-validates everything.
				if (pass.length < 8) {
					fail('Please choose a password of at least 8 characters.');
					return;
				}
				if (pass !== pass2) {
					fail('The two passwords do not match.');
					return;
				}

				var grades = Array.prototype.slice
					.call(root.querySelectorAll('.lxp-tsu-grade:checked'))
					.map(function(cb) { return cb.value; });

				var pdBox = root.querySelector('.lxp-tsu-pd');
				var pd    = !!(pdBox && pdBox.checked);

				// Either one satisfies this. The server repeats the check.
				if (!grades.length && !pd) {
					fail('Please choose at least one grade you teach, or tick Professional Development.');
					return;
				}

				btn.setAttribute('disabled', 'disabled');
				msg.style.color = '#5f6368';
				msg.textContent = 'Creating your account…';

				var body = new FormData();
				body.append('lxp_first_name', root.querySelector('.lxp-tsu-first').value);
				body.append('lxp_last_name', root.querySelector('.lxp-tsu-last').value);
				body.append('lxp_user_email', root.querySelector('.lxp-tsu-email').value);
				body.append('lxp_user_password', pass);
				body.append('lxp_user_password_confirm', pass2);
				body.append('teacher_professional_development', pd ? '1' : '0');
				grades.forEach(function(g) { body.append('grades[]', g); });

				fetch(signupUrl, {
					method: 'POST',
					body: body,
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': restNonce }
				}).then(function(res) {
					return res.json().then(function(j) { return { ok: res.ok, json: j }; });
				}).then(function(r) {
					if (r.ok && r.json && r.json.success) {
						window.location.href = r.json.data.redirect_url;
					} else {
						fail(errorText(r));
					}
				}).catch(function() {
					fail();
				});
			});
		})();
		</script>
		<?php
	}
}
