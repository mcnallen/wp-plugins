<?php
/**
 * CC QA Weekly Digest
 *
 * Sends a weekly summary of top questions and their best answers
 * to every subscriber. Includes a global unsubscribe link (token-based).
 *
 * Cron event:  cc_qa_weekly_digest
 * Schedule:    Weekly (runs every 7 days, offset to next chosen weekday at 9am site time)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Digest' ) ) return;

class CC_QA_Digest {

    const CRON_HOOK = 'cc_qa_weekly_digest';

    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'send' ) );
    }

    /* ── Scheduling ─────────────────────────────────────────────── */

    public static function schedule() {
        if ( ! CC_QA_Admin::get( 'cc_qa_digest_enabled' ) ) return;
        if ( wp_next_scheduled( self::CRON_HOOK ) ) return;

        // Schedule for next occurrence of the chosen weekday at 09:00 site time
        $day_name  = CC_QA_Admin::get( 'cc_qa_digest_day' ) ?: 'monday';
        $site_tz   = get_option( 'timezone_string' ) ?: 'UTC';
        try {
            $tz   = new DateTimeZone( $site_tz );
            $next = new DateTime( 'next ' . $day_name . ' 09:00:00', $tz );
        } catch ( Exception $e ) {
            $next = new DateTime( 'next monday 09:00:00', new DateTimeZone( 'UTC' ) );
        }

        wp_schedule_event( $next->getTimestamp(), 'weekly', self::CRON_HOOK );
    }

    public static function unschedule() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) wp_unschedule_event( $ts, self::CRON_HOOK );
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Reschedule when admin changes day-of-week or toggled on.
     * Called by CC_QA_Admin after saving settings.
     */
    public static function reschedule() {
        self::unschedule();
        self::schedule();
    }

    /* ── Main send method ──────────────────────────────────────── */

    public static function send() {
        if ( ! CC_QA_Admin::get( 'cc_qa_digest_enabled' ) ) return;

        $top_questions = self::get_top_questions( 5 );
        if ( empty( $top_questions ) ) return;

        $subscribers = CC_QA_Database::get_digest_subscribers();
        if ( empty( $subscribers ) ) return;

        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        $subject   = "[{$site_name}] This Week's Top Questions";
        $body_html = self::build_digest_html( $top_questions, $site_name, $site_url );

        $max  = (int) CC_QA_Admin::get( 'cc_qa_email_max_recipients' );
        $sent = 0;
        foreach ( $subscribers as $sub ) {
            if ( $sent >= $max ) break;
            // Build a personalised version with a global-unsubscribe link
            $unsub_url = CC_QA_Email::get_unsubscribe_url( 0, $sub->token );
            $final     = str_replace( '{{UNSUB_URL}}', esc_url( $unsub_url ), $body_html );
            self::send_email( $sub->email, $subject, $final );
            $sent++;
        }

        update_option( 'cc_qa_digest_last_sent', gmdate( 'Y-m-d H:i:s' ) );
    }

    /* ── Query ─────────────────────────────────────────────────── */

    private static function get_top_questions( $count = 5 ) {
        return get_posts( array(
            'post_type'      => 'cc_question',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'meta_key'       => '_cc_qa_votes',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'date_query'     => array( array( 'after' => '7 days ago' ) ),
        ) );
    }

    /* ── HTML builder ──────────────────────────────────────────── */

    private static function build_digest_html( $questions, $site_name, $site_url ) {
        $orange = '#FF5020';
        $text   = '#111110';
        $text2  = '#5C5A55';
        $border = '#E0DDD7';
        $off    = '#F3F2EF';

        // Build question rows
        $rows = '';
        foreach ( $questions as $q ) {
            $q_url   = get_permalink( $q->ID );
            $votes   = (int) get_post_meta( $q->ID, '_cc_qa_votes', true );
            $a_count = (int) get_post_meta( $q->ID, '_cc_qa_answer_count', true );
            $q_author = CC_QA_Shortcode::get_author_display( $q->post_author );

            // Grab top answer
            $top_answers = get_posts( array(
                'post_type'      => 'cc_answer',
                'post_parent'    => $q->ID,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_key'       => '_cc_qa_votes',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            ) );
            $top_answer = ! empty( $top_answers ) ? $top_answers[0] : null;

            $rows .= "
            <tr><td style='padding:0 0 28px;'>
              <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid {$border};border-radius:10px;overflow:hidden;'>
                <tr>
                  <td style='background:#fff;padding:20px 24px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                      <tr>
                        <td style='padding-bottom:6px;'>
                          <a href='" . esc_url( $q_url ) . "'
                             style='font-size:17px;font-weight:800;color:{$text};text-decoration:none;line-height:1.3;letter-spacing:-0.02em;'>
                            " . esc_html( $q->post_title ) . "
                          </a>
                        </td>
                      </tr>
                      <tr>
                        <td style='font-size:12px;color:#9A9890;padding-bottom:10px;'>
                          Asked by <strong>" . esc_html( $q_author->name ) . "</strong>
                          &nbsp;·&nbsp; {$votes} votes
                          &nbsp;·&nbsp; {$a_count} " . _n( 'answer', 'answers', $a_count, 'cc-qa' ) . "
                        </td>
                      </tr>";

            if ( $top_answer ) {
                $ans_author = CC_QA_Shortcode::get_author_display( $top_answer->post_author );
                $excerpt    = wp_trim_words( wp_strip_all_tags( $top_answer->post_content ), 30, '…' );
                $rows .= "
                      <tr>
                        <td style='background:{$off};border-radius:6px;padding:12px 16px;border-left:3px solid {$orange};'>
                          <p style='margin:0 0 4px;font-size:11px;color:#9A9890;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;'>
                            Top answer — " . esc_html( $ans_author->name ) . "
                          </p>
                          <p style='margin:0;font-size:14px;color:{$text2};line-height:1.6;'>" . esc_html( $excerpt ) . "</p>
                        </td>
                      </tr>";
            }

            $rows .= "
                      <tr>
                        <td style='padding-top:14px;'>
                          <a href='" . esc_url( $q_url ) . "'
                             style='display:inline-block;background:{$orange};color:#fff;font-size:13px;font-weight:700;padding:9px 18px;border-radius:8px;text-decoration:none;'>
                            " . ( $a_count > 0 ? 'Read the Discussion →' : 'Be the First to Answer →' ) . "
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td></tr>";
        }

        $unsub_line = "<p style='font-size:12px;color:#9A9890;text-align:center;margin-top:20px;'>
            You're subscribed to the weekly Q&amp;A digest because you're a member of
            <a href='{$site_url}' style='color:{$orange};'>{$site_name}</a>.
            &nbsp;·&nbsp;
            <a href='{{UNSUB_URL}}' style='color:#9A9890;'>Unsubscribe from all Q&amp;A emails</a>
          </p>";

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:{$off};font-family:\"Instrument Sans\",Helvetica,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' border='0'>
    <tr><td align='center' style='padding:40px 20px;'>
      <table width='600' cellpadding='0' cellspacing='0' border='0' style='max-width:600px;width:100%;'>

        <!-- Header -->
        <tr><td style='background:{$text};border-radius:12px 12px 0 0;padding:24px 40px;'>
          <table width='100%' cellpadding='0' cellspacing='0'>
            <tr>
              <td>
                <a href='{$site_url}' style='font-size:18px;font-weight:800;color:#fff;text-decoration:none;letter-spacing:-0.03em;'>
                  Creator<span style='color:{$orange};'>Connected</span>
                </a>
              </td>
              <td align='right'>
                <span style='font-size:12px;color:rgba(255,255,255,0.5);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;'>Weekly Digest</span>
              </td>
            </tr>
          </table>
        </td></tr>

        <!-- Intro -->
        <tr><td style='background:#fff;padding:32px 40px 16px;border-left:1px solid {$border};border-right:1px solid {$border};'>
          <h2 style='margin:0 0 8px;font-size:24px;font-weight:800;color:{$text};letter-spacing:-0.03em;'>This week's top questions</h2>
          <p style='margin:0 0 4px;font-size:14px;color:{$text2};'>The most active questions from your community this week.</p>
        </td></tr>

        <!-- Questions -->
        <tr><td style='background:#fff;padding:16px 40px 8px;border-left:1px solid {$border};border-right:1px solid {$border};'>
          <table width='100%' cellpadding='0' cellspacing='0'>
            {$rows}
          </table>
        </td></tr>

        <!-- Footer -->
        <tr><td style='background:{$off};border:1px solid {$border};border-top:none;border-radius:0 0 12px 12px;padding:24px 40px;'>
          {$unsub_line}
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";
    }

    /* ── Email send helper ─────────────────────────────────────── */

    private static function send_email( $to, $subject, $body ) {
        $cb = static function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $cb );
        wp_mail( $to, $subject, $body, array(
            'From: ' . get_bloginfo( 'name' ) . ' <noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
        ) );
        remove_filter( 'wp_mail_content_type', $cb );
    }
}
