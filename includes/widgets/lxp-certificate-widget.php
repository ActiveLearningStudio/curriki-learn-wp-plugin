<?php
/**
 * LXP Certificate — course completion certificate, filled in and downloaded.
 *
 * Placed on the page chosen under Curriki Learn -> Certificates. Learners land
 * here (?course_id=X) after finishing a course flagged "Issue certificate".
 * The certificate itself is the form: name, email and school are typed straight
 * onto it, saved via POST /lms/v1/certificate/save, and the PDF is captured in
 * the browser (html2canvas + jsPDF, assets/js/lxp-certificate.js) so it matches
 * the client's design exactly.
 *
 * Design source of truth: docs/Curriki-Grant-Application-Course-Certificate.html.
 * The markup in render_certificate() is that file's <main class="cert"> copied
 * verbatim (logo moved to assets/img, <main> -> <div>, and the seal <svg> wrapped
 * in <div class="seal"> because html2canvas drops an absolutely-positioned
 * inline SVG from the capture); only the dynamic text is substituted. Keep it that way — see docs/certificate-context.md.
 *
 * @see lms/repositories/class-certificate-repository.php
 * @see lms/lms-rest-apis/certificate.php
 */

namespace Edudeme\Elementor;

use Elementor\Controls_Manager;

class LXP_Certificate_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'lxp-certificate'; }
	public function get_title()      { return esc_html__( 'LXP Certificate', 'tinylxp' ); }
	public function get_icon()       { return 'eicon-document-file'; }
	public function get_categories() { return [ 'general' ]; }

	public function get_style_depends()  { return [ 'lxp-certificate' ]; }
	public function get_script_depends() { return [ 'lxp-certificate' ]; }

	protected function register_controls() {

		// ── Certificate text ──────────────────────────────────────────────
		$this->start_controls_section( 'section_text', [
			'label' => esc_html__( 'Certificate Text', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'pill_text', [
			'label'   => esc_html__( 'Pill', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => 'Free AI Course',
		] );

		$this->add_control( 'band_label', [
			'label'   => esc_html__( 'Band Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => 'Completed',
		] );

		$this->add_control( 'band_tagline', [
			'label'   => esc_html__( 'Band Tagline', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => 'Free AI course for schools and teachers',
		] );

		$this->add_control( 'band_url', [
			'label'   => esc_html__( 'Band URL Text', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => 'grants.curriki.org',
		] );

		$this->add_control( 'default_subtitle', [
			'label'       => esc_html__( 'Default Course Subtitle', 'tinylxp' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => 'A free AI course from Curriki AI Grants',
			'description' => esc_html__( 'Used when the course has no subtitle of its own (Curriki Certificate box on the course edit screen).', 'tinylxp' ),
		] );

		$this->add_control( 'setup_help', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Select this page under Curriki Learn → Certificates in wp-admin, and tick "Issue certificate" on each course. The course title, completion date and learner details are filled in automatically.', 'tinylxp' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		] );

		$this->end_controls_section();

		// ── Buttons & messages ────────────────────────────────────────────
		$this->start_controls_section( 'section_actions', [
			'label' => esc_html__( 'Buttons & Messages', 'tinylxp' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'save_label', [
			'label'   => esc_html__( 'Save Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Save details', 'tinylxp' ),
		] );

		$this->add_control( 'download_label', [
			'label'   => esc_html__( 'Download Button Label', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Download PDF', 'tinylxp' ),
		] );

		$this->add_control( 'not_available_text', [
			'label'   => esc_html__( 'No Certificate Message', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'No certificate is available for this course.', 'tinylxp' ),
		] );

		$this->add_control( 'not_complete_text', [
			'label'   => esc_html__( 'Not Completed Message', 'tinylxp' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Complete this course to receive your certificate.', 'tinylxp' ),
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$is_edit  = \Elementor\Plugin::$instance->editor->is_edit_mode();

		// The editor gets the empty design so it can be selected and checked.
		if ( $is_edit ) {
			$this->render_certificate( $settings, [
				'course_id' => 0,
				'title'     => 'Grant Application Course',
				'subtitle'  => $settings['default_subtitle'],
				'name'      => '',
				'email'     => '',
				'school'    => '',
				'date'      => '',
				'filename'  => $this->certificate_filename( 'Grant Application Course' ),
			] );
			return;
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

		if ( ! is_user_logged_in() ) {
			$return_url = $course_id ? add_query_arg( 'course_id', $course_id, get_permalink() ) : get_permalink();
			$this->render_notice( sprintf(
				'%s <a href="%s">%s</a>',
				esc_html__( 'Please log in to view your certificate.', 'tinylxp' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Log in', 'tinylxp' )
			) );
			return;
		}

		$course    = $course_id ? get_post( $course_id ) : null;
		$repo      = new \TL_Certificate_Repository();

		if ( ! $course || TL_COURSE_CPT !== $course->post_type || ! $repo->is_course_enabled( $course_id ) ) {
			$this->render_notice( esc_html( $settings['not_available_text'] ) );
			return;
		}

		$user_id  = get_current_user_id();
		$end_time = $repo->get_completion( $user_id, $course_id );

		if ( null === $end_time ) {
			$this->render_notice( sprintf(
				'%s <a href="%s">%s</a>',
				esc_html( $settings['not_complete_text'] ),
				esc_url( get_permalink( $course_id ) ),
				esc_html__( 'Go to the course', 'tinylxp' )
			) );
			return;
		}

		$saved    = $repo->get( $user_id, $course_id );
		$details  = $saved ? $saved : $repo->get_prefill( $user_id );
		$subtitle = $repo->get_subtitle( $course_id );

		$this->render_certificate( $settings, [
			'course_id' => $course_id,
			// Not get_the_title(): it prepends "Private:" / "Protected:" for non-public
			// courses, which must never print on a certificate.
			'title'     => apply_filters( 'the_title', $course->post_title, $course->ID ),
			'subtitle'  => '' !== $subtitle ? $subtitle : $settings['default_subtitle'],
			'name'      => $details['name'],
			'email'     => $details['email'],
			'school'    => $details['school'],
			'date'      => $repo->format_completion_date( $end_time ),
			'filename'  => $this->certificate_filename( $course->post_title ),
		] );
	}

	/**
	 * Presentable PDF file name: "Certificate - {Course Title}.pdf".
	 *
	 * Keeps the title as authored (case, spaces, dashes). Only characters that
	 * Windows/macOS reject in file names are swapped for "_", and the result is
	 * capped well under the 255-byte file-name limit.
	 */
	private function certificate_filename( $course_title ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $course_title ), ENT_QUOTES, 'UTF-8' );
		$title = preg_replace( '/[\\\\\/:*?"<>|\x00-\x1F\x7F]/u', '_', $title );
		$title = trim( preg_replace( '/\s+/u', ' ', $title ), " ._" );

		if ( function_exists( 'mb_substr' ) && mb_strlen( $title ) > 150 ) {
			$title = rtrim( mb_substr( $title, 0, 150 ), " ._" );
		}

		return 'Certificate - ' . ( '' !== $title ? $title : 'Course' ) . '.pdf';
	}

	private function render_notice( $html ) {
		echo '<div class="lxp-cert-wrap"><div class="lxp-cert-notice"><p>' . wp_kses_post( $html ) . '</p></div></div>';
	}

	/**
	 * The certificate — markup copied verbatim from the design file.
	 */
	private function render_certificate( $settings, $data ) {
		$uid      = 'lxp-cert-' . $this->get_id();
		$logo_url = plugins_url( 'assets/img/curriki-ai-grants-logo.svg', __FILE__ );
		?>
		<div class="lxp-cert-wrap" id="<?php echo esc_attr( $uid ); ?>"
			data-save-url="<?php echo esc_url( rest_url( 'lms/v1/certificate/save' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-course-id="<?php echo esc_attr( $data['course_id'] ); ?>"
			data-filename="<?php echo esc_attr( $data['filename'] ); ?>">
			<div class="lxp-cert-viewport">
			<div class="lxp-cert-stage">
<div class="cert" role="region" aria-label="Certificate of completion">
<aside class="band" aria-hidden="false">
  <i style="left:-90px;top:-70px;width:300px;height:300px;background:#285AA5;opacity:.45"></i>
  <i style="left:130px;top:610px;width:230px;height:230px;background:#fff;opacity:.06"></i>
  <i style="left:-60px;top:690px;width:150px;height:150px;background:#285AA5;opacity:.5"></i>
  <i style="left:206px;top:232px;width:16px;height:16px;background:var(--y1)"></i>
  <i style="left:34px;top:300px;width:10px;height:10px;background:var(--y3);opacity:.9"></i>
  <i style="left:198px;top:640px;width:12px;height:12px;background:var(--y3);opacity:.9"></i>
  <img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Curriki AI grants" width="188" height="90">
  <div class="seal"><svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150" aria-hidden="true">
    <circle cx="75" cy="75" r="72" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width="1.5" stroke-dasharray="2 7" stroke-linecap="round"/>
    <circle cx="75" cy="75" r="56" fill="#F8AF2C"/>
    <circle cx="75" cy="75" r="56" fill="none" stroke="#FFCA70" stroke-width="3"/>
    <path d="M52 77.5l16 16 30-33" fill="none" stroke="#063A75" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
  </svg></div>
  <div class="t t1"><?php echo esc_html( $settings['band_label'] ); ?></div>
  <div class="t t2"><?php echo esc_html( $settings['band_tagline'] ); ?></div>
  <div class="t url"><?php echo esc_html( $settings['band_url'] ); ?></div>
</aside>
<section class="main">
  <div class="pill"><?php echo esc_html( $settings['pill_text'] ); ?></div>
  <h1>Certificate of <em>Completion</em></h1>
  <p class="lead l1">This certifies that</p>
  <label class="field name"><input type="text" name="full_name" autocomplete="name" placeholder="Type your full name" maxlength="100" required value="<?php echo esc_attr( $data['name'] ); ?>"><span>Full name</span></label>
  <p class="lead l2">has successfully completed the</p>
  <div class="card">
    <div class="ico"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#084892" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg></div>
    <div class="card-text"><h2><?php echo esc_html( $data['title'] ); ?></h2><p><?php echo esc_html( $data['subtitle'] ); ?></p></div>
  </div>
  <label class="field row email"><input type="email" name="email" autocomplete="email" placeholder="name@school.org" value="<?php echo esc_attr( $data['email'] ); ?>"><span>Email address</span></label>
  <label class="field row school"><input type="text" name="school" autocomplete="organization" placeholder="Your school name" maxlength="150" value="<?php echo esc_attr( $data['school'] ); ?>"><span>School name</span></label>
  <label class="field row date"><input type="text" name="date" placeholder="Month DD, YYYY" readonly tabindex="-1" value="<?php echo esc_attr( $data['date'] ); ?>"><span>Date completed</span></label>
</section>
</div>
			</div>
			</div>
			<div class="lxp-cert-actions">
				<button type="button" class="lxp-cert-btn lxp-cert-btn--primary" data-action="download"><?php echo esc_html( $settings['download_label'] ); ?></button>
				<button type="button" class="lxp-cert-btn lxp-cert-btn--secondary" data-action="save"><?php echo esc_html( $settings['save_label'] ); ?></button>
				<span class="lxp-cert-status" role="status" aria-live="polite"></span>
			</div>
		</div>
		<?php
	}
}
