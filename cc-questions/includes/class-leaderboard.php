<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'CC_QA_Leaderboard' ) ) return;

class CC_QA_Leaderboard {

    const CACHE_KEY = 'cc_qa_leaderboard_stats';
    const CACHE_TTL = 300; // 5 minutes

    public static function init() {
        add_shortcode( 'cc_qa_leaderboard', array( __CLASS__, 'render' ) );
    }

    public static function bust_cache() {
        wp_cache_delete( self::CACHE_KEY, 'cc_qa' );
    }

    /**
     * Reset leaderboard stats. Stores a timestamp; get_stats() will only
     * count activity after this date. Lifetime votes are stored in user meta
     * and are never affected by this reset.
     */
    public static function reset_stats() {
        update_option( 'cc_qa_leaderboard_reset_date', gmdate( 'Y-m-d H:i:s' ) );
        self::bust_cache();
    }

    public static function get_stats() {
        global $wpdb;

        $cached = wp_cache_get( self::CACHE_KEY, 'cc_qa' );
        if ( false !== $cached ) {
            return $cached;
        }

        $posts     = esc_sql( $wpdb->prefix . 'posts' );
        $postmeta  = esc_sql( $wpdb->prefix . 'postmeta' );
        $votes_tbl = esc_sql( $wpdb->prefix . 'cc_qa_votes' );
        $users_tbl = esc_sql( $wpdb->prefix . 'users' );

        // If a reset date is set, only count activity after that date
        $reset_date = get_option( 'cc_qa_leaderboard_reset_date', '' );
        $date_clause_posts = $reset_date ? $wpdb->prepare( "AND p.post_date > %s", $reset_date ) : '';
        $date_clause_votes = $reset_date ? $wpdb->prepare( "AND v.created_at > %s", $reset_date ) : '';
        // For simple post queries (no alias)
        $date_clause_plain = $reset_date ? $wpdb->prepare( "AND post_date > %s", $reset_date ) : '';

        $stats = array();

        // 1. Questions asked
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT post_author AS user_id, COUNT(*) AS total FROM `{$posts}`
             WHERE post_type = 'cc_question' AND post_status = 'publish' {$date_clause_plain}
             GROUP BY post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['questions_asked'] = (int) $r->total;
        }

        // 2. Answers given
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT post_author AS user_id, COUNT(*) AS total FROM `{$posts}`
             WHERE post_type = 'cc_answer' AND post_status = 'publish' {$date_clause_plain}
             GROUP BY post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['answers_given'] = (int) $r->total;
        }

        // 3. Accepted answers
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT p.post_author AS user_id, COUNT(*) AS total
             FROM `{$posts}` p
             INNER JOIN `{$postmeta}` pm ON pm.post_id = p.ID
             WHERE p.post_type = 'cc_answer' AND p.post_status = 'publish'
               AND pm.meta_key = '_cc_qa_accepted' AND pm.meta_value = '1'
               {$date_clause_posts}
             GROUP BY p.post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['accepted_answers'] = (int) $r->total;
        }

        // 4. Upvotes on questions (since reset)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT p.post_author AS user_id, COUNT(*) AS total
             FROM `{$votes_tbl}` v INNER JOIN `{$posts}` p ON p.ID = v.post_id
             WHERE v.vote = 1 AND p.post_type = 'cc_question' AND p.post_status = 'publish'
               {$date_clause_votes}
             GROUP BY p.post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['q_upvotes'] = (int) $r->total;
        }

        // 5. Downvotes on questions (since reset)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT p.post_author AS user_id, COUNT(*) AS total
             FROM `{$votes_tbl}` v INNER JOIN `{$posts}` p ON p.ID = v.post_id
             WHERE v.vote = -1 AND p.post_type = 'cc_question' AND p.post_status = 'publish'
               {$date_clause_votes}
             GROUP BY p.post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['q_downvotes'] = (int) $r->total;
        }

        // 6. Upvotes on answers (since reset)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT p.post_author AS user_id, COUNT(*) AS total
             FROM `{$votes_tbl}` v INNER JOIN `{$posts}` p ON p.ID = v.post_id
             WHERE v.vote = 1 AND p.post_type = 'cc_answer' AND p.post_status = 'publish'
               {$date_clause_votes}
             GROUP BY p.post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['a_upvotes'] = (int) $r->total;
        }

        // 7. Downvotes on answers (since reset)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT p.post_author AS user_id, COUNT(*) AS total
             FROM `{$votes_tbl}` v INNER JOIN `{$posts}` p ON p.ID = v.post_id
             WHERE v.vote = -1 AND p.post_type = 'cc_answer' AND p.post_status = 'publish'
               {$date_clause_votes}
             GROUP BY p.post_author ORDER BY total DESC"
        );
        foreach ( $rows as $r ) {
            $stats[ $r->user_id ]['a_downvotes'] = (int) $r->total;
        }

        // Fill defaults and compute score
        foreach ( $stats as $uid => $s ) {
            $s = array_merge( array(
                'questions_asked'  => 0,
                'answers_given'    => 0,
                'accepted_answers' => 0,
                'q_upvotes'        => 0,
                'q_downvotes'      => 0,
                'a_upvotes'        => 0,
                'a_downvotes'      => 0,
            ), $s );
            $s['score'] = ( $s['q_upvotes'] + $s['a_upvotes'] ) * 2
                        + $s['accepted_answers'] * 10
                        + $s['questions_asked']
                        + $s['answers_given']
                        - ( $s['q_downvotes'] + $s['a_downvotes'] );
            $stats[ $uid ] = $s;
        }

        // Attach display names + profile URLs + lifetime votes (never reset)
        if ( ! empty( $stats ) ) {
            $ids = array_map( 'intval', array_keys( $stats ) );

            if ( ! empty( $ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $users = $wpdb->get_results(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        "SELECT ID, display_name, user_login FROM `{$users_tbl}` WHERE ID IN ({$placeholders})",
                        ...$ids
                    )
                );
                foreach ( $users as $u ) {
                    $name = ( ! empty( trim( $u->display_name ) ) ) ? $u->display_name : $u->user_login;
                    $stats[ $u->ID ]['name']              = $name;
                    $stats[ $u->ID ]['initial']           = strtoupper( substr( $name, 0, 1 ) );
                    $stats[ $u->ID ]['profile_url']       = CC_QA_Badges::profile_url( $u->ID );
                    $stats[ $u->ID ]['lifetime_upvotes']  = (int) get_user_meta( $u->ID, '_cc_qa_lifetime_upvotes',   true );
                    $stats[ $u->ID ]['lifetime_downvotes']= (int) get_user_meta( $u->ID, '_cc_qa_lifetime_downvotes', true );
                }
            }
        }

        // Remove deleted/missing users
        $stats = array_filter( $stats, function( $s ) {
            return ! empty( $s['name'] );
        } );

        wp_cache_set( self::CACHE_KEY, $stats, 'cc_qa', self::CACHE_TTL );

        return $stats;
    }

    public static function get_categories( $limit = 10 ) {
        $stats = self::get_stats();
        if ( empty( $stats ) ) return array();

        $sorter = function( $key ) use ( $stats, $limit ) {
            $arr = $stats;
            uasort( $arr, function( $a, $b ) use ( $key ) {
                $av = isset( $a[ $key ] ) ? (int) $a[ $key ] : 0;
                $bv = isset( $b[ $key ] ) ? (int) $b[ $key ] : 0;
                return $bv - $av;
            } );
            $arr = array_filter( $arr, function( $s ) use ( $key ) {
                return isset( $s[ $key ] ) && (int) $s[ $key ] > 0;
            } );
            return array_slice( $arr, 0, $limit, true );
        };

        $upvotes_rows = $stats;
        uasort( $upvotes_rows, function( $a, $b ) {
            return ( (int) $b['q_upvotes'] + (int) $b['a_upvotes'] ) - ( (int) $a['q_upvotes'] + (int) $a['a_upvotes'] );
        } );
        $upvotes_rows = array_filter( $upvotes_rows, function( $s ) {
            return ( (int) $s['q_upvotes'] + (int) $s['a_upvotes'] ) > 0;
        } );
        foreach ( $upvotes_rows as $uid => $s ) {
            $upvotes_rows[ $uid ]['_display'] = (int) $s['q_upvotes'] + (int) $s['a_upvotes'];
        }
        $upvotes_rows = array_slice( $upvotes_rows, 0, $limit, true );

        $downvotes_rows = $stats;
        uasort( $downvotes_rows, function( $a, $b ) {
            return ( (int) $b['q_downvotes'] + (int) $b['a_downvotes'] ) - ( (int) $a['q_downvotes'] + (int) $a['a_downvotes'] );
        } );
        $downvotes_rows = array_filter( $downvotes_rows, function( $s ) {
            return ( (int) $s['q_downvotes'] + (int) $s['a_downvotes'] ) > 0;
        } );
        foreach ( $downvotes_rows as $uid => $s ) {
            $downvotes_rows[ $uid ]['_display'] = (int) $s['q_downvotes'] + (int) $s['a_downvotes'];
        }
        $downvotes_rows = array_slice( $downvotes_rows, 0, $limit, true );

        return array(
            array( 'id' => 'overall',   'label' => 'Overall Score',        'icon' => '🏆', 'key' => 'score',            'unit' => 'pts',       'color' => 'orange', 'rows' => $sorter( 'score' ) ),
            array( 'id' => 'questions', 'label' => 'Most Questions Asked',  'icon' => '❓', 'key' => 'questions_asked',  'unit' => 'questions', 'color' => 'teal',   'rows' => $sorter( 'questions_asked' ) ),
            array( 'id' => 'answers',   'label' => 'Most Answers Given',    'icon' => '💬', 'key' => 'answers_given',    'unit' => 'answers',   'color' => 'teal',   'rows' => $sorter( 'answers_given' ) ),
            array( 'id' => 'accepted',  'label' => 'Most Accepted Answers', 'icon' => '✓',  'key' => 'accepted_answers', 'unit' => 'accepted',  'color' => 'green',  'rows' => $sorter( 'accepted_answers' ) ),
            array( 'id' => 'upvotes',   'label' => 'Most Upvotes',          'icon' => '▲',  'key' => '_display',         'unit' => 'upvotes',   'color' => 'orange', 'rows' => $upvotes_rows,   'display_key' => '_display' ),
            array( 'id' => 'downvotes', 'label' => 'Most Downvotes',        'icon' => '▼',  'key' => '_display',         'unit' => 'downvotes', 'color' => 'red',    'rows' => $downvotes_rows, 'display_key' => '_display' ),
        );
    }

    /** Read the admin-configured limit, falling back to 10. */
    public static function get_limit() {
        return (int) CC_QA_Admin::get( 'cc_qa_leaderboard_limit' );
    }

    public static function render( $atts ) {
        $default_limit = self::get_limit();
        $atts  = shortcode_atts( array( 'limit' => $default_limit ), $atts, 'cc_qa_leaderboard' );
        $limit = max( 3, min( 50, (int) $atts['limit'] ) );

        wp_enqueue_style(  'cc-qa-style',  CC_QA_URL . 'assets/css/cc-questions.css',  array(), CC_QA_VERSION );
        wp_enqueue_script( 'cc-qa-script', CC_QA_URL . 'assets/js/cc-questions.js', array(), CC_QA_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

        return self::render_inner( $limit, 'standalone' );
    }

    /**
     * Render the leaderboard for embedding inside the Q&A layout.
     * $context: 'compact' for sidebars, 'stacked' for above/below layouts.
     */
    public static function render_inline( $limit = 0, $context = 'compact' ) {
        if ( ! $limit ) $limit = self::get_limit();
        return self::render_inner( (int) $limit, $context );
    }

    /**
     * Shared inner render.
     * $context: 'standalone' | 'compact' | 'stacked'
     */
    private static function render_inner( $limit = 10, $context = 'standalone' ) {
        $limit = max( 3, min( 50, $limit ) );
        $cats  = self::get_categories( $limit );

        $has_data = false;
        foreach ( $cats as $cat ) {
            if ( ! empty( $cat['rows'] ) ) { $has_data = true; break; }
        }

        if ( ! $has_data ) {
            return '<p class="qa-empty-leaderboard">No activity yet — start asking and answering questions!</p>';
        }

        // CSS modifier class based on context
        $modifier = '';
        if ( 'compact' === $context )  $modifier = ' cc-qa-leaderboard--compact';
        if ( 'stacked' === $context )  $modifier = ' cc-qa-leaderboard--stacked';

        // In compact mode, default to showing 5 entries max to save space
        if ( 'compact' === $context ) {
            foreach ( $cats as &$cat ) {
                $cat['rows'] = array_slice( $cat['rows'], 0, 5, true );
            }
            unset( $cat );
        }

        ob_start();
        ?>
        <div class="cc-qa-leaderboard<?php echo esc_attr( $modifier ); ?>" id="cc-qa-leaderboard">

          <div class="qa-lb-header">
            <h1 class="qa-page-title">Top Contributors</h1>
            <p class="qa-page-sub">The most active and helpful members of our community.</p>
          </div>

          <div class="qa-lb-tabs" role="tablist">
            <?php foreach ( $cats as $i => $cat ) : ?>
              <button class="qa-lb-tab <?php echo 0 === $i ? 'active' : ''; ?>"
                      role="tab"
                      data-tab="<?php echo esc_attr( $cat['id'] ); ?>"
                      aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>">
                <span class="qa-lb-tab-icon"><?php echo esc_html( $cat['icon'] ); ?></span>
                <span class="qa-lb-tab-label"><?php echo esc_html( $cat['label'] ); ?></span>
              </button>
            <?php endforeach; ?>
          </div>

          <?php foreach ( $cats as $i => $cat ) : ?>
            <div class="qa-lb-panel <?php echo 0 === $i ? 'active' : ''; ?>"
                 id="lb-panel-<?php echo esc_attr( $cat['id'] ); ?>"
                 role="tabpanel">

              <?php if ( empty( $cat['rows'] ) ) : ?>
                <p class="qa-lb-empty">No data yet for this category.</p>
              <?php else : ?>
                <div class="qa-lb-table">
                  <?php
                  $rank = 1;
                  foreach ( $cat['rows'] as $uid => $s ) :
                      $display_val   = isset( $cat['display_key'] ) ? (int) $s[ $cat['display_key'] ] : (int) $s[ $cat['key'] ];
                      $medal_class   = '';
                      $medal_icon    = '';
                      if ( 1 === $rank ) { $medal_class = 'qa-lb-medal-gold';   $medal_icon = '🥇'; }
                      if ( 2 === $rank ) { $medal_class = 'qa-lb-medal-silver'; $medal_icon = '🥈'; }
                      if ( 3 === $rank ) { $medal_class = 'qa-lb-medal-bronze'; $medal_icon = '🥉'; }
                      $profile_url      = $s['profile_url'] ?? '#';
                      $lifetime_up      = (int) ( $s['lifetime_upvotes']   ?? 0 );
                      $lifetime_down    = (int) ( $s['lifetime_downvotes'] ?? 0 );
                  ?>
                    <div class="qa-lb-row <?php echo esc_attr( $medal_class ); ?> qa-lb-color-<?php echo esc_attr( $cat['color'] ); ?>">

                      <span class="qa-lb-rank">
                        <?php if ( $medal_icon ) : ?>
                          <span class="qa-lb-medal"><?php echo esc_html( $medal_icon ); ?></span>
                        <?php else : ?>
                          <?php echo esc_html( $rank ); ?>
                        <?php endif; ?>
                      </span>

                      <a href="<?php echo esc_url( $profile_url ); ?>" class="qa-lb-author-link" aria-label="<?php echo esc_attr( $s['name'] ); ?>'s profile">
                        <div class="qa-avatar qa-avatar-sm qa-lb-avatar">
                          <?php echo esc_html( $s['initial'] ); ?>
                        </div>
                      </a>

                      <div class="qa-lb-name-wrap">
                        <a href="<?php echo esc_url( $profile_url ); ?>" class="qa-lb-name">
                          <?php echo esc_html( $s['name'] ); ?>
                        </a>
                        <div class="qa-lb-lifetime">
                          <?php if ( $lifetime_up > 0 ) : ?>
                            <span class="qa-lb-lifetime-up" title="Lifetime upvotes received">▲<?php echo esc_html( number_format( $lifetime_up ) ); ?></span>
                          <?php endif; ?>
                          <?php if ( $lifetime_down > 0 ) : ?>
                            <span class="qa-lb-lifetime-down" title="Lifetime downvotes received">▼<?php echo esc_html( number_format( $lifetime_down ) ); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <?php if ( 'overall' === $cat['id'] ) : ?>
                        <div class="qa-lb-breakdown">
                          <span title="Questions asked">❓<?php echo esc_html( (string) $s['questions_asked'] ); ?></span>
                          <span title="Answers given">💬<?php echo esc_html( (string) $s['answers_given'] ); ?></span>
                          <span title="Accepted answers">✓<?php echo esc_html( (string) $s['accepted_answers'] ); ?></span>
                          <span title="Upvotes since reset">▲<?php echo esc_html( (string) ( $s['q_upvotes'] + $s['a_upvotes'] ) ); ?></span>
                        </div>
                      <?php elseif ( 'upvotes' === $cat['id'] ) : ?>
                        <div class="qa-lb-breakdown">
                          <span title="On questions">Q: <?php echo esc_html( (string) $s['q_upvotes'] ); ?></span>
                          <span title="On answers">A: <?php echo esc_html( (string) $s['a_upvotes'] ); ?></span>
                        </div>
                      <?php elseif ( 'downvotes' === $cat['id'] ) : ?>
                        <div class="qa-lb-breakdown">
                          <span title="On questions">Q: <?php echo esc_html( (string) $s['q_downvotes'] ); ?></span>
                          <span title="On answers">A: <?php echo esc_html( (string) $s['a_downvotes'] ); ?></span>
                        </div>
                      <?php endif; ?>

                      <span class="qa-lb-score qa-lb-score-<?php echo esc_attr( $cat['color'] ); ?>">
                        <?php echo esc_html( number_format( $display_val ) ); ?>
                        <span class="qa-lb-unit"><?php echo esc_html( $cat['unit'] ); ?></span>
                      </span>

                    </div>
                  <?php $rank++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <div class="qa-lb-scoring-note">
            Overall score: <strong>+10</strong> per accepted answer &middot; <strong>+2</strong> per upvote &middot; <strong>+1</strong> per question or answer &middot; <strong>&minus;1</strong> per downvote
          </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
