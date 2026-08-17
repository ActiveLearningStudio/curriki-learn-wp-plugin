<?php
/*
 *  wordpress-tiny-lxp-platform - Enable WordPress to act as an Tiny LXP Platform.

 *  Copyright (C) 2022  Waqar Muneer
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 *  Contact: Waqar Muneer <waqarmuneer@gmail.com>
 */

/*
  Plugin Name: Tiny Lxp
  Plugin URI: https://github.com/i-do-dev/TinyLxp-wp-plugin
  Text Domain: TinyLxp-wp-plugin
  Description: This plugin allows WordPress to act as a Platform using the IMS Learning Tools Interoperability (Tiny LXP) specification.
  Version: 2.0.3
  Author: Waqar Muneer
  Author URI: https://github.com/i-do-dev/TinyLxp-wp-plugin
  License: GPL3
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin name.
 */
define('Tiny_LXP_PLATFORM_NAME', 'lti-platform');

/**
 * Current plugin version.
 */
define('Tiny_LXP_PLATFORM_VERSION', '2.0.3');

/**
 * Plugin root URL (with trailing slash).
 */
if (!defined('TL_PLUGIN_URL')) {
    define('TL_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-tiny-lxp-platform.php';

/**
 * Schema version for plugin-owned tables.
 *
 * Bump this whenever tl_lxp_install_tables() changes so existing installs pick
 * the change up on the next request, without a deactivate/reactivate cycle.
 */
define('TL_LXP_DB_VERSION', '1.2.0');

/**
 * Create/refresh every plugin-owned table.
 *
 * Idempotent: safe to call on every request. Uses raw CREATE TABLE IF NOT EXISTS
 * to match the existing tiny_lms_grades / lxp_workbook_submissions pattern.
 */
function tl_lxp_install_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Class membership + provisioning audit for zero-PII token students.
    // Zone A only: alias_label is a non-PII display label, never a real name.
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lxp_class_members(
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        class_id bigint(20) unsigned NOT NULL,
        student_post_id bigint(20) unsigned NOT NULL,
        student_user_id bigint(20) unsigned NOT NULL,
        alias_label varchar(64) NOT NULL,
        joined_via varchar(16) NOT NULL DEFAULT 'code',
        status varchar(16) NOT NULL DEFAULT 'active',
        claim_token_hash char(64) NOT NULL,
        claim_issued_at datetime NOT NULL,
        claim_last_used datetime NULL DEFAULT NULL,
        consent_teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
        consent_school_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY class_alias (class_id, alias_label),
        UNIQUE KEY claim_hash (claim_token_hash),
        KEY class_id (class_id),
        KEY student_user_id (student_user_id),
        KEY student_post_id (student_post_id)
    ) $charset_collate");

    // Zone B: the encrypted alias -> real name map, one blob per class.
    // The server NEVER holds the plaintext or any key that opens it. Every
    // column below is opaque ciphertext or public key-derivation parameters.
    //
    // Divergence from the client's schema: these are longtext, not longblob,
    // because the payloads are stored base64-encoded. That keeps $wpdb->prepare()
    // free of binary-escaping hazards and survives mysqldump/restore intact.
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lxp_roster_vault(
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        class_id bigint(20) unsigned NOT NULL,
        teacher_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        ciphertext longtext NOT NULL,
        iv varchar(64) NOT NULL,
        wrapped_dek_teacher longtext NOT NULL,
        wrapped_dek_escrow longtext NULL DEFAULT NULL,
        kdf_params longtext NULL DEFAULT NULL,
        version int NOT NULL DEFAULT 1,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY class_id (class_id),
        KEY teacher_user_id (teacher_user_id)
    ) $charset_collate");
}

/**
 * Run the installer when the stored schema version is behind the code.
 *
 * The plugin is already active on running sites, so relying on the activation
 * hook alone would require a deactivate/reactivate in production.
 */
function tl_lxp_maybe_upgrade_db() {
    if (get_option('tl_lxp_db_version') === TL_LXP_DB_VERSION) {
        return;
    }
    tl_lxp_install_tables();
    update_option('tl_lxp_db_version', TL_LXP_DB_VERSION);
}
add_action('plugins_loaded', 'tl_lxp_maybe_upgrade_db');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_tiny_lxp_platform()
{
    $plugin = new Tiny_LXP_Platform();
    if ($plugin->isOK()) {
        $plugin->run();
    }
}

run_tiny_lxp_platform();

register_activation_hook(__FILE__, 'on_activate');

function on_activate() {
    global $wpdb;

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tiny_lms_grades(
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lesson_id bigint(20) default NULL,
        score FLOAT default NULL,
        user_id bigint(20) default NULL,
        PRIMARY KEY (id)
    )");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lxp_workbook_submissions(
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lesson_id bigint(20) unsigned NOT NULL,
        course_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        fields longtext NOT NULL,
        submitted_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY lesson_user (lesson_id, user_id),
        KEY course_id (course_id),
        KEY user_id (user_id)
    )");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lxp_capstone_submissions(
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lesson_id bigint(20) unsigned NOT NULL,
        course_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        response longtext NOT NULL,
        evaluation longtext NULL DEFAULT NULL,
        submitted_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY lesson_user (lesson_id, user_id),
        KEY course_id (course_id),
        KEY user_id (user_id)
    )");

    // Add evaluation column to existing installs that pre-date this column.
    $eval_col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}lxp_capstone_submissions LIKE 'evaluation'" );
    if ( empty( $eval_col ) ) {
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}lxp_capstone_submissions ADD COLUMN evaluation longtext NULL DEFAULT NULL" );
    }

    // Tables owned by the versioned installer (lxp_class_members, ...).
    tl_lxp_install_tables();
    update_option('tl_lxp_db_version', TL_LXP_DB_VERSION);

    // Check if the pages already exist to avoid duplication
    $pagesArray = array(
    	['title' => 'Assignment','content' =>''],
    	['title' => 'Assignments','content' =>''],
		['title' => 'Calendar','content' =>''],
        ['title' => 'Classes','content' =>''],
		['title' => 'Courses','content' =>''],
		['title' => 'Dashboard','content' =>''],
		['title' => 'Districts','content' =>''],
		['title' => 'Grade Assignment','content' =>''],
		['title' => 'Grade Summary','content' =>''],
		['title' => 'Grades','content' =>''],
		['title' => 'Groups','content' =>''],
        ['title' => 'Lessons','content' =>''],
		['title' => 'Login','content' =>''],
		['title' => 'Sample Page','content' =>''],
		['title' => 'Schools','content' =>''],
		['title' => 'Search','content' =>''],
		['title' => 'Students','content' =>''],
		['title' => 'Teachers','content' =>''],
		['title' => 'Capstone Journal','content' =>'']
    );

    foreach ($pagesArray as $newPage) {
        $pageExist = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_title = %s AND post_type = 'page' AND post_status = 'publish' ", $newPage['title']));

        if (!$pageExist) {
            // Page does not exist, create it
            $page = array(
                'post_title'    => $newPage['title'],
                'post_content'  => $newPage['content'],
                'post_status'   => 'publish',
                'post_type'     => 'page',
            );

            wp_insert_post($page);
        }
    }
}