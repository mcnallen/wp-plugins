<?php
/**
 * CC QA Badges
 *
 * Defines all badges, awards them at the right moments, and renders
 * the SVG activity chart shown on member profile pages.
 *
 * Badges are stored in user meta as a JSON-encoded array of badge slugs.
 * They are awarded exactly once — the check_and_award() method is a no-op
 * if the user already holds the badge.
 *
 * Meta key: _cc_qa_badges  →  [ 'first_question', 'helpful', … ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Badges' ) ) return;

class CC_QA_Badges {

    /* ── Badge Definitions ──────────────────────────────────────────
     *
     * Each badge:
     *   emoji   — displayed in the pill
     *   label   — short display name
     *   desc    — tooltip / screen-reader description
     *   tier    — 'bronze' | 'silver' | 'gold'  (controls pill colour)
     */
    public static function definitions() {
        return array(

            // ── Participation ──
            'first_question' => array(
                'emoji' => '🙋',
                'label' => 'First Question',
                'desc'  => 'Asked their first question',
                'tier'  => 'bronze',
            ),
            'first_answer' => array(
                'emoji' => '✏️',
                'label' => 'First Answer',
                'desc'  => 'Posted their first answer',
                'tier'  => 'bronze',
            ),

            // ── Quality ──
            'helpful' => array(
                'emoji' => '✅',
                'label' => 'Helpful',
                'desc'  => 'Had an answer accepted for the first time',
                'tier'  => 'bronze',
            ),
            'top_contributor' => array(
                'emoji' => '🏆',
                'label' => 'Top Contributor',
                'desc'  => '10 or more accepted answers',
                'tier'  => 'gold',
            ),

            // ── Volume ──
            'prolific' => array(
                'emoji' => '📚',
                'label' => 'Prolific',
                'desc'  => 'Asked 25 or more questions',
                'tier'  => 'silver',
            ),
            'encyclopedic' => array(
                'emoji' => '🧠',
                'label' => 'Encyclopedic',
                'desc'  => 'Posted 50 or more answers',
                'tier'  => 'gold',
            ),

            // ── Reputation ──
            'well_received' => array(
                'emoji' => '👍',
                'label' => 'Well Received',
                'desc'  => 'Received 50 or more lifetime upvotes',
                'tier'  => 'silver',
            ),
            'crowd_favourite' => array(
                'emoji' => '⭐',
                'label' => 'Crowd Favourite',
                'desc'  => 'Received 200 or more lifetime upvotes',
                'tier'  => 'gold',
            ),

            // ── Activity ──
            'on_fire' => array(
                'emoji' => '🔥',
                'label' => 'On Fire',
                'desc'  => '5 or more answers posted in the last 30 days',
                'tier'  => 'silver',
            ),
            'veteran' => array(
                'emoji' => '🎖️',
                'label' => 'Veteran',
                'desc'  => 'Active member for 1 year or more',
                'tier'  => 'silver',
            ),
        );
    }

    /* ── Award a Badge ──────────────────────────────────────────────
     *
     * Silently does nothing if the user already has the badge.
     * Returns true if the badge was freshly awarded.
     */
    public static function award( $user_id, $slug ) {
        $user_id = (int) $user_id;
        if ( ! $user_id || ! isset( self::definitions()[ $slug ] ) ) return false;

        $badges = self::get( $user_id );
        if ( in_array( $slug, $badges, true ) ) return false;

        $badges[] = $slug;
        update_user_meta( $user_id, '_cc_qa_badges', wp_json_encode( $badges ) );
        return true;
    }

    /* ── Get All Badges for a User ──────────────────────────────── */
    public static function get( $user_id ) {
        $raw = get_user_meta( (int) $user_id, '_cc_qa_badges', true );
        if ( ! $raw ) return array();
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    /* ── Check and Award Based on Current Stats ────────────────────
     *
     * Called after: question posted, answer posted, answer accepted, vote added.
     * $triggers is an array of event names that just happened, e.g. ['question', 'vote'].
     * We only run checks relevant to what just changed — avoids unnecessary queries.
     */
    public static function check_and_award( $user_id, array $triggers ) {
        $user_id = (int) $user_id;
        if ( ! $user_id ) return;

        $badges  = self::get( $user_id );
        $defs    = self::definitions();

        // ── Participation badges ─────────────────────────────────
        if ( in_array( 'question', $triggers, true ) && ! in_array( 'first_question', $badges, true ) ) {
            self::award( $user_id, 'first_question' );
        }

        if ( in_array( 'answer', $triggers, true ) && ! in_array( 'first_answer', $badges, true ) ) {
            self::award( $user_id, 'first_answer' );
        }

        // ── Accepted answer badges ───────────────────────────────
        if ( in_array( 'accepted', $triggers, true ) ) {
            if ( ! in_array( 'helpful', $badges, true ) ) {
                self::award( $user_id, 'helpful' );
            }
            // Count total accepted answers for this user
            if ( ! in_array( 'top_contributor', $badges, true ) ) {
                $accepted = self::count_accepted_answers( $user_id );
                if ( $accepted >= 10 ) {
                    self::award( $user_id, 'top_contributor' );
                }
            }
        }

        // ── Volume badges ────────────────────────────────────────
        if ( in_array( 'question', $triggers, true ) && ! in_array( 'prolific', $badges, true ) ) {
            $q_count = (int) count_user_posts( $user_id, 'cc_question' );
            if ( $q_count >= 25 ) self::award( $user_id, 'prolific' );
        }

        if ( in_array( 'answer', $triggers, true ) && ! in_array( 'encyclopedic', $badges, true ) ) {
            $a_count = self::count_answers( $user_id );
            if ( $a_count >= 50 ) self::award( $user_id, 'encyclopedic' );
        }

        // ── Reputation badges ────────────────────────────────────
        if ( in_array( 'vote', $triggers, true ) ) {
            $lifetime_up = (int) get_user_meta( $user_id, '_cc_qa_lifetime_upvotes', true );
            if ( ! in_array( 'well_received', $badges, true ) && $lifetime_up >= 50 ) {
                self::award( $user_id, 'well_received' );
            }
            if ( ! in_array( 'crowd_favourite', $badges, true ) && $lifetime_up >= 200 ) {
                self::award( $user_id, 'crowd_favourite' );
            }
        }

        // ── Activity badges ──────────────────────────────────────
        if ( in_array( 'answer', $triggers, true ) && ! in_array( 'on_fire', $badges, true ) ) {
            $recent = self::count_recent_answers( $user_id, 30 );
            if ( $recent >= 5 ) self::award( $user_id, 'on_fire' );
        }

        // Veteran — checked on question OR answer since those are the most common events
        if ( ( in_array( 'question', $triggers, true ) || in_array( 'answer', $triggers, true ) )
             && ! in_array( 'veteran', $badges, true ) ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $registered = strtotime( $user->user_registered );
                if ( ( time() - $registered ) >= YEAR_IN_SECONDS ) {
                    // Must have at least 1 post to qualify (can't just be registered)
                    $total = (int) count_user_posts( $user_id, 'cc_question' )
                           + self::count_answers( $user_id );
                    if ( $total >= 1 ) {
                        self::award( $user_id, 'veteran' );
                    }
                }
            }
        }
    }

    /* ── Query Helpers ──────────────────────────────────────────── */

    private static function count_answers( $user_id ) {
        $q = new WP_Query( array(
            'post_type'      => 'cc_answer',
            'author'         => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        return (int) $q->found_posts;
    }

    private static function count_accepted_answers( $user_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_cc_qa_accepted' AND pm.meta_value = '1'
             WHERE p.post_type = 'cc_answer' AND p.post_status = 'publish' AND p.post_author = %d",
            $user_id
        ) );
    }

    private static function count_recent_answers( $user_id, $days = 30 ) {
        $q = new WP_Query( array(
            'post_type'      => 'cc_answer',
            'author'         => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'date_query'     => array( array( 'after' => $days . ' days ago' ) ),
        ) );
        return (int) $q->found_posts;
    }

    /* ── Render Badge Pills ─────────────────────────────────────────
     *
     * Returns HTML for all badges a user holds.
     * Empty string if they have no badges yet.
     */
    public static function render_pills( $user_id ) {
        $badges = self::get( $user_id );
        if ( empty( $badges ) ) return '';

        $defs   = self::definitions();
        $html   = '<div class="qa-badge-list" role="list" aria-label="Earned badges">';

        foreach ( $badges as $slug ) {
            if ( ! isset( $defs[ $slug ] ) ) continue;
            $b = $defs[ $slug ];
            $html .= sprintf(
                '<span class="qa-badge qa-badge--%s" role="listitem" title="%s">%s %s</span>',
                esc_attr( $b['tier'] ),
                esc_attr( $b['desc'] ),
                $b['emoji'],
                esc_html( $b['label'] )
            );
        }

        $html .= '</div>';
        return $html;
    }

    /* ── SVG Activity Chart ─────────────────────────────────────────
     *
     * Returns a server-rendered SVG showing monthly question + answer
     * counts for the past 12 months.
     *
     * Layout: stacked bar per month (questions at bottom, answers on top).
     * No JS needed — pure SVG with inline styles.
     */
    public static function render_activity_chart( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;

        // Build 12-month buckets (oldest → newest, left → right)
        $months = array();
        for ( $i = 11; $i >= 0; $i-- ) {
            $ts           = strtotime( "first day of -{$i} months 00:00:00" );
            $key          = date( 'Y-m', $ts );
            $months[$key] = array( 'q' => 0, 'a' => 0, 'label' => date( 'M', $ts ) );
        }

        // Count questions per month
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $q_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS ym, COUNT(*) AS cnt
             FROM {$wpdb->posts}
             WHERE post_type = 'cc_question'
               AND post_status = 'publish'
               AND post_author = %d
               AND post_date >= %s
             GROUP BY ym",
            $user_id,
            date( 'Y-m-01', strtotime( '-11 months' ) )
        ) );
        foreach ( $q_rows as $row ) {
            if ( isset( $months[ $row->ym ] ) ) {
                $months[ $row->ym ]['q'] = (int) $row->cnt;
            }
        }

        // Count answers per month
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $a_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS ym, COUNT(*) AS cnt
             FROM {$wpdb->posts}
             WHERE post_type = 'cc_answer'
               AND post_status = 'publish'
               AND post_author = %d
               AND post_date >= %s
             GROUP BY ym",
            $user_id,
            date( 'Y-m-01', strtotime( '-11 months' ) )
        ) );
        foreach ( $a_rows as $row ) {
            if ( isset( $months[ $row->ym ] ) ) {
                $months[ $row->ym ]['a'] = (int) $row->cnt;
            }
        }

        // Check if there's anything to show
        $total_activity = array_sum( array_column( $months, 'q' ) )
                        + array_sum( array_column( $months, 'a' ) );

        if ( $total_activity === 0 ) {
            return '<p class="qa-chart-empty">No activity in the last 12 months yet.</p>';
        }

        // SVG dimensions
        $svg_w      = 560;
        $svg_h      = 120;
        $pad_left   = 24;   // room for y-axis labels
        $pad_bottom = 20;   // room for month labels
        $pad_top    = 8;
        $chart_w    = $svg_w - $pad_left - 4;
        $chart_h    = $svg_h - $pad_bottom - $pad_top;
        $n          = count( $months );
        $bar_gap    = 3;
        $bar_w      = floor( ( $chart_w - ( $n - 1 ) * $bar_gap ) / $n );

        $max_val = 1; // avoid division by zero
        foreach ( $months as $m ) {
            $stack = $m['q'] + $m['a'];
            if ( $stack > $max_val ) $max_val = $stack;
        }

        // Round max up to a clean number for the y-axis
        $y_max  = (int) ceil( $max_val / max( 1, round( $max_val / 4 ) ) ) * max( 1, round( $max_val / 4 ) );
        $y_max  = max( $y_max, 1 );

        // Y-axis gridlines at 0, 50%, 100%
        $gridlines = '';
        foreach ( array( 0, 0.5, 1.0 ) as $frac ) {
            $y = $pad_top + $chart_h - ( $frac * $chart_h );
            $val = round( $frac * $y_max );
            $gridlines .= sprintf(
                '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#E0DDD7" stroke-width="1"/>',
                $pad_left, $y, $svg_w - 4, $y
            );
            $gridlines .= sprintf(
                '<text x="%d" y="%.1f" text-anchor="end" font-size="9" fill="#9A9890">%s</text>',
                $pad_left - 3, $y + 3, $val
            );
        }

        // Bars
        $bars   = '';
        $labels = '';
        $i      = 0;
        foreach ( $months as $ym => $m ) {
            $x       = $pad_left + $i * ( $bar_w + $bar_gap );
            $q_h     = $m['q'] > 0 ? max( 2, round( $m['q'] / $y_max * $chart_h ) ) : 0;
            $a_h     = $m['a'] > 0 ? max( 2, round( $m['a'] / $y_max * $chart_h ) ) : 0;
            $total_h = $q_h + $a_h;

            // Questions bar (bottom, orange)
            if ( $q_h > 0 ) {
                $q_y = $pad_top + $chart_h - $q_h;
                $bars .= sprintf(
                    '<rect x="%d" y="%.1f" width="%d" height="%d" fill="#FF5020" rx="2" opacity="0.85">
                       <title>%s: %d question%s</title>
                     </rect>',
                    $x, $q_y, $bar_w, $q_h,
                    esc_attr( $m['label'] . ' ' . substr( $ym, 0, 4 ) ),
                    $m['q'], $m['q'] === 1 ? '' : 's'
                );
            }

            // Answers bar (top, teal-ish)
            if ( $a_h > 0 ) {
                $a_y = $pad_top + $chart_h - $total_h;
                $bars .= sprintf(
                    '<rect x="%d" y="%.1f" width="%d" height="%d" fill="#0ea5e9" rx="2" opacity="0.75">
                       <title>%s: %d answer%s</title>
                     </rect>',
                    $x, $a_y, $bar_w, $a_h,
                    esc_attr( $m['label'] . ' ' . substr( $ym, 0, 4 ) ),
                    $m['a'], $m['a'] === 1 ? '' : 's'
                );
            }

            // Empty-bar placeholder
            if ( $total_h === 0 ) {
                $bars .= sprintf(
                    '<rect x="%d" y="%.1f" width="%d" height="2" fill="#E0DDD7" rx="1"/>',
                    $x, $pad_top + $chart_h - 2, $bar_w
                );
            }

            // Month label (every other month on narrow, all 12 on wide)
            if ( $i % 2 === 0 || $n <= 6 ) {
                $label_x = $x + $bar_w / 2;
                $labels .= sprintf(
                    '<text x="%.1f" y="%d" text-anchor="middle" font-size="9" fill="#9A9890">%s</text>',
                    $label_x, $svg_h - 4, esc_attr( $m['label'] )
                );
            }

            $i++;
        }

        // Legend
        $legend_y = $pad_top + 2;
        $legend   = sprintf(
            '<rect x="%d" y="%d" width="8" height="8" fill="#FF5020" rx="1" opacity="0.85"/>
             <text x="%d" y="%d" font-size="9" fill="#5C5A55">Questions</text>
             <rect x="%d" y="%d" width="8" height="8" fill="#0ea5e9" rx="1" opacity="0.75"/>
             <text x="%d" y="%d" font-size="9" fill="#5C5A55">Answers</text>',
            $svg_w - 140, $legend_y,
            $svg_w - 129, $legend_y + 8,
            $svg_w - 75,  $legend_y,
            $svg_w - 64,  $legend_y + 8
        );

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"
                  class="qa-activity-chart" role="img"
                  aria-label="Monthly activity chart for the last 12 months"
                  style="width:100%%;max-width:%dpx;height:auto;display:block;">
               %s
               %s
               %s
               %s
             </svg>',
            $svg_w, $svg_h, $svg_w,
            $gridlines,
            $bars,
            $labels,
            $legend
        );

        return $svg;
    }

    /* ── Profile URL ────────────────────────────────────────────────
     *
     * Returns the canonical /questions/author/{login}/ URL for a user.
     * Used everywhere a profile link is needed.
     */
    public static function profile_url( $user_id ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user ) return '';
        return home_url( '/questions/author/' . urlencode( $user->user_nicename ) . '/' );
    }
}
