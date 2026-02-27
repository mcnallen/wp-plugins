<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Post_Types' ) ) return;

class CC_QA_Post_Types {

    public static function init() {
        add_action( 'init',                  array( __CLASS__, 'register' ) );
        add_action( 'init',                  array( __CLASS__, 'register_taxonomies' ) );
        add_action( 'init',                  array( __CLASS__, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',            array( __CLASS__, 'add_query_vars' ) );
        add_filter( 'post_updated_messages', array( __CLASS__, 'messages' ) );
        add_filter( 'template_include',      array( __CLASS__, 'load_templates' ) );

        // Homepage mode: serve Q&A feed at / and redirect /questions/ archive to /
        if ( CC_QA_Admin::get( 'cc_qa_homepage_mode' ) ) {
            add_action( 'template_redirect', array( __CLASS__, 'homepage_redirect_archive' ), 1 );
        }
    }

    /**
     * When homepage mode is active and someone hits the /questions/ archive,
     * 301-redirect them to the homepage so there's only one canonical URL.
     * Individual question pages (/questions/slug/) and topic pages are unaffected.
     */
    public static function homepage_redirect_archive() {
        if ( is_post_type_archive( 'cc_question' ) ) {
            wp_redirect( home_url( '/' ), 301 );
            exit;
        }
    }

    /**
     * Register /questions/author/{username}/ as a routed URL.
     * Rewrites to index.php?cc_qa_author_name={username}.
     * Flush rewrite rules on plugin activation — not here.
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^questions/author/([^/]+)/?$',
            'index.php?cc_qa_author_name=$matches[1]',
            'top'
        );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'cc_qa_author_name';
        return $vars;
    }

    /**
     * Load single and archive templates from the plugin's /templates/ folder.
     * Theme templates in the child theme take priority if they exist.
     */
    public static function load_templates( $template ) {
        // Homepage mode: serve the Q&A archive template at the front page
        if ( CC_QA_Admin::get( 'cc_qa_homepage_mode' ) && is_front_page() && is_home() ) {
            $theme_tpl = locate_template( array( 'archive-cc_question.php' ) );
            if ( $theme_tpl ) return $theme_tpl;
            return CC_QA_PATH . 'templates/archive-cc_question.php';
        }
        if ( is_singular( 'cc_question' ) ) {
            $theme_tpl = locate_template( array( 'single-cc_question.php' ) );
            if ( $theme_tpl ) return $theme_tpl;
            return CC_QA_PATH . 'templates/single-cc_question.php';
        }
        if ( is_post_type_archive( 'cc_question' ) ) {
            $theme_tpl = locate_template( array( 'archive-cc_question.php' ) );
            if ( $theme_tpl ) return $theme_tpl;
            return CC_QA_PATH . 'templates/archive-cc_question.php';
        }
        if ( is_tax( 'cc_question_topic' ) ) {
            $theme_tpl = locate_template( array( 'taxonomy-cc_question_topic.php' ) );
            if ( $theme_tpl ) return $theme_tpl;
            return CC_QA_PATH . 'templates/archive-cc_question.php';
        }
        // Q&A member profile at /questions/author/{username}/
        $author_name = get_query_var( 'cc_qa_author_name', '' );
        if ( $author_name !== '' ) {
            $theme_tpl = locate_template( array( 'author-cc_question.php' ) );
            if ( $theme_tpl ) return $theme_tpl;
            return CC_QA_PATH . 'templates/author-cc_question.php';
        }
        return $template;
    }

    public static function register() {
        register_post_type( 'cc_question', array(
            'labels' => array(
                'name'               => 'ccQuestions',
                'singular_name'      => 'Question',
                'menu_name'          => 'ccQuestions',
                'add_new'            => 'Ask Question',
                'add_new_item'       => 'Ask New Question',
                'edit_item'          => 'Edit Question',
                'view_item'          => 'View Question',
                'search_items'       => 'Search Questions',
                'not_found'          => 'No questions found',
                'not_found_in_trash' => 'No questions in trash',
            ),
            'public'          => true,
            'has_archive'     => true,
            'rewrite'         => array( 'slug' => 'questions', 'with_front' => false ),
            'supports'        => array( 'title', 'editor', 'author', 'custom-fields' ),
            'show_in_rest'    => true,
            'menu_icon'       => 'dashicons-format-chat',
            'menu_position'   => 25,
            'capability_type' => 'post',
            'hierarchical'    => false,
        ) );

        register_post_type( 'cc_answer', array(
            'labels' => array(
                'name'               => 'Answers',
                'singular_name'      => 'Answer',
                'add_new'            => 'Add Answer',
                'add_new_item'       => 'Add New Answer',
                'edit_item'          => 'Edit Answer',
                'view_item'          => 'View Answer',
                'search_items'       => 'Search Answers',
                'not_found'          => 'No answers found',
                'not_found_in_trash' => 'No answers in trash',
            ),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=cc_question',
            'supports'           => array( 'editor', 'author', 'custom-fields' ),
            'show_in_rest'       => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
        ) );
    }

    public static function register_taxonomies() {
        register_taxonomy( 'cc_question_topic', 'cc_question', array(
            'labels' => array(
                'name'          => 'Topics',
                'singular_name' => 'Topic',
                'search_items'  => 'Search Topics',
                'all_items'     => 'All Topics',
                'edit_item'     => 'Edit Topic',
                'add_new_item'  => 'Add New Topic',
            ),
            'hierarchical'      => true,
            'public'            => true,
            'rewrite'           => array( 'slug' => 'question-topic', 'with_front' => false ),
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ) );
    }

    public static function messages( $messages ) {
        $messages['cc_question'] = array_fill( 0, 11, 'Question saved.' );
        $messages['cc_answer']   = array_fill( 0, 11, 'Answer saved.' );
        return $messages;
    }
}
