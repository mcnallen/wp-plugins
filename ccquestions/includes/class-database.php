<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'CC_QA_Database' ) ) return;

class CC_QA_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $votes_table = $wpdb->prefix . 'cc_qa_votes';
        $sql_votes   = "CREATE TABLE IF NOT EXISTS {$votes_table} (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT(20) UNSIGNED NOT NULL,
            user_id    BIGINT(20) UNSIGNED NOT NULL,
            vote       TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_post (user_id, post_id),
            KEY post_id (post_id)
        ) {$charset};";

        $subs_table = $wpdb->prefix . 'cc_qa_subscriptions';
        $sql_subs   = "CREATE TABLE IF NOT EXISTS {$subs_table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            user_id     BIGINT(20) UNSIGNED NOT NULL,
            email       VARCHAR(200) NOT NULL,
            token       VARCHAR(64)  NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_question (user_id, question_id),
            KEY question_id (question_id),
            KEY token (token)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_votes );
        dbDelta( $sql_subs );

        add_option( 'cc_qa_db_version', CC_QA_VERSION );
    }

    public static function deactivate() {}

    // ── Vote helpers ──────────────────────────────────────────

    public static function get_vote_count( $post_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'cc_qa_votes' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(SUM(vote),0) FROM `{$table}` WHERE post_id = %d", $post_id )
        );
    }

    public static function user_voted( $post_id, $user_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'cc_qa_votes' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM `{$table}` WHERE post_id = %d AND user_id = %d", $post_id, $user_id )
        );
    }

    public static function add_vote( $post_id, $user_id, $vote = 1 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_qa_votes';

        if ( self::user_voted( $post_id, $user_id ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert( $table, array(
            'post_id'    => $post_id,
            'user_id'    => $user_id,
            'vote'       => $vote,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%d', '%d', '%s' ) );

        $count = self::get_vote_count( $post_id );
        update_post_meta( $post_id, '_cc_qa_votes', $count );

        // Track lifetime votes on the post author — never reset with leaderboard resets
        $post_author = (int) get_post_field( 'post_author', $post_id );
        if ( $post_author ) {
            $meta_key = ( 1 === (int) $vote ) ? '_cc_qa_lifetime_upvotes' : '_cc_qa_lifetime_downvotes';
            $current  = (int) get_user_meta( $post_author, $meta_key, true );
            update_user_meta( $post_author, $meta_key, $current + 1 );
        }

        return $count;
    }

    /**
     * Backfill lifetime vote counts for a user from the votes table.
     *
     * The _cc_qa_lifetime_upvotes / _cc_qa_lifetime_downvotes meta keys were
     * introduced in v2.5. Users who had votes cast before that version have no
     * stored meta. This method counts all historical votes from the DB and writes
     * the meta once, then never runs again for that user (meta is already set).
     *
     * Called lazily on profile page load so it's a one-time cost per user.
     */
    public static function backfill_lifetime_votes( $user_id ) {
        global $wpdb;

        $user_id   = (int) $user_id;
        $posts_tbl = esc_sql( $wpdb->prefix . 'posts' );
        $votes_tbl = esc_sql( $wpdb->prefix . 'cc_qa_votes' );

        // Only backfill if meta has never been set (get_user_meta returns '' not '0' when absent)
        $up_raw   = get_user_meta( $user_id, '_cc_qa_lifetime_upvotes',   false );
        $down_raw = get_user_meta( $user_id, '_cc_qa_lifetime_downvotes', false );

        $needs_up   = empty( $up_raw );
        $needs_down = empty( $down_raw );

        if ( ! $needs_up && ! $needs_down ) {
            return; // Already populated — nothing to do.
        }

        // Count upvotes received on all posts authored by this user
        if ( $needs_up ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $up = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$votes_tbl}` v
                 INNER JOIN `{$posts_tbl}` p ON p.ID = v.post_id
                 WHERE p.post_author = %d AND v.vote = 1
                   AND p.post_type IN ('cc_question','cc_answer')
                   AND p.post_status = 'publish'",
                $user_id
            ) );
            update_user_meta( $user_id, '_cc_qa_lifetime_upvotes', $up );
        }

        if ( $needs_down ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $down = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$votes_tbl}` v
                 INNER JOIN `{$posts_tbl}` p ON p.ID = v.post_id
                 WHERE p.post_author = %d AND v.vote = -1
                   AND p.post_type IN ('cc_question','cc_answer')
                   AND p.post_status = 'publish'",
                $user_id
            ) );
            update_user_meta( $user_id, '_cc_qa_lifetime_downvotes', $down );
        }
    }

    // ── Subscription helpers ──────────────────────────────────

    public static function subscribe( $question_id, $user_id, $email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_qa_subscriptions';
        $token = bin2hex( random_bytes( 16 ) ); // 32-char random hex, never exposes user IDs

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->replace( $table, array(
            'question_id' => $question_id,
            'user_id'     => $user_id,
            'email'       => $email,
            'token'       => $token,
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%d', '%s', '%s', '%s' ) );

        return $token;
    }

    /**
     * Get a single subscription row by its unsubscribe token.
     */
    public static function get_sub_by_token( $token ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'cc_qa_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE token = %s LIMIT 1", $token )
        );
    }

    /**
     * Delete a specific subscription by its unsubscribe token.
     */
    public static function unsubscribe_by_token( $token ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_qa_subscriptions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (bool) $wpdb->delete( $table, array( 'token' => $token ), array( '%s' ) );
    }

    /**
     * Delete ALL subscriptions for a user — used by digest global unsubscribe.
     */
    public static function unsubscribe_all_for_user( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cc_qa_subscriptions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
    }

    /**
     * Returns one row per unique email/user for the weekly digest mailer.
     * Carries one representative token per user for the global unsubscribe link.
     */
    public static function get_digest_subscribers() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'cc_qa_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            "SELECT user_id, email, MIN(token) AS token FROM `{$table}` GROUP BY user_id, email"
        );
    }

    public static function get_subscribers( $question_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'cc_qa_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT user_id, email, token FROM `{$table}` WHERE question_id = %d", $question_id )
        );
    }

    public static function get_all_subscribers() {
        global $wpdb;
        $subs  = esc_sql( $wpdb->prefix . 'cc_qa_subscriptions' );
        $users = esc_sql( $wpdb->prefix . 'users' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            "SELECT DISTINCT s.email, u.display_name FROM `{$subs}` s LEFT JOIN `{$users}` u ON u.ID = s.user_id"
        );
    }
}
