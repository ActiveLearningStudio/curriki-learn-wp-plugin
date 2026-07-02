<?php
/**
 * Plugin Name: H5P Auto-Complete for LearnPress
 * Plugin URI:
 * Description: Automatically marks H5P course items as completed when the learner
 *              finishes the H5P activity — no "Complete" button needed. Works for both
 *              activity types (no score) and scored types (quiz, fill-in-the-blank, etc.).
 *              Upgrade-safe: does not modify the official learnpress-h5p plugin.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * Author:      Your Name
 * Text Domain: my-h5p-autocomplete
 */

defined( 'ABSPATH' ) || exit;

class TL_H5P_AutoComplete {

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
		add_action( 'wp_ajax_h5p_auto_complete', [ __CLASS__, 'handle' ] );
	}

	// =========================================================================
	// Frontend: inject JS xAPI listener + hide the manual Complete button
	// =========================================================================

	public static function enqueue() {
		// Only act on pages where the official H5P script is loaded
		if ( ! wp_script_is( 'learn-press-h5p', 'enqueued' ) ) {
			return;
		}

		// 1. Inject a nonce variable that our inline JS will use
		wp_add_inline_script(
			'learn-press-h5p',
			'var h5pAcNonce = ' . wp_json_encode( wp_create_nonce( 'h5p_auto_complete' ) ) . ';',
			'before'
		);

		// 2. Our xAPI listener — runs AFTER lph5p.js has executed
		wp_add_inline_script( 'learn-press-h5p', self::inline_js(), 'after' );

		// 3. Hide the manual Complete button the official plugin renders
		wp_add_inline_style( 'learn-press-h5p', '#complete_h5p_button { display: none !important; }' );
	}

	private static function inline_js() {
		/*
		 * Waits for the H5P runtime, then listens for top-level xAPI events.
		 * Fires our AJAX call when the learner completes or answers the activity.
		 *
		 * Uses lpH5pSettings.id / .course_id / .ajax_url — already set by the
		 * official plugin via wp_localize_script, so no duplication needed.
		 */
		return <<<'JS'
(function () {
    'use strict';

    // Prevent the official learnpress-h5p plugin's own xAPI listener (lph5p.js)
    // from also submitting completion — it only shows a "Complete" button (which
    // we hide via CSS) when this is 'yes'; otherwise it fires a competing AJAX
    // call + reload that races with ours.
    if ( typeof lpH5pSettings !== 'undefined' ) {
        lpH5pSettings.h5p_button_complete = 'yes';
    }

    // Guard: only fire once per page load regardless of how many xAPI events
    // arrive (some content types, e.g. Arithmetic Quiz, emit both 'answered'
    // and 'completed' at the top level for the same interaction).
    var h5pAcFired = false;

    /**
     * Poll until H5P.externalDispatcher is available.
     * H5P loads asynchronously so we cannot assume it exists on DOMContentLoaded.
     */
    function waitForH5P( cb, attempts ) {
        if ( typeof H5P !== 'undefined' && H5P.externalDispatcher ) {
            cb();
        } else if ( ( attempts || 0 ) < 100 ) {
            setTimeout( function () { waitForH5P( cb, ( attempts || 0 ) + 1 ); }, 100 );
        }
    }

    /**
     * Send the auto-complete request to our PHP AJAX handler.
     */
    function autoComplete() {
        if ( h5pAcFired ) {
            return; // already submitted — ignore the duplicate event
        }

        if ( typeof lpH5pSettings === 'undefined'
            || ! lpH5pSettings.id
            || ! lpH5pSettings.course_id
        ) {
            return;
        }

        h5pAcFired = true;

        var body = new URLSearchParams( {
            action:     'h5p_auto_complete',
            lp_h5p_id:  lpH5pSettings.id,
            course_id:  lpH5pSettings.course_id,
            nonce:      h5pAcNonce,
        } );

        fetch( lpH5pSettings.ajax_url, { method: 'POST', body: body } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( data ) {
                if ( data.status === 'success' ) {
                    window.location.reload();
                }
            } )
            .catch( function ( e ) {
                h5pAcFired = false; // allow retry on genuine network failure
                console.error( '[H5P Auto-Complete] AJAX error:', e );
            } );
    }

    waitForH5P( function () {
        H5P.externalDispatcher.on( 'xAPI', function ( event ) {
            var verb      = event.getVerb();
            // Ignore sub-interaction events (they carry a parent context)
            var hasParent = event.getVerifiedStatementValue(
                [ 'context', 'contextActivities', 'parent' ]
            );

            /*
             * 'completed' fires for activity types (video, presentation, etc.)
             * 'answered'  fires for scored types (quiz, drag-text, etc.)
             * Both fire at the top level only when !hasParent.
             */
            if ( ( verb === 'completed' || verb === 'answered' ) && ! hasParent ) {
                autoComplete();
            }
        } );
    } );

}());
JS;
	}

	// =========================================================================
	// Server-side: AJAX handler
	// =========================================================================

	public static function handle() {
		// Security check
		check_ajax_referer( 'h5p_auto_complete', 'nonce' );

		$lp_h5p_id = absint( $_POST['lp_h5p_id'] ?? 0 );
		$course_id  = absint( $_POST['course_id']  ?? 0 );

		if ( ! $lp_h5p_id || ! $course_id ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Invalid parameters.' ] );
		}

		$user = learn_press_get_current_user();
		if ( ! $user || $user->is( 'guest' ) ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Not logged in.' ] );
		}

		$course = learn_press_get_course( $course_id );
		if ( ! $course || ! $course->has_item( $lp_h5p_id ) ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Item not found in course.' ] );
		}

		if ( ! $user->has_enrolled_course( $course_id ) ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Not enrolled in course.' ] );
		}

		if ( $user->has_finished_course( $course_id ) ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Course already finished.' ] );
		}

		// Idempotent — if already completed, return success without re-writing
		if ( $user->has_item_status( [ 'completed' ], $lp_h5p_id, $course_id ) ) {
			wp_send_json( [ 'status' => 'success', 'message' => 'Already completed.' ] );
		}

		// -----------------------------------------------------------------------
		// Determine score and graduation
		// Defaults cover activity types that store no score in the H5P results DB.
		// -----------------------------------------------------------------------
		$score      = 0;
		$max_score  = 0;
		$graduation = 'passed'; // safe default: activity type = just doing it = passed

		$h5p_interact  = (int) get_post_meta( $lp_h5p_id, '_lp_h5p_interact', true );
		$passing_grade = (float) ( get_post_meta( $lp_h5p_id, '_lp_passing_grade', true ) ?: 50 );

		if ( $h5p_interact && class_exists( 'H5P_Plugin_Admin' ) ) {
			$results = H5P_Plugin_Admin::get_instance()
				->get_results( $h5p_interact, $user->get_id(), 0, 1, 1 );

			if ( ! empty( $results[0] ) && is_object( $results[0] ) ) {
				$score     = (float) $results[0]->score;
				$max_score = (float) $results[0]->max_score;

				if ( $max_score > 0 ) {
					// Scored activity: calculate pass/fail from percentage
					$pct        = ( $score / $max_score ) * 100;
					$graduation = $pct >= $passing_grade ? 'passed' : 'failed';
				}
				// max_score === 0 → activity type with a result row → graduation stays 'passed'
			}
			// No result row at all → pure activity type → graduation stays 'passed'
		}

		// -----------------------------------------------------------------------
		// Retrieve existing user_item_id (0 if the user has never started this item)
		// learn_press_update_h5p_item() will INSERT when 0, UPDATE otherwise.
		// -----------------------------------------------------------------------
		$user_item_id = 0;
		$course_data  = $user->get_course_data( $course_id );
		if ( $course_data ) {
			$existing = $course_data->get_item( $lp_h5p_id );
			if ( $existing ) {
				$user_item_id = (int) $existing->get_user_item_id();
			}
		}

		// -----------------------------------------------------------------------
		// Persist completion via the official plugin's helper function
		// -----------------------------------------------------------------------
		if ( ! function_exists( 'learn_press_update_h5p_item' ) ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'LearnPress H5P plugin is required.' ] );
		}

		$result_id = learn_press_update_h5p_item(
			$lp_h5p_id,
			$course_id,
			$user,
			'completed',
			$user_item_id
		);

		if ( ! $result_id ) {
			wp_send_json( [ 'status' => 'error', 'message' => 'Could not save completion.' ] );
		}

		// Store graduation and raw score for reporting / evaluation methods
		learn_press_update_user_item_field(
			[ 'graduation' => $graduation ],
			[ 'user_item_id' => $result_id ]
		);
		learn_press_update_user_item_meta( $result_id, 'score',     $score );
		learn_press_update_user_item_meta( $result_id, 'max_score', $max_score );

		wp_send_json( [
			'status'  => 'success',
			'message' => __( 'You have completed this H5P activity!', 'tl-h5p-autocomplete' ),
		] );
	}
}

