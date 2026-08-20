<?php
/**
 * LXP Class Join — zero-PII student enrollment form (Zone A).
 *
 * A student enters a class registration code and picks a seat label. No name,
 * email, date of birth or password field exists anywhere in this markup — that
 * absence IS the form-level enforcement the privacy spec requires. The teacher's
 * act of issuing the code is the COPPA school-consent gate.
 *
 * Also handles the claim-link return path: a `?claim=` query arg resumes the
 * student's existing token account without consuming a second seat.
 *
 * @see docs/student-privacy-zone-a-context.md
 */

namespace Edudeme\Elementor;

use Elementor\Controls_Manager;

class LXP_Class_Join_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'lxp-class-join'; }
	public function get_title()      { return esc_html__( 'LXP Class Join', 'tinylxp' ); }
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
			'default' => esc_html__( 'Join your class', 'tinylxp' ),
		] );

		$this->add_control( 'code_label', [
			'label'   => esc_html__( 'Code Field Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Class code', 'tinylxp' ),
		] );

		$this->add_control( 'alias_label', [
			'label'       => esc_html__( 'Nickname Field Label', 'tinylxp' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => esc_html__( 'Choose a nickname', 'tinylxp' ),
			'description' => esc_html__( 'Keep this asking for a nickname. Students type this freely and it becomes their display name on the server, so wording that invites a real name defeats the point of the privacy design.', 'tinylxp' ),
		] );

		$this->add_control( 'button_label', [
			'label'   => esc_html__( 'Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Join', 'tinylxp' ),
		] );

		$this->add_control( 'privacy_note', [
			'label'       => esc_html__( 'Privacy Note', 'tinylxp' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => esc_html__( 'We never ask for your name, email or birthday.', 'tinylxp' ),
			'description' => esc_html__( 'Shown under the form. Leave blank to hide.', 'tinylxp' ),
		] );

		$this->end_controls_section();

		// ── Ticket screen ─────────────────────────────────────────────────
		// Shown once, straight after a successful join. The claim link exists
		// in plaintext only at this moment — the server stores nothing but its
		// hash — so this screen is the student's single chance to keep it.
		$this->start_controls_section( 'section_ticket', [
			'label' => esc_html__( 'Ticket Screen', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'ticket_heading', [
			'label'   => esc_html__( 'Heading', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'You are in!', 'tinylxp' ),
		] );

		$this->add_control( 'ticket_note', [
			'label'       => esc_html__( 'Instruction', 'tinylxp' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => esc_html__( 'This is your ticket back into class! Ask your teacher to help you save or bookmark this page.', 'tinylxp' ),
			'description' => esc_html__( 'Shown above the class link. This is the only time the link is ever shown.', 'tinylxp' ),
		] );

		$this->add_control( 'ticket_copy_label', [
			'label'   => esc_html__( 'Copy Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Copy my link', 'tinylxp' ),
		] );

		$this->add_control( 'ticket_continue_label', [
			'label'   => esc_html__( 'Continue Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Go to my class', 'tinylxp' ),
		] );

		$this->end_controls_section();

		// ── Style ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => esc_html__( 'Style', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'box_bg_color', [
			'label'   => esc_html__( 'Box Background', 'tinylxp' ),
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

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$uid           = 'lxp-cj-' . $this->get_id();
		$heading       = esc_html( $settings['heading'] );
		$code_label    = esc_html( $settings['code_label'] );
		$alias_label   = esc_html( $settings['alias_label'] );
		$button_label  = esc_html( $settings['button_label'] );
		$privacy_note  = esc_html( $settings['privacy_note'] );

		$ticket_heading  = esc_html( $settings['ticket_heading'] );
		$ticket_note     = esc_html( $settings['ticket_note'] );
		$ticket_copy     = esc_html( $settings['ticket_copy_label'] );
		$ticket_continue = esc_html( $settings['ticket_continue_label'] );

		$box_bg    = esc_attr( $settings['box_bg_color'] );
		$text_col  = esc_attr( $settings['text_color'] );
		$btn_bg    = esc_attr( $settings['btn_bg_color'] );
		$btn_text  = esc_attr( $settings['btn_text_color'] );

		$redeem_url = esc_url_raw( rest_url( 'lms/v1/class/redeem' ) );
		$claim_url  = esc_url_raw( rest_url( 'lms/v1/class/claim' ) );
		$seats_url  = esc_url_raw( rest_url( 'lms/v1/class/seats' ) );

		if ( is_user_logged_in() ) {
			echo '<div class="lxp-cj-signed-in" style="text-align:center;color:' . $text_col . '">'
				. esc_html__( 'You are signed in.', 'tinylxp' )
				. '</div>';
			return;
		}
		?>
		<style>
		#<?php echo esc_attr( $uid ); ?> {
			max-width: 380px;
			margin: 0 auto;
			background: <?php echo $box_bg; ?>;
			color: <?php echo $text_col; ?>;
			border-radius: 8px;
			box-shadow: 0 1px 3px rgba(0,0,0,.2), 0 1px 2px rgba(0,0,0,.12);
			padding: 24px;
			font-family: 'Google Sans', Roboto, Arial, sans-serif;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-heading {
			font-size: 18px;
			font-weight: 500;
			margin: 0 0 16px;
			color: <?php echo $text_col; ?>;
		}
		#<?php echo esc_attr( $uid ); ?> label {
			display: block;
			font-size: 13px;
			margin-bottom: 6px;
			color: <?php echo $text_col; ?>;
		}
		#<?php echo esc_attr( $uid ); ?> input[type="text"] {
			width: 100%;
			box-sizing: border-box;
			padding: 10px 12px;
			font-size: 15px;
			border: 1px solid #dadce0;
			border-radius: 6px;
			margin-bottom: 16px;
			background: #fff;
		}
		#<?php echo esc_attr( $uid ); ?> input[type="text"].lxp-cj-code {
			text-transform: uppercase;
			letter-spacing: 2px;
			font-weight: 600;
		}
		#<?php echo esc_attr( $uid ); ?> button {
			width: 100%;
			padding: 10px 16px;
			font-size: 15px;
			font-weight: 500;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			background: <?php echo $btn_bg; ?>;
			color: <?php echo $btn_text; ?>;
			transition: opacity .15s ease;
		}
		#<?php echo esc_attr( $uid ); ?> button:hover { opacity: .92; }
		#<?php echo esc_attr( $uid ); ?> button[disabled] { opacity: .6; cursor: default; }
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-msg {
			margin-top: 12px;
			font-size: 13px;
			min-height: 16px;
			color: #d93025;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-note {
			margin-top: 14px;
			font-size: 12px;
			color: #5f6368;
			text-align: center;
		}
		/* Resting state. Revealed by showAlias() with an explicit display:block —
		   an empty string would only drop the inline value and let this rule win
		   again. */
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-seat-wrap { display: none; }
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-class-name {
			font-size: 13px;
			color: #137333;
			min-height: 18px;
			margin: -6px 0 10px;
		}

		/* ── Ticket screen ─────────────────────────────────────────────── */
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-ticket { text-align: center; }
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-ticket-heading {
			font-size: 20px;
			font-weight: 500;
			margin: 0 0 8px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-seat-badge {
			display: inline-block;
			background: #e8f0fe;
			color: #1967d2;
			border-radius: 12px;
			padding: 4px 14px;
			font-size: 14px;
			font-weight: 500;
			margin-bottom: 16px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-ticket-note {
			font-size: 15px;
			line-height: 1.5;
			margin-bottom: 16px;
			text-align: left;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-ticket-link {
			display: block;
			word-break: break-all;
			font-family: 'Roboto Mono', Consolas, monospace;
			font-size: 12px;
			line-height: 1.5;
			background: #f1f3f4;
			border: 1px solid #dadce0;
			border-radius: 6px;
			padding: 10px 12px;
			margin-bottom: 12px;
			color: #1a73e8;
			text-align: left;
			text-decoration: none;
		}
		#<?php echo esc_attr( $uid ); ?> button.lxp-cj-copy {
			background: #fff;
			color: <?php echo $btn_bg; ?>;
			border: 1px solid #dadce0;
			margin-bottom: 10px;
		}
		#<?php echo esc_attr( $uid ); ?> .lxp-cj-copied {
			font-size: 12px;
			color: #137333;
			min-height: 16px;
			margin-bottom: 6px;
		}
		</style>

		<div class="lxp-cj" id="<?php echo esc_attr( $uid ); ?>">

			<form class="lxp-cj-form" autocomplete="off">
				<?php if ( $heading ) : ?>
				<div class="lxp-cj-heading"><?php echo $heading; ?></div>
				<?php endif; ?>

				<label for="<?php echo esc_attr( $uid ); ?>-code"><?php echo $code_label; ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-code" class="lxp-cj-code"
				       maxlength="6" required autocapitalize="characters" spellcheck="false" />

				<div class="lxp-cj-class-name" aria-live="polite"></div>

				<!--
					One control, always: the student types a nickname. The old
					alternative — a dropdown of teacher-assigned seat labels — was
					dropped at the client's request. Keep the wording pointed at a
					nickname: with no dropdown left, that framing plus the server's
					looks_like_pii() screen is what keeps real names off the server.
				-->
				<div class="lxp-cj-seat-wrap">
					<label for="<?php echo esc_attr( $uid ); ?>-alias"><?php echo $alias_label; ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-alias" class="lxp-cj-alias"
					       maxlength="32" autocomplete="off" spellcheck="false"
					       placeholder="<?php echo esc_attr__( 'Choose a nickname', 'tinylxp' ); ?>" />
				</div>

				<button type="submit" class="lxp-cj-btn"><?php echo $button_label; ?></button>
				<div class="lxp-cj-msg" aria-live="polite"></div>
				<?php if ( $privacy_note ) : ?>
				<div class="lxp-cj-note"><?php echo $privacy_note; ?></div>
				<?php endif; ?>
			</form>

			<!--
				Shown once, after a successful join. The raw claim link exists only
				here: the server keeps a SHA-256 hash of it and nothing else, so it
				can never be re-shown. If the student loses it, the teacher must
				re-issue from the Roster modal, which rotates the secret.
			-->
			<div class="lxp-cj-ticket" hidden>
				<?php if ( $ticket_heading ) : ?>
				<div class="lxp-cj-ticket-heading"><?php echo $ticket_heading; ?></div>
				<?php endif; ?>

				<div class="lxp-cj-seat-badge"></div>

				<?php if ( $ticket_note ) : ?>
				<div class="lxp-cj-ticket-note"><?php echo $ticket_note; ?></div>
				<?php endif; ?>

				<a class="lxp-cj-ticket-link" href="#" rel="nofollow"></a>

				<button type="button" class="lxp-cj-copy"><?php echo $ticket_copy; ?></button>
				<div class="lxp-cj-copied" aria-live="polite"></div>
				<button type="button" class="lxp-cj-continue"><?php echo $ticket_continue; ?></button>
			</div>

		</div>

		<script>
		(function() {
			var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!root) return;

			var form        = root.querySelector('.lxp-cj-form');
			var codeInput   = root.querySelector('.lxp-cj-code');
			var seatWrap    = root.querySelector('.lxp-cj-seat-wrap');
			var aliasIn     = root.querySelector('.lxp-cj-alias');
			var classNameEl = root.querySelector('.lxp-cj-class-name');
			var btn         = root.querySelector('.lxp-cj-btn');
			var msg         = root.querySelector('.lxp-cj-msg');

			var ticket      = root.querySelector('.lxp-cj-ticket');
			var ticketBadge = root.querySelector('.lxp-cj-seat-badge');
			var ticketLink  = root.querySelector('.lxp-cj-ticket-link');
			var copyBtn     = root.querySelector('.lxp-cj-copy');
			var copiedMsg   = root.querySelector('.lxp-cj-copied');
			var continueBtn = root.querySelector('.lxp-cj-continue');

			var redeemUrl = <?php echo wp_json_encode( $redeem_url ); ?>;
			var claimUrl  = <?php echo wp_json_encode( $claim_url ); ?>;
			var seatsUrl  = <?php echo wp_json_encode( $seats_url ); ?>;

			var params = new URLSearchParams(window.location.search);

			// Mirrors Rest_Lxp_Class_Redemption::ALIAS_PATTERN and looks_like_pii().
			// A convenience only — the server re-checks both and stays the authority.
			var ALIAS_PATTERN = /^[A-Za-z0-9 ._-]{2,32}$/;

			function looksLikePii(alias) {
				if (alias.indexOf('@') !== -1) { return true; }
				return alias.replace(/\D/g, '').length >= 7;
			}

			function fail(text) {
				msg.textContent = text || 'Something went wrong. Please try again.';
				btn.removeAttribute('disabled');
			}

			function post(url, fields) {
				var body = new FormData();
				Object.keys(fields).forEach(function(k) { body.append(k, fields[k]); });
				return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function(res) {
						return res.json().then(function(j) { return { ok: res.ok, json: j }; });
					});
			}

			function errorText(r) {
				var d = r.json && r.json.data;
				if (typeof d === 'string') { return d; }
				if (d && typeof d.message === 'string') { return d.message; }
				return 'That class code could not be used. Please check it with your teacher.';
			}

			// --- Returning student: a claim link resumes the same account. ----
			var claimToken = params.get('claim');
			if (claimToken) {
				msg.style.color = '#5f6368';
				msg.textContent = 'Signing you in…';
				btn.setAttribute('disabled', 'disabled');
				post(claimUrl, { claim_token: claimToken }).then(function(r) {
					if (r.ok && r.json && r.json.success) {
						window.location.href = r.json.data.redirect_url;
					} else {
						msg.style.color = '#d93025';
						fail('That link is no longer valid. Ask your teacher for a new one.');
					}
				}).catch(function() {
					msg.style.color = '#d93025';
					fail();
				});
				return;
			}

			function hideAlias() {
				// 'none' beats the stylesheet; see showAlias() for why the inverse
				// is not simply ''.
				seatWrap.style.display = 'none';
				classNameEl.textContent = '';
			}

			function showAlias() {
				// MUST be an explicit 'block', never ''. The widget's own stylesheet
				// carries `.lxp-cj-seat-wrap { display: none }` as the resting state,
				// and assigning '' only clears the *inline* declaration — the cascade
				// then falls straight back to that rule and the field stays hidden.
				// That was the bug that made the nickname field impossible to reach.
				seatWrap.style.display = 'block';
			}

			// --- Check the code once it is complete. --------------------------
			// The student types a nickname, so there is no seat list to fetch; this
			// exists to confirm *which* class the code opens, and to catch a full
			// class, before they bother typing anything.
			function loadSeats(code) {
				return post(seatsUrl, { class_code: code }).then(function(r) {
					if (!r.ok || !r.json || !r.json.success) {
						hideAlias();
						msg.style.color = '#d93025';
						msg.textContent = errorText(r);
						return false;
					}

					var d = r.json.data;

					if (d.is_full) {
						hideAlias();
						msg.style.color = '#d93025';
						msg.textContent = 'This class is full. Please check with your teacher.';
						return false;
					}

					classNameEl.textContent = d.class_name ? 'Joining: ' + d.class_name : '';
					msg.textContent = '';
					showAlias();
					aliasIn.focus();
					return true;
				}).catch(function() {
					hideAlias();
					return false;
				});
			}

			// Only look a code up once — retyping the same 6 chars must not
			// spam the endpoint and burn the student's own rate-limit budget.
			var lastLookedUp = '';

			codeInput.addEventListener('input', function() {
				codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
				if (codeInput.value.length === 6) {
					if (codeInput.value === lastLookedUp) { return; }
					lastLookedUp = codeInput.value;
					loadSeats(codeInput.value);
				} else {
					hideAlias();
				}
			});

			// Deep link: ?class_code=XYZ pre-fills and checks the code immediately.
			var preCode = params.get('class_code');
			if (preCode) {
				codeInput.value = preCode.toUpperCase().replace(/[^A-Z0-9]/g, '');
				lastLookedUp = codeInput.value;
				loadSeats(codeInput.value);
			}

			// --- Join. -------------------------------------------------------
			form.addEventListener('submit', function(e) {
				e.preventDefault();
				msg.style.color = '#d93025';
				msg.textContent = '';

				var code  = codeInput.value.trim().toUpperCase();
				var alias = aliasIn.value.trim();

				if (!code) { return; }
				if (!alias) {
					msg.textContent = 'Please type a nickname.';
					return;
				}
				if (!ALIAS_PATTERN.test(alias)) {
					msg.textContent = 'Nicknames need 2 to 32 letters or numbers.';
					return;
				}
				if (looksLikePii(alias)) {
					msg.textContent = 'Please pick a nickname — not an email address or phone number.';
					return;
				}

				btn.setAttribute('disabled', 'disabled');

				post(redeemUrl, { class_code: code, alias_label: alias }).then(function(r) {
					if (r.ok && r.json && r.json.success) {
						// Belt and braces: stash it too, so a student who clicks
						// straight past the ticket screen can still be recovered
						// on this device.
						try {
							window.localStorage.setItem('lxp_claim_' + code, r.json.data.claim_url);
						} catch (err) { /* private mode — non-fatal */ }
						showTicket(r.json.data);
					} else {
						fail(errorText(r));
					}
				}).catch(function() {
					fail();
				});
			});

			// --- Ticket screen. ----------------------------------------------
			// The raw claim link is only ever available right here. Swap the form
			// out for it rather than redirecting straight past it.
			function showTicket(data) {
				form.hidden   = true;
				ticket.hidden = false;

				ticketBadge.textContent = data.alias_label || '';
				ticketLink.textContent  = data.claim_url;
				ticketLink.href         = data.claim_url;

				// Navigate to the claim URL itself, not redirect_url. That URL *is*
				// the Student Courses page (plus ?claim & ?class_code), so the
				// address bar then holds the exact link worth bookmarking — there
				// is no browser API to add a bookmark for us, only the user's own
				// Ctrl+D on the page they are standing on.
				continueBtn.onclick = function() {
					window.location.href = data.claim_url;
				};

				root.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}

			copyBtn.addEventListener('click', function() {
				var url = ticketLink.href;

				function done() {
					copiedMsg.textContent = 'Link copied. Save it somewhere safe!';
				}

				// navigator.clipboard is unavailable on plain http, which schools
				// and local dev both hit — fall back to a throwaway textarea.
				if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
					window.navigator.clipboard.writeText(url).then(done).catch(legacyCopy);
				} else {
					legacyCopy();
				}

				function legacyCopy() {
					var ta = document.createElement('textarea');
					ta.value = url;
					ta.setAttribute('readonly', '');
					ta.style.position = 'absolute';
					ta.style.left = '-9999px';
					document.body.appendChild(ta);
					ta.select();
					try { document.execCommand('copy'); done(); }
					catch (err) { copiedMsg.textContent = 'Press and hold the link to copy it.'; }
					document.body.removeChild(ta);
				}
			});
		})();
		</script>
		<?php
	}
}
