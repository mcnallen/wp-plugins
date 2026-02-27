<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Email' ) ) return;

class CC_QA_Email {

    /**
     * Notify all registered users when a new question is posted.
     */
    public static function notify_new_question( $question_id ) {
        if ( ! CC_QA_Admin::get( 'cc_qa_notify_new_questions' ) ) return;

        $question    = get_post( $question_id );
        $author_data = get_userdata( $question->post_author );
        $author_name = ( $author_data && ! empty( trim( $author_data->display_name ) ) )
            ? $author_data->display_name
            : ( $author_data->user_login ?? 'A community member' );

        $q_url     = self::question_url( $question_id );
        $site_name = get_bloginfo( 'name' );
        $subject   = "[{$site_name}] New question: " . esc_html( $question->post_title );

        $users = get_users( array(
            'fields' => array( 'ID', 'user_email', 'display_name' ),
            'number' => (int) CC_QA_Admin::get( 'cc_qa_email_max_recipients' ),
        ) );

        foreach ( $users as $u ) {
            if ( $author_data && $u->user_email === $author_data->user_email ) continue;

            // Subscribe the user to this question (creates a token row) so they can
            // unsubscribe — token is returned and used for a safe, ID-free unsub link.
            $token       = CC_QA_Database::subscribe( $question_id, (int) $u->ID, $u->user_email );
            $unsub_url   = self::get_unsubscribe_url( $question_id, $token );

            $body = self::template(
                "A new question was just posted on {$site_name}.",
                "<strong>" . esc_html( $author_name ) . "</strong> asked:",
                esc_html( $question->post_title ),
                wp_trim_words( wp_strip_all_tags( $question->post_content ), 30, '…' ),
                $q_url,
                'View &amp; Answer the Question',
                $unsub_url
            );

            self::send( $u->user_email, $subject, $body );
        }
    }

    /**
     * Notify question subscribers when a new answer is posted.
     */
    public static function notify_new_answer( $question_id, $answer_id ) {
        if ( ! CC_QA_Admin::get( 'cc_qa_notify_new_answers' ) ) return;

        $question           = get_post( $question_id );
        $answer             = get_post( $answer_id );
        $answer_author_data = get_userdata( $answer->post_author );
        $author_name        = ( $answer_author_data && ! empty( trim( $answer_author_data->display_name ) ) )
            ? $answer_author_data->display_name
            : ( $answer_author_data->user_login ?? 'A community member' );

        $q_url     = self::question_url( $question_id );
        $site_name = get_bloginfo( 'name' );
        $subject   = "[{$site_name}] New answer to: " . esc_html( $question->post_title );

        $subscribers = CC_QA_Database::get_subscribers( $question_id );

        foreach ( $subscribers as $sub ) {
            if ( (int) $sub->user_id === (int) $answer->post_author ) continue;

            $body = self::template(
                "Someone answered a question you're following on {$site_name}.",
                "<strong>" . esc_html( $author_name ) . "</strong> answered:",
                esc_html( $question->post_title ),
                wp_trim_words( wp_strip_all_tags( $answer->post_content ), 30, '…' ),
                $q_url,
                'Read the Full Answer',
                self::get_unsubscribe_url( $question_id, $sub->token ?? '' )
            );

            self::send( $sub->email, $subject, $body );
        }
    }

    /**
     * Notify question subscribers when a reply is posted on an answer.
     */
    public static function notify_new_reply( $question_id, $answer_id, $content, $user ) {
        if ( ! CC_QA_Admin::get( 'cc_qa_notify_new_answers' ) ) return;

        $question  = get_post( $question_id );
        $q_url     = self::question_url( $question_id ) . '#answer-' . $answer_id;
        $site_name = get_bloginfo( 'name' );
        $name      = ! empty( trim( $user->display_name ) ) ? $user->display_name : $user->user_login;
        $subject   = "[{$site_name}] New reply on: " . esc_html( $question->post_title );

        $subscribers = CC_QA_Database::get_subscribers( $question_id );

        foreach ( $subscribers as $sub ) {
            if ( (int) $sub->user_id === (int) $user->ID ) continue;

            $body = self::template(
                "Someone replied to an answer on a question you're following.",
                "<strong>" . esc_html( $name ) . "</strong> replied:",
                esc_html( $question->post_title ),
                wp_trim_words( wp_strip_all_tags( $content ), 25, '…' ),
                $q_url,
                'View the Reply',
                self::get_unsubscribe_url( $question_id, $sub->token ?? '' )
            );

            self::send( $sub->email, $subject, $body );
        }
    }

    /**
     * Shared HTML email template.
     */
    private static function template( $intro, $byline, $title, $excerpt, $cta_url, $cta_label, $unsub_url = '' ) {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        $orange    = '#FF5020';
        $text      = '#111110';
        $text2     = '#5C5A55';
        $border    = '#E0DDD7';
        $off       = '#F3F2EF';

        $unsub_line = $unsub_url
            ? "<p style='font-size:12px;color:#9A9890;text-align:center;margin-top:24px;'>
                 <a href='" . esc_url( $unsub_url ) . "' style='color:#9A9890;'>Unsubscribe from these notifications</a>
               </p>"
            : '';

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#F3F2EF;font-family:\"Instrument Sans\",Helvetica,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' border='0'>
    <tr><td align='center' style='padding:40px 20px;'>
      <table width='600' cellpadding='0' cellspacing='0' border='0' style='max-width:600px;width:100%;'>

        <!-- Header -->
        <tr><td style='background:{$text};border-radius:12px 12px 0 0;padding:28px 40px;'>
          <a href='{$site_url}' style='font-size:18px;font-weight:800;color:#fff;text-decoration:none;letter-spacing:-0.03em;'>
            Creator<span style='color:{$orange};'>Connected</span>
          </a>
        </td></tr>

        <!-- Body -->
        <tr><td style='background:#fff;padding:40px;border-left:1px solid {$border};border-right:1px solid {$border};'>
          <p style='margin:0 0 20px;font-size:14px;color:{$text2};line-height:1.6;'>{$intro}</p>
          <p style='margin:0 0 8px;font-size:13px;color:#9A9890;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;'>{$byline}</p>
          <h2 style='margin:0 0 12px;font-size:22px;font-weight:800;color:{$text};letter-spacing:-0.03em;line-height:1.2;'>{$title}</h2>
          " . ( $excerpt ? "<p style='margin:0 0 28px;font-size:15px;color:{$text2};line-height:1.7;background:{$off};padding:16px 20px;border-radius:8px;border-left:3px solid {$orange};'>{$excerpt}</p>" : '' ) . "
          <a href='" . esc_url( $cta_url ) . "' style='display:inline-block;background:{$orange};color:#fff;font-size:15px;font-weight:700;padding:14px 28px;border-radius:10px;text-decoration:none;'>
            {$cta_label} →
          </a>
        </td></tr>

        <!-- Footer -->
        <tr><td style='background:{$off};border:1px solid {$border};border-top:none;border-radius:0 0 12px 12px;padding:24px 40px;'>
          <p style='margin:0;font-size:12px;color:#9A9890;text-align:center;'>
            You're receiving this because you're a member of <a href='{$site_url}' style='color:{$orange};'>{$site_name}</a>.
          </p>
          {$unsub_line}
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";
    }

    private static function send( $to, $subject, $body ) {
        $callback = static function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $callback );
        wp_mail( $to, $subject, $body, array(
            'From: ' . get_bloginfo( 'name' ) . ' <noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
        ) );
        remove_filter( 'wp_mail_content_type', $callback );
    }

    /**
     * Fix: always use the CPT permalink — old code used query args that no longer apply.
     */
    private static function question_url( $question_id ) {
        return get_permalink( $question_id );
    }

    /**
     * Build a token-based unsubscribe URL — no user IDs in the URL.
     *
     * For per-question notifications: pass $question_id + $token (token from the subscription row).
     * For global "unsubscribe from all" (digest): pass $token only (question_id = 0).
     */
    public static function get_unsubscribe_url( $question_id = 0, $token = '' ) {
        if ( ! $token ) return '';
        return add_query_arg( array(
            'action'   => 'cc_qa_unsubscribe',
            'token'    => urlencode( $token ),
            'scope'    => $question_id ? 'question' : 'all',
        ), admin_url( 'admin-ajax.php' ) );
    }

    /**
     * Nopriv AJAX handler — processes unsubscribe token links from emails.
     * Registered in cc-qa.php alongside the other ajax hooks.
     */
    public static function handle_unsubscribe() {
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
        $scope = sanitize_key( $_GET['scope'] ?? 'question' );

        if ( ! $token ) {
            wp_die( 'Invalid unsubscribe link.', 'Unsubscribe Error', array( 'response' => 400 ) );
        }

        $sub = CC_QA_Database::get_sub_by_token( $token );
        if ( ! $sub ) {
            wp_die( 'This unsubscribe link has already been used or is invalid.', 'Already Unsubscribed', array( 'response' => 200 ) );
        }

        if ( 'all' === $scope ) {
            CC_QA_Database::unsubscribe_all_for_user( (int) $sub->user_id );
            wp_die( '✅ You have been unsubscribed from all Q&amp;A email notifications. You can re-subscribe by participating in a question or answer.', 'Unsubscribed', array( 'response' => 200 ) );
        } else {
            CC_QA_Database::unsubscribe_by_token( $token );
            $q_title = get_the_title( (int) $sub->question_id );
            /* translators: %s: question title */
            $msg = $q_title
                ? sprintf( '✅ You have been unsubscribed from notifications for: <strong>%s</strong>.', esc_html( $q_title ) )
                : '✅ You have been unsubscribed.';
            wp_die( $msg, 'Unsubscribed', array( 'response' => 200 ) );
        }
    }
}
