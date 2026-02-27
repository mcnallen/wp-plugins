<?php
/**
 * Plugin Name:       ccQuestions — Community Q&A Forum
 * Plugin URI:        https://github.com/mcnallen/wp-plugins
 * Description:       SEO-first community Q&A with QAPage JSON-LD schema, voting, badges, leaderboard, and email notifications. Optimised for Google and Generative Engine Optimization (GEO / AI search).
 * Version:           2.9.0
 * Author:            CreatorConnected
 * Author URI:        https://creatorconnected.com
 * Text Domain:       ccquestions
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( defined( 'CC_QA_VERSION' ) ) return;

define( 'CC_QA_VERSION',  '2.9.0' );
define( 'CC_QA_PATH',     plugin_dir_path( __FILE__ ) );
define( 'CC_QA_URL',      plugin_dir_url( __FILE__ ) );
define( 'CC_QA_BASENAME', plugin_basename( __FILE__ ) );

require_once CC_QA_PATH . 'includes/class-admin.php';
require_once CC_QA_PATH . 'includes/class-database.php';
require_once CC_QA_PATH . 'includes/class-post-types.php';
require_once CC_QA_PATH . 'includes/class-ajax.php';
require_once CC_QA_PATH . 'includes/class-email.php';
require_once CC_QA_PATH . 'includes/class-shortcode.php';
require_once CC_QA_PATH . 'includes/class-leaderboard.php';
require_once CC_QA_PATH . 'includes/class-schema.php';
require_once CC_QA_PATH . 'includes/class-digest.php';
require_once CC_QA_PATH . 'includes/class-badges.php';

function cc_qa_init() {
    CC_QA_Post_Types::init();
    CC_QA_Ajax::init();
    CC_QA_Shortcode::init();
    CC_QA_Leaderboard::init();
    CC_QA_Admin::init();
    CC_QA_Schema::init();
    CC_QA_Digest::init();

    // Token-based unsubscribe — works without login, no nonce needed (token IS the auth)
    add_action( 'wp_ajax_nopriv_cc_qa_unsubscribe', array( 'CC_QA_Email', 'handle_unsubscribe' ) );
    add_action( 'wp_ajax_cc_qa_unsubscribe',        array( 'CC_QA_Email', 'handle_unsubscribe' ) );
}
add_action( 'plugins_loaded', 'cc_qa_init' );

register_activation_hook( __FILE__, function() {
    try {
        CC_QA_Database::install();
    } catch ( Exception $e ) {
        wp_die(
            'ccQuestions activation error: ' . esc_html( $e->getMessage() ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }
    // Register CPTs and rewrite rules before flushing so /questions/author/ resolves
    CC_QA_Post_Types::register();
    CC_QA_Post_Types::register_taxonomies();
    CC_QA_Post_Types::add_rewrite_rules();
    flush_rewrite_rules();
    CC_QA_Digest::schedule();
} );
register_deactivation_hook( __FILE__, function() {
    CC_QA_Database::deactivate();
    CC_QA_Digest::unschedule();
} );

add_action( 'wp_enqueue_scripts', 'cc_qa_register_assets' );
function cc_qa_register_assets() {
    wp_register_style(  'cc-qa-style',  CC_QA_URL . 'assets/css/ccquestions.css',  array(), CC_QA_VERSION );
    wp_register_script( 'cc-qa-script', CC_QA_URL . 'assets/js/ccquestions.js', array(), CC_QA_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cc_qa' ) ) {
        wp_enqueue_style( 'cc-qa-style' );
        wp_enqueue_script( 'cc-qa-script' );
        wp_localize_script( 'cc-qa-script', 'CC_QA', cc_qa_js_config( get_permalink() ) );
    }
}

/**
 * Shared JS config object — used by cc-qa.php (shortcode), single template, and archive template.
 *
 * @param string $login_redirect URL to redirect to after login. Defaults to current permalink.
 */
function cc_qa_js_config( $login_redirect = '' ) {
    if ( ! $login_redirect ) {
        $login_redirect = get_permalink();
    }
    return array(
        'ajax_url'  => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'cc_qa_nonce' ),
        'logged_in' => is_user_logged_in(),
        'login_url' => wp_login_url( $login_redirect ),
        'user_id'   => get_current_user_id(),
        'title_max' => (int) CC_QA_Admin::get( 'cc_qa_question_title_max' ),
        'strings'   => array(
            'vote_thanks'     => 'Vote recorded!',
            'already_voted'   => 'You already voted on this.',
            'login_to_vote'   => 'Please log in to vote.',
            'login_to_ask'    => 'Please log in to ask a question.',
            'login_to_answer' => 'Please log in to answer.',
            'confirm_delete'  => 'Are you sure you want to delete this?',
            'submitting'      => 'Submitting…',
            'error'           => 'Something went wrong. Please try again.',
        ),
    );
}
