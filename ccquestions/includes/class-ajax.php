<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Ajax' ) ) return;

class CC_QA_Ajax {

    public static function init() {
        // Actions that require login — only register the authenticated hook.
        // nopriv hooks are intentionally omitted; the server-side require_login()
        // check would catch them anyway, but not registering them is cleaner.
        $authed_only = array(
            'cc_qa_submit_question',
            'cc_qa_submit_answer',
            'cc_qa_submit_reply',
            'cc_qa_delete_reply',
            'cc_qa_vote',
            'cc_qa_accept_answer',
            'cc_qa_delete_question',
            'cc_qa_delete_answer',
            'cc_qa_edit_question',
            'cc_qa_edit_answer',
            'cc_qa_edit_reply',
        );

        // Load-more actions are public (no login needed to browse).
        $public = array(
            'cc_qa_load_more_questions',
            'cc_qa_load_more_answers',
        );

        foreach ( $authed_only as $action ) {
            add_action( "wp_ajax_{$action}", array( __CLASS__, str_replace( 'cc_qa_', 'handle_', $action ) ) );
        }

        foreach ( $public as $action ) {
            $handler = str_replace( 'cc_qa_', 'handle_', $action );
            add_action( "wp_ajax_{$action}",        array( __CLASS__, $handler ) );
            add_action( "wp_ajax_nopriv_{$action}", array( __CLASS__, $handler ) );
        }
    }

    /* ── Helpers ── */
    private static function verify() {
        if ( ! check_ajax_referer( 'cc_qa_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
    }

    private static function require_login() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ), 401 );
        }
    }

    /**
     * Transient-based rate limiter.
     *
     * Tracks how many times a user has performed $action within the configured
     * rolling window. If the limit is exceeded, sends a JSON error and exits.
     *
     * @param string $action   One of 'question', 'answer', 'vote'.
     */
    private static function check_rate_limit( string $action ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return; // require_login() already guards this

        // Admins/editors bypass rate limits
        if ( current_user_can( 'edit_others_posts' ) ) return;

        $window  = max( 1, (int) CC_QA_Admin::get( 'cc_qa_rate_limit_window' ) );   // minutes
        $max_map = array(
            'question' => max( 1, (int) CC_QA_Admin::get( 'cc_qa_rate_limit_questions' ) ),
            'answer'   => max( 1, (int) CC_QA_Admin::get( 'cc_qa_rate_limit_answers' ) ),
            'vote'     => max( 1, (int) CC_QA_Admin::get( 'cc_qa_rate_limit_votes' ) ),
        );
        $max = $max_map[ $action ] ?? 3;

        $key   = "cc_qa_rl_{$action}_{$user_id}";
        $count = (int) get_transient( $key );

        if ( $count >= $max ) {
            /* translators: 1: action label, 2: max allowed, 3: window in minutes */
            $label   = array( 'question' => 'questions', 'answer' => 'answers', 'vote' => 'votes' )[ $action ] ?? $action;
            $message = sprintf(
                'Slow down — you can post %d %s every %d minutes. Please wait a moment.',
                $max,
                $label,
                $window
            );
            wp_send_json_error( array( 'message' => $message ), 429 );
        }

        if ( 0 === $count ) {
            // First action in this window — set transient with the window TTL
            set_transient( $key, 1, $window * MINUTE_IN_SECONDS );
        } else {
            // Increment without resetting the expiry (get remaining TTL)
            // WordPress doesn't expose TTL on get, so we store count only and let
            // the original transient expire naturally.
            set_transient( $key, $count + 1, $window * MINUTE_IN_SECONDS );
        }
    }

    private static function sanitize_content( $text ) {
        $text = wp_strip_all_tags( $text );
        $text = sanitize_textarea_field( $text );
        return $text;
    }

    /* ── Submit Question ── */
    public static function handle_submit_question() {
        self::verify();
        self::require_login();
        self::check_rate_limit( 'question' );

        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $content = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );
        $topics  = array_map( 'absint', (array) ( $_POST['topics'] ?? array() ) );

        $min_q = (int) CC_QA_Admin::get( 'cc_qa_min_question_length' );
        if ( strlen( $title ) < $min_q ) {
            wp_send_json_error( array( 'message' => "Question title must be at least {$min_q} characters." ) );
        }

        $user = wp_get_current_user();

        // Fix: respect the moderation setting instead of always publishing
        $status  = CC_QA_Admin::get( 'cc_qa_moderate_questions' ) ? 'pending' : 'publish';

        $post_id = wp_insert_post( array(
            'post_type'    => 'cc_question',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_author'  => get_current_user_id(),
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        if ( ! empty( $topics ) ) {
            wp_set_object_terms( $post_id, $topics, 'cc_question_topic' );
        }

        update_post_meta( $post_id, '_cc_qa_votes',        0 );
        update_post_meta( $post_id, '_cc_qa_answer_count', 0 );
        update_post_meta( $post_id, '_cc_qa_accepted',     0 );

        CC_QA_Database::subscribe( $post_id, get_current_user_id(), $user->user_email );

        // Bust leaderboard cache so new question appears in stats
        CC_QA_Leaderboard::bust_cache();

        // Only notify and render card if published (not pending moderation)
        if ( 'publish' === $status ) {
            CC_QA_Badges::check_and_award( get_current_user_id(), array( 'question' ) );
            CC_QA_Email::notify_new_question( $post_id );

            ob_start();
            // Fix: render_question_card() takes only one parameter
            CC_QA_Shortcode::render_question_card( get_post( $post_id ) );
            $html = ob_get_clean();

            wp_send_json_success( array(
                'message'      => 'Your question has been posted!',
                'html'         => $html,
                'post_id'      => $post_id,
                'question_url' => get_permalink( $post_id ),
                'pending'      => false,
            ) );
        } else {
            wp_send_json_success( array(
                'message' => 'Your question has been submitted and is pending review.',
                'html'    => '',
                'post_id' => $post_id,
                'pending' => true,
            ) );
        }
    }

    /* ── Submit Answer ── */
    public static function handle_submit_answer() {
        self::verify();
        self::require_login();
        self::check_rate_limit( 'answer' );

        $question_id = absint( $_POST['question_id'] ?? 0 );
        $content     = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );

        if ( ! $question_id || get_post_type( $question_id ) !== 'cc_question' ) {
            wp_send_json_error( array( 'message' => 'Invalid question.' ) );
        }

        $min_a = (int) CC_QA_Admin::get( 'cc_qa_min_answer_length' );
        if ( strlen( wp_strip_all_tags( $content ) ) < $min_a ) {
            wp_send_json_error( array( 'message' => "Answer must be at least {$min_a} characters." ) );
        }

        $user    = wp_get_current_user();
        $post_id = wp_insert_post( array(
            'post_type'    => 'cc_answer',
            'post_title'   => 'Answer to: ' . get_the_title( $question_id ),
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_parent'  => $question_id,
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        update_post_meta( $post_id, '_cc_qa_votes',    0 );
        update_post_meta( $post_id, '_cc_qa_accepted', 0 );

        $count = (int) get_post_meta( $question_id, '_cc_qa_answer_count', true );
        update_post_meta( $question_id, '_cc_qa_answer_count', $count + 1 );

        CC_QA_Database::subscribe( $question_id, get_current_user_id(), $user->user_email );
        CC_QA_Email::notify_new_answer( $question_id, $post_id );
        CC_QA_Badges::check_and_award( get_current_user_id(), array( 'answer' ) );
        CC_QA_Leaderboard::bust_cache(); // Bust leaderboard cache

        $current_user_id = get_current_user_id();
        $q_author_id     = (int) get_post_field( 'post_author', $question_id );
        // Fix: correctly compute is_question_author for the rendered card
        // (answerer can never accept their own answer so this will always be false
        //  for the submitter, but it is correct to pass the real value)
        $is_q_author = $current_user_id && $current_user_id === $q_author_id;

        ob_start();
        CC_QA_Shortcode::render_answer_card( get_post( $post_id ), $current_user_id, $is_q_author );
        $html = ob_get_clean();

        wp_send_json_success( array(
            'message'      => 'Your answer has been posted!',
            'html'         => $html,
            'answer_id'    => $post_id,
            'answer_count' => $count + 1,
        ) );
    }

    /* ── Vote ── */
    public static function handle_vote() {
        self::verify();
        self::require_login();
        self::check_rate_limit( 'vote' );

        $post_id   = absint( $_POST['post_id'] ?? 0 );
        $vote_type = (int) ( $_POST['vote_type'] ?? 1 );
        $user_id   = get_current_user_id();

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post.' ) );
        }

        $post = get_post( $post_id );
        if ( $post && (int) $post->post_author === $user_id ) {
            wp_send_json_error( array( 'message' => "You can't vote on your own post." ) );
        }

        if ( CC_QA_Database::user_voted( $post_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'You already voted on this.' ) );
        }

        $count = CC_QA_Database::add_vote( $post_id, $user_id, $vote_type );
        if ( $count !== false && $vote_type === 1 ) {
            $post_author = (int) get_post_field( 'post_author', $post_id );
            if ( $post_author ) {
                CC_QA_Badges::check_and_award( $post_author, array( 'vote' ) );
            }
        }
        CC_QA_Leaderboard::bust_cache(); // Bust leaderboard cache

        wp_send_json_success( array(
            'count'   => $count,
            'post_id' => $post_id,
        ) );
    }

    /* ── Accept Answer ── */
    public static function handle_accept_answer() {
        self::verify();
        self::require_login();

        $answer_id   = absint( $_POST['answer_id'] ?? 0 );
        $question_id = absint( get_post_field( 'post_parent', $answer_id ) );
        $user_id     = get_current_user_id();

        if ( ! $answer_id || ! $question_id ) {
            wp_send_json_error( array( 'message' => 'Invalid answer.' ) );
        }

        $q_author = (int) get_post_field( 'post_author', $question_id );
        if ( $q_author !== $user_id && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Only the question author can accept an answer.' ) );
        }

        $a_author = (int) get_post_field( 'post_author', $answer_id );
        if ( $a_author === $user_id && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( array( 'message' => "You can't accept your own answer." ) );
        }

        $prev = get_post_meta( $question_id, '_cc_qa_accepted_answer', true );
        if ( $prev ) {
            update_post_meta( (int) $prev, '_cc_qa_accepted', 0 );
        }

        update_post_meta( $answer_id,   '_cc_qa_accepted',        1 );
        update_post_meta( $question_id, '_cc_qa_accepted_answer', $answer_id );
        update_post_meta( $question_id, '_cc_qa_accepted',        1 );
        CC_QA_Badges::check_and_award( $a_author, array( 'accepted' ) );
        CC_QA_Leaderboard::bust_cache(); // Bust leaderboard cache

        wp_send_json_success( array(
            'answer_id'   => $answer_id,
            'question_id' => $question_id,
        ) );
    }

    /* ── Delete Question ── */
    public static function handle_delete_question() {
        self::verify();
        self::require_login();

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'cc_question' ) {
            wp_send_json_error( array( 'message' => 'Invalid question.' ) );
        }

        $user_id = get_current_user_id();
        if ( (int) $post->post_author !== $user_id && ! current_user_can( 'delete_others_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $answers = get_posts( array(
            'post_type'   => 'cc_answer',
            'post_parent' => $post_id,
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );
        foreach ( $answers as $aid ) {
            wp_delete_post( $aid, true );
        }

        wp_delete_post( $post_id, true );
        CC_QA_Leaderboard::bust_cache();

        wp_send_json_success( array( 'post_id' => $post_id ) );
    }

    /* ── Delete Answer ── */
    public static function handle_delete_answer() {
        self::verify();
        self::require_login();

        $answer_id = absint( $_POST['answer_id'] ?? 0 );
        $post      = get_post( $answer_id );

        if ( ! $post || $post->post_type !== 'cc_answer' ) {
            wp_send_json_error( array( 'message' => 'Invalid answer.' ) );
        }

        $user_id = get_current_user_id();
        if ( (int) $post->post_author !== $user_id && ! current_user_can( 'delete_others_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $question_id = (int) $post->post_parent;
        wp_delete_post( $answer_id, true );

        $count = max( 0, (int) get_post_meta( $question_id, '_cc_qa_answer_count', true ) - 1 );
        update_post_meta( $question_id, '_cc_qa_answer_count', $count );
        CC_QA_Leaderboard::bust_cache();

        wp_send_json_success( array(
            'answer_id'    => $answer_id,
            'answer_count' => $count,
        ) );
    }

    /* ── Load More Questions ── */
    public static function handle_load_more_questions() {
        self::verify();

        $page   = absint( $_POST['page'] ?? 1 );
        $topic  = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
        $sort   = sanitize_key( $_POST['sort'] ?? 'newest' );
        $search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

        $args  = CC_QA_Shortcode::build_question_query( $page, $topic, $sort, $search );
        $query = new WP_Query( $args );

        ob_start();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                CC_QA_Shortcode::render_question_card( get_post() );
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html'      => $html,
            'has_more'  => $page < $query->max_num_pages,
            'max_pages' => $query->max_num_pages,
        ) );
    }

    /* ── Load More Answers ── */
    public static function handle_load_more_answers() {
        self::verify();

        $question_id = absint( $_POST['question_id'] ?? 0 );
        $page        = absint( $_POST['page'] ?? 1 );
        $sort        = sanitize_key( $_POST['sort'] ?? 'votes' );
        $user_id     = get_current_user_id();

        $args = array(
            'post_type'      => 'cc_answer',
            'post_parent'    => $question_id,
            'post_status'    => 'publish',
            'posts_per_page' => (int) CC_QA_Admin::get( 'cc_qa_answers_per_page' ),
            'paged'          => $page,
        );

        if ( $sort === 'votes' ) {
            $args['meta_key'] = '_cc_qa_votes';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'ASC';
        }

        $query = new WP_Query( $args );

        // Fix: compute is_question_author properly so Accept button shows correctly
        $q_author_id = (int) get_post_field( 'post_author', $question_id );
        $is_q_author = $user_id && $user_id === $q_author_id;

        ob_start();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                CC_QA_Shortcode::render_answer_card( get_post(), $user_id, $is_q_author );
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html'     => $html,
            'has_more' => $page < $query->max_num_pages,
        ) );
    }

    /* ── Submit Reply to Answer ── */
    public static function handle_submit_reply() {
        self::verify();
        self::require_login();

        $answer_id = absint( $_POST['answer_id'] ?? 0 );
        $content   = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );

        if ( ! $answer_id || get_post_type( $answer_id ) !== 'cc_answer' ) {
            wp_send_json_error( array( 'message' => 'Invalid answer.' ) );
        }

        if ( strlen( wp_strip_all_tags( $content ) ) < 2 ) {
            wp_send_json_error( array( 'message' => 'Reply is too short.' ) );
        }

        $user        = wp_get_current_user();
        $question_id = (int) get_post_field( 'post_parent', $answer_id );

        $replies              = get_post_meta( $answer_id, '_cc_qa_replies', true ) ?: array();
        $reply_id             = uniqid( 'r', true );
        $replies[ $reply_id ] = array(
            'id'         => $reply_id,
            'user_id'    => get_current_user_id(),
            'user_name'  => ! empty( trim( $user->display_name ) ) ? $user->display_name : $user->user_login,
            'content'    => $content,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
            'time_diff'  => 'just now',
        );
        update_post_meta( $answer_id, '_cc_qa_replies', $replies );

        CC_QA_Email::notify_new_reply( $question_id, $answer_id, $content, $user );

        ob_start();
        CC_QA_Shortcode::render_reply( $reply_id, $replies[ $reply_id ], $answer_id, get_current_user_id() );
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html'     => $html,
            'reply_id' => $reply_id,
        ) );
    }

    /* ── Edit Question ── */
    public static function handle_edit_question() {
        self::verify();
        self::require_login();

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'cc_question' ) {
            wp_send_json_error( array( 'message' => 'Invalid question.' ) );
        }

        $perms = CC_QA_Shortcode::get_edit_permissions( $post );
        if ( ! $perms['can_edit'] ) {
            wp_send_json_error( array( 'message' => 'You can no longer edit this question (1-hour window has passed).' ), 403 );
        }

        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $content = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );

        $min_q = (int) CC_QA_Admin::get( 'cc_qa_min_question_length' );
        if ( strlen( $title ) < $min_q ) {
            wp_send_json_error( array( 'message' => "Question title must be at least {$min_q} characters." ) );
        }

        wp_update_post( array(
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => $content,
        ) );

        wp_send_json_success( array(
            'post_id' => $post_id,
            'title'   => esc_html( $title ),
            'content' => esc_html( $content ),
            'excerpt' => esc_html( wp_trim_words( wp_strip_all_tags( $content ), 20, '…' ) ),
        ) );
    }

    /* ── Edit Answer ── */
    public static function handle_edit_answer() {
        self::verify();
        self::require_login();

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'cc_answer' ) {
            wp_send_json_error( array( 'message' => 'Invalid answer.' ) );
        }

        $perms = CC_QA_Shortcode::get_edit_permissions( $post );
        if ( ! $perms['can_edit'] ) {
            wp_send_json_error( array( 'message' => 'You can no longer edit this answer (1-hour window has passed).' ), 403 );
        }

        $content = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );

        $min_a = (int) CC_QA_Admin::get( 'cc_qa_min_answer_length' );
        if ( strlen( wp_strip_all_tags( $content ) ) < $min_a ) {
            wp_send_json_error( array( 'message' => "Answer must be at least {$min_a} characters." ) );
        }

        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => $content,
        ) );

        wp_send_json_success( array(
            'post_id' => $post_id,
            'content' => esc_html( $content ),
        ) );
    }

    /* ── Edit Reply ── */
    public static function handle_edit_reply() {
        self::verify();
        self::require_login();

        $answer_id = absint( $_POST['answer_id'] ?? 0 );
        $reply_id  = sanitize_text_field( wp_unslash( $_POST['reply_id'] ?? '' ) );
        $content   = self::sanitize_content( wp_unslash( $_POST['content'] ?? '' ) );
        $user_id   = get_current_user_id();

        if ( strlen( wp_strip_all_tags( $content ) ) < 2 ) {
            wp_send_json_error( array( 'message' => 'Reply is too short.' ) );
        }

        $replies = get_post_meta( $answer_id, '_cc_qa_replies', true ) ?: array();

        if ( ! isset( $replies[ $reply_id ] ) ) {
            wp_send_json_error( array( 'message' => 'Reply not found.' ) );
        }

        $reply = $replies[ $reply_id ];

        if ( (int) $reply['user_id'] !== $user_id && ! current_user_can( 'delete_others_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        // Enforce 1-hour window (admins bypass)
        if ( ! current_user_can( 'delete_others_posts' ) && ! empty( $reply['created_at'] ) ) {
            if ( ( time() - strtotime( $reply['created_at'] ) ) >= HOUR_IN_SECONDS ) {
                wp_send_json_error( array( 'message' => 'The 1-hour edit window for this reply has passed.' ), 403 );
            }
        }

        $replies[ $reply_id ]['content'] = $content;
        update_post_meta( $answer_id, '_cc_qa_replies', $replies );

        wp_send_json_success( array(
            'reply_id' => $reply_id,
            'content'  => esc_html( $content ),
        ) );
    }
}
