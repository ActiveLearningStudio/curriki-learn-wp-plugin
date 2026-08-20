<?php
/**
 * Keep teachers on the front end.
 *
 * A teacher's whole workflow lives at /classes and the pages it links to. They
 * have no reason to see wp-admin, and the caps LearnPress grants `lp_teacher`
 * (edit/publish courses and lessons — see Tiny_LXP_Platform_Tool) would
 * otherwise put the WordPress editor one click away.
 *
 * Two halves, and both are needed:
 *
 *   1. `login_redirect` sends them to /classes instead of wherever WordPress
 *      would have.
 *   2. `admin_init` bounces any wp-admin request back out. Without this, the
 *      redirect is only a suggestion — a bookmark or a typed URL still works.
 *
 * These hooks are registered from this file directly rather than through
 * Tiny_LXP_Platform_Loader on purpose. The loader only runs when
 * Tiny_LXP_Platform::isOK() passes, so a failed dependency check would silently
 * hand teachers wp-admin back. An access control must not be conditional on an
 * unrelated integration being healthy.
 *
 * @package Tiny_LXP_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TL_Teacher_Access {

	/**
	 * Where a locked-down teacher belongs. Matches the redirect
	 * Rest_Lxp_Teacher_Signup hands back after self-signup.
	 */
	const LANDING_PATH = '/classes/';

	/**
	 * The role this applies to.
	 *
	 * Deliberately just `lp_teacher`. `lxp_teacher_admin` (school-level staff)
	 * is a different job with different needs — widen this only on request.
	 */
	const ROLE = 'lp_teacher';

	public static function init() {
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 99, 3 );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ), 1 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
	}

	/**
	 * @return string Absolute URL of the teacher landing page.
	 */
	public static function landing_url() {
		return home_url( self::LANDING_PATH );
	}

	/**
	 * Is this user a teacher who should be kept out of wp-admin?
	 *
	 * @param  WP_User|null $user Defaults to the current user.
	 * @return bool
	 */
	public static function is_locked_teacher( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		if ( ! ( $user instanceof WP_User ) || ! $user->ID ) {
			return false;
		}

		$roles = (array) $user->roles;

		if ( ! in_array( self::ROLE, $roles, true ) ) {
			return false;
		}

		// Escape hatch. A site administrator who also carries lp_teacher — which
		// happens when staff test the teacher experience on their own account —
		// must keep wp-admin. Locking them out is not recoverable from the front
		// end, so this check fails open for that one case only.
		if ( in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Send teachers to /classes after login.
	 *
	 * @param  string           $redirect_to           Where WordPress decided to go.
	 * @param  string           $requested_redirect_to The `redirect_to` request value.
	 * @param  WP_User|WP_Error $user                  The user, or an auth error.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		// A WP_Error here means the login failed. Never fall back to the current
		// user in that case — decide from the user who actually authenticated.
		if ( ! ( $user instanceof WP_User ) || ! self::is_locked_teacher( $user ) ) {
			return $redirect_to;
		}

		// $requested_redirect_to is ignored on purpose. It is normally the
		// wp-admin URL that bounced them to the login form in the first place,
		// which is precisely where they must not land.
		return self::landing_url();
	}

	/**
	 * Bounce teachers out of wp-admin.
	 *
	 * Hooked at priority 1 so nothing else does work on their behalf first.
	 */
	public static function block_admin() {
		// admin-ajax.php and WP-Cron both fire admin_init. Neither renders an
		// admin screen, and the front end calls admin-ajax, so redirecting them
		// would break working pages rather than close a hole.
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		if ( ! self::is_locked_teacher() ) {
			return;
		}

		wp_safe_redirect( self::landing_url(), 302 );
		exit;
	}

	/**
	 * No admin bar for a user who cannot reach anything it links to.
	 *
	 * @param  bool $show
	 * @return bool
	 */
	public static function hide_admin_bar( $show ) {
		return self::is_locked_teacher() ? false : $show;
	}
}

TL_Teacher_Access::init();
