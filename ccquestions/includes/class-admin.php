<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Admin' ) ) return;

class CC_QA_Admin {

    public static function init() {
        add_action( 'admin_menu',    array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init',    array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_init',    array( __CLASS__, 'handle_reset_leaderboard' ) );
        add_action( 'admin_init',    array( __CLASS__, 'handle_digest_actions' ) );
        add_action( 'updated_option', array( __CLASS__, 'on_option_saved' ), 10, 3 );
        add_action( 'wp_head',       array( __CLASS__, 'output_custom_css' ) );
        add_filter( 'manage_cc_question_posts_columns',       array( __CLASS__, 'question_columns' ) );
        add_action( 'manage_cc_question_posts_custom_column', array( __CLASS__, 'question_column_data' ), 10, 2 );
    }

    /** Output admin-supplied custom CSS on the front-end. */
    public static function output_custom_css() {
        $css = trim( self::get( 'cc_qa_custom_css' ) );
        if ( $css ) {
            echo '<style id="cc-qa-custom-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
        }
    }

    /* ── Defaults ── */
    public static function defaults() {
        return array(
            'cc_qa_page_id'               => 0,
            'cc_qa_questions_per_page'    => 10,
            'cc_qa_answers_per_page'      => 5,
            'cc_qa_answers_on_single'     => 50,
            'cc_qa_min_question_length'   => 10,
            'cc_qa_min_answer_length'     => 20,
            'cc_qa_question_title_max'    => 200,
            'cc_qa_email_max_recipients'  => 500,
            'cc_qa_notify_new_questions'  => 1,
            'cc_qa_notify_new_answers'    => 1,
            'cc_qa_moderate_questions'    => 0,
            // Rate limiting
            'cc_qa_rate_limit_questions'  => 3,
            'cc_qa_rate_limit_answers'    => 3,
            'cc_qa_rate_limit_votes'      => 3,
            'cc_qa_rate_limit_window'     => 10,
            // Archive page content
            'cc_qa_archive_title'         => 'Community Q&A',
            'cc_qa_archive_subtitle'      => 'Ask questions and get answers from the community.',
            'cc_qa_archive_meta_desc'     => '',
            'cc_qa_archive_seo_title'     => '',
            // Leaderboard layout on archive / shortcode pages
            'cc_qa_leaderboard_position'  => 'none',
            // Noindex shortcode pages to avoid duplicate content
            'cc_qa_noindex_shortcode'     => 1,
            // Weekly digest
            'cc_qa_digest_enabled'        => 0,
            'cc_qa_digest_day'            => 'monday',
            // Leaderboard display
            'cc_qa_leaderboard_limit'     => 10,
            'cc_qa_sidebar_sticky'        => 1,
            // Custom CSS
            'cc_qa_custom_css'            => '',
            // Homepage mode
            'cc_qa_homepage_mode'         => 0,
            // Footer credit
            'cc_qa_footer_credit'         => 1,
        );
    }

    /* ── Helper: get option with default ── */
    public static function get( $key ) {
        $defaults = self::defaults();
        return get_option( $key, $defaults[ $key ] ?? '' );
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=cc_question',
            'Q&A Settings',
            'Settings',
            'manage_options',
            'cc-qa-settings',
            array( __CLASS__, 'settings_page' )
        );
    }

    public static function register_settings() {
        foreach ( array_keys( self::defaults() ) as $key ) {
            register_setting( 'cc_qa_settings', $key, array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_' . $key ),
            ) );
        }
    }

    /* ── Sanitizers ── */
    public static function sanitize_cc_qa_page_id( $v )              { return absint( $v ); }
    public static function sanitize_cc_qa_questions_per_page( $v )   { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_answers_per_page( $v )     { return max( 1, min( 20, absint( $v ) ) ); }
    public static function sanitize_cc_qa_answers_on_single( $v )    { return max( 5, min( 200, absint( $v ) ) ); }
    public static function sanitize_cc_qa_min_question_length( $v )  { return max( 5, min( 100, absint( $v ) ) ); }
    public static function sanitize_cc_qa_min_answer_length( $v )    { return max( 5, min( 500, absint( $v ) ) ); }
    public static function sanitize_cc_qa_question_title_max( $v )   { return max( 50, min( 500, absint( $v ) ) ); }
    public static function sanitize_cc_qa_email_max_recipients( $v ) { return max( 10, min( 5000, absint( $v ) ) ); }
    public static function sanitize_cc_qa_notify_new_questions( $v ) { return (int) (bool) $v; }
    public static function sanitize_cc_qa_notify_new_answers( $v )   { return (int) (bool) $v; }
    public static function sanitize_cc_qa_moderate_questions( $v )   { return (int) (bool) $v; }
    public static function sanitize_cc_qa_rate_limit_questions( $v ) { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_answers( $v )   { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_votes( $v )     { return max( 1, min( 100, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_window( $v )    { return max( 1, min( 60, absint( $v ) ) ); }
    public static function sanitize_cc_qa_archive_title( $v )        { return sanitize_text_field( $v ); }
    public static function sanitize_cc_qa_archive_subtitle( $v )     { return sanitize_textarea_field( $v ); }
    public static function sanitize_cc_qa_archive_meta_desc( $v )    { return sanitize_textarea_field( $v ); }
    public static function sanitize_cc_qa_archive_seo_title( $v )    { return sanitize_text_field( $v ); }
    public static function sanitize_cc_qa_leaderboard_position( $v ) {
        return in_array( $v, array( 'none', 'above', 'below', 'sidebar-right', 'sidebar-left' ), true ) ? $v : 'none';
    }
    public static function sanitize_cc_qa_noindex_shortcode( $v )    { return (int) (bool) $v; }
    public static function sanitize_cc_qa_digest_enabled( $v )       { return (int) (bool) $v; }
    public static function sanitize_cc_qa_digest_day( $v ) {
        $days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
        return in_array( $v, $days, true ) ? $v : 'monday';
    }
    public static function sanitize_cc_qa_leaderboard_limit( $v ) { return max( 3, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_sidebar_sticky( $v )    { return (int) (bool) $v; }
    public static function sanitize_cc_qa_custom_css( $v )        { return wp_strip_all_tags( $v ); }
    public static function sanitize_cc_qa_homepage_mode( $v )     { return (int) (bool) $v; }
    public static function sanitize_cc_qa_footer_credit( $v )     { return (int) (bool) $v; }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <style>
        /* ── ccQuestions Admin Settings Styles ── */
        #ccq-settings-wrap { max-width: 900px; }
        #ccq-settings-wrap h1 {
            display: flex; align-items: center; gap: 10px;
            font-size: 22px; font-weight: 700; color: #1e1e1e;
            border-bottom: 3px solid #ff5020; padding-bottom: 14px; margin-bottom: 24px;
        }
        #ccq-settings-wrap h1 .ccq-logo-badge {
            background: #ff5020; color: #fff;
            font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            padding: 3px 8px; border-radius: 4px; margin-left: 4px;
        }
        /* Section headings as cards */
        #ccq-settings-wrap h2.title {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #ff5020;
            border-radius: 0 6px 6px 0;
            padding: 10px 16px;
            margin: 32px 0 4px;
            font-size: 14px; font-weight: 700; color: #1e1e1e;
        }
        #ccq-settings-wrap h2.title:first-of-type { margin-top: 8px; }
        /* Tighten the form table inside sections */
        #ccq-settings-wrap .form-table { margin-top: 0; background: #fff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 6px 6px; }
        #ccq-settings-wrap .form-table th { color: #374151; font-size: 13px; font-weight: 600; padding: 14px 20px; }
        #ccq-settings-wrap .form-table td { padding: 14px 20px; }
        #ccq-settings-wrap .form-table tr:not(:last-child) td,
        #ccq-settings-wrap .form-table tr:not(:last-child) th { border-bottom: 1px solid #f1f5f9; }
        /* Save button */
        #ccq-settings-wrap .submit .button-primary {
            background: #ff5020 !important; border-color: #e04018 !important;
            color: #fff !important; font-weight: 700 !important;
            padding: 8px 28px !important; font-size: 14px !important; height: auto !important;
            border-radius: 6px !important; box-shadow: 0 2px 6px rgba(255,80,32,.3) !important;
            transition: background .15s, box-shadow .15s !important;
        }
        #ccq-settings-wrap .submit .button-primary:hover {
            background: #e04018 !important; box-shadow: 0 4px 12px rgba(255,80,32,.4) !important;
        }
        /* Section description paragraphs */
        #ccq-settings-wrap > form > p,
        #ccq-settings-wrap > form > div.notice + p { color: #4b5563; font-size: 13px; margin: 4px 0 0; }
        /* Notices */
        #ccq-settings-wrap .notice.inline { margin: 8px 0 0; border-radius: 0 4px 4px 0; }
        /* Footer credit card */
        #ccq-footer-credit {
            margin-top: 40px; padding: 16px 20px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            font-size: 13px; color: #6b7280;
        }
        #ccq-footer-credit strong { color: #1e1e1e; }
        #ccq-footer-credit a { color: #ff5020; text-decoration: none; font-weight: 600; }
        #ccq-footer-credit a:hover { text-decoration: underline; }
        #ccq-footer-credit .ccq-version {
            background: #f1f5f9; color: #6b7280;
            font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 20px;
        }
        /* HR separators */
        #ccq-settings-wrap hr { border: none; border-top: 1px solid #e2e8f0; margin: 36px 0; }
        /* Action section headings (below the form) */
        #ccq-settings-wrap > h2:not(.title) { font-size: 15px; font-weight: 700; color: #1e1e1e; margin-bottom: 6px; }
        </style>

        <div class="wrap" id="ccq-settings-wrap">
          <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" fill="#ff5020"/></svg>
            ccQuestions — Settings
            <span class="ccq-logo-badge">v<?php echo esc_html( CC_QA_VERSION ); ?></span>
          </h1>
          <?php settings_errors( 'cc_qa_settings' ); ?>

          <form method="post" action="options.php">
            <?php settings_fields( 'cc_qa_settings' ); ?>

            <!-- ══════════════════════════════════════
                 HOMEPAGE MODE
            ══════════════════════════════════════ -->
            <h2 class="title">🏠 Homepage Mode</h2>
            <p>When enabled, the Q&amp;A feed is served directly at <code><?php echo esc_html( home_url( '/' ) ); ?></code> — your site's front page. No shortcode or page needed. Individual questions remain at <code>/questions/question-slug/</code>. The <code>/questions/</code> archive URL will redirect to <code>/</code> to keep a single canonical URL.</p>
            <?php if ( self::get( 'cc_qa_homepage_mode' ) ) : ?>
              <div class="notice notice-success inline"><p>✅ Homepage mode is <strong>active</strong>. Your front page is the Q&amp;A feed. <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">View it →</a></p></div>
            <?php else : ?>
              <div class="notice notice-info inline"><p>ℹ️ Homepage mode is off. The Q&amp;A feed lives at <a href="<?php echo esc_url( get_post_type_archive_link( 'cc_question' ) ); ?>" target="_blank">/questions/</a>.</p></div>
            <?php endif; ?>
            <table class="form-table">
              <tr>
                <th scope="row">Use Q&amp;A as homepage</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_homepage_mode" value="1"
                           <?php checked( self::get( 'cc_qa_homepage_mode' ) ); ?> />
                    Serve the Q&amp;A feed at <code>/</code> (your site's front page)
                  </label>
                  <p class="description">
                    <strong>Requirements:</strong> WordPress → Settings → Reading → "Your homepage displays" must be set to <strong>"Your latest posts"</strong> (not a static page). If it's set to a static page this setting has no effect.<br>
                    <strong>SEO effect:</strong> The canonical URL for the Q&amp;A feed becomes <code><?php echo esc_html( home_url( '/' ) ); ?></code>. The <code>/questions/</code> archive receives a <code>301 redirect</code> to <code>/</code> so there's no duplicate. Individual question pages at <code>/questions/slug/</code> are unaffected.
                  </p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 ARCHIVE PAGE CONTENT
            ══════════════════════════════════════ -->
            <h2 class="title">📄 Archive Page Content <span style="font-size:13px;font-weight:400;color:#646970;">— controls the <code>/questions/</code> page</span></h2>
            <p>These fields control what appears on your <a href="<?php echo esc_url( get_post_type_archive_link( 'cc_question' ) ); ?>" target="_blank">/questions/ archive page</a>. You can edit the title, intro text, and SEO metadata without touching any template files.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Page heading (H1)</th>
                <td>
                  <input type="text" name="cc_qa_archive_title" class="regular-text"
                         value="<?php echo esc_attr( self::get( 'cc_qa_archive_title' ) ); ?>"
                         placeholder="Creator Q&amp;A Community" />
                  <p class="description">The main heading shown at the top of the /questions/ page.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Subtitle / intro text</th>
                <td>
                  <textarea name="cc_qa_archive_subtitle" class="large-text" rows="3"
                             placeholder="Ask questions and get answers from the community."><?php echo esc_textarea( self::get( 'cc_qa_archive_subtitle' ) ); ?></textarea>
                  <p class="description">Shown below the heading. Keep it one or two sentences — it's visible to users and crawlers.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">SEO title tag override</th>
                <td>
                  <input type="text" name="cc_qa_archive_seo_title" class="large-text"
                         value="<?php echo esc_attr( self::get( 'cc_qa_archive_seo_title' ) ); ?>"
                         placeholder="<?php echo esc_attr( ( self::get( 'cc_qa_archive_title' ) ?: 'Community Q&A' ) . ' — ' . get_bloginfo( 'name' ) ); ?>" />
                  <p class="description">Overrides the <code>&lt;title&gt;</code> tag on /questions/. Leave blank to use the heading + site name. <strong>Note:</strong> Yoast / RankMath will override this if configured there instead.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Meta description</th>
                <td>
                  <textarea name="cc_qa_archive_meta_desc" class="large-text" rows="3"
                             placeholder="Ask questions and get answers from the community."><?php echo esc_textarea( self::get( 'cc_qa_archive_meta_desc' ) ); ?></textarea>
                  <p class="description">The <code>&lt;meta name="description"&gt;</code> for /questions/. Keep under 160 characters for best display in search results. Leave blank to use the subtitle. <strong>Note:</strong> Yoast / RankMath will override this if configured there.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 LEADERBOARD LAYOUT
            ══════════════════════════════════════ -->
            <h2 class="title">🏆 Leaderboard Layout</h2>
            <p>Controls whether and where the leaderboard appears on the <code>/questions/</code> archive page and on any page using the <code>[cc_qa]</code> shortcode. The <code>[cc_qa_leaderboard]</code> shortcode still works independently on any other page.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Leaderboard position</th>
                <td>
                  <select name="cc_qa_leaderboard_position">
                    <?php
                    $positions = array(
                        'none'          => 'Hidden (don\'t show leaderboard)',
                        'above'         => 'Above the Q&A feed',
                        'below'         => 'Below the Q&A feed',
                        'sidebar-right' => 'Sidebar — right of the Q&A feed',
                        'sidebar-left'  => 'Sidebar — left of the Q&A feed',
                    );
                    $current = self::get( 'cc_qa_leaderboard_position' );
                    foreach ( $positions as $val => $label ) :
                    ?>
                      <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
                        <?php echo esc_html( $label ); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <p class="description">Sidebar layouts use a two-column grid (Q&A takes ~65%, leaderboard ~35%). On screens narrower than 900px the leaderboard stacks below the feed automatically.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 DUPLICATE CONTENT
            ══════════════════════════════════════ -->
            <h2 class="title">🔍 SEO — Duplicate Content</h2>
            <p>Because both <code>/questions/</code> and any page using <code>[cc_qa]</code> show the same Q&amp;A feed, Google could treat them as duplicate content. The safest approach is to <code>noindex</code> the shortcode page(s) so only <code>/questions/</code> gets indexed.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Noindex shortcode pages</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_noindex_shortcode" value="1"
                           <?php checked( self::get( 'cc_qa_noindex_shortcode' ) ); ?> />
                    Add <code>&lt;meta name="robots" content="noindex"&gt;</code> to any page containing the <code>[cc_qa]</code> shortcode
                  </label>
                  <p class="description">Recommended if you have both <code>/questions/</code> and a shortcode page live. The shortcode page stays accessible to users but won't be indexed by Google. <strong>Note:</strong> Yoast / RankMath noindex settings will also work — this is just a built-in fallback.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 PAGE & DISPLAY
            ══════════════════════════════════════ -->
            <h2 class="title">Page &amp; Display</h2>
            <table class="form-table">
              <tr>
                <th scope="row">Q&amp;A Page (for email links)</th>
                <td>
                  <?php
                  wp_dropdown_pages( array(
                      'name'             => 'cc_qa_page_id',
                      'show_option_none' => '— Select Page —',
                      'selected'         => (int) self::get( 'cc_qa_page_id' ),
                  ) ); ?>
                  <p class="description">Page containing the <code>[cc_qa]</code> shortcode. Used to generate links in notification emails. If you're using <code>/questions/</code> as your main URL you can leave this blank — email links will use the CPT archive URL automatically.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Questions per page</th>
                <td>
                  <input type="number" name="cc_qa_questions_per_page" min="1" max="50"
                         value="<?php echo esc_attr( self::get( 'cc_qa_questions_per_page' ) ); ?>" class="small-text" />
                  <p class="description">Questions shown before "Load more". (1–50)</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Answers per "Load more"</th>
                <td>
                  <input type="number" name="cc_qa_answers_per_page" min="1" max="20"
                         value="<?php echo esc_attr( self::get( 'cc_qa_answers_per_page' ) ); ?>" class="small-text" />
                  <p class="description">Answers loaded per batch on the single question page. (1–20)</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Max answers on question page</th>
                <td>
                  <input type="number" name="cc_qa_answers_on_single" min="5" max="200"
                         value="<?php echo esc_attr( self::get( 'cc_qa_answers_on_single' ) ); ?>" class="small-text" />
                  <p class="description">Maximum answers loaded at once on a single question page. (5–200)</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 CONTENT RULES
            ══════════════════════════════════════ -->
            <h2 class="title">Content Rules</h2>
            <table class="form-table">
              <tr>
                <th scope="row">Min question length</th>
                <td>
                  <input type="number" name="cc_qa_min_question_length" min="5" max="100"
                         value="<?php echo esc_attr( self::get( 'cc_qa_min_question_length' ) ); ?>" class="small-text" /> characters
                </td>
              </tr>
              <tr>
                <th scope="row">Min answer length</th>
                <td>
                  <input type="number" name="cc_qa_min_answer_length" min="5" max="500"
                         value="<?php echo esc_attr( self::get( 'cc_qa_min_answer_length' ) ); ?>" class="small-text" /> characters
                </td>
              </tr>
              <tr>
                <th scope="row">Question title max length</th>
                <td>
                  <input type="number" name="cc_qa_question_title_max" min="50" max="500"
                         value="<?php echo esc_attr( self::get( 'cc_qa_question_title_max' ) ); ?>" class="small-text" /> characters
                </td>
              </tr>
              <tr>
                <th scope="row">Moderate new questions</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_moderate_questions" value="1"
                           <?php checked( self::get( 'cc_qa_moderate_questions' ) ); ?> />
                    Hold new questions for admin review before publishing
                  </label>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 EMAIL NOTIFICATIONS
            ══════════════════════════════════════ -->
            <h2 class="title">Email Notifications</h2>
            <table class="form-table">
              <tr>
                <th scope="row">Notify on new questions</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_notify_new_questions" value="1"
                           <?php checked( self::get( 'cc_qa_notify_new_questions' ) ); ?> />
                    Email all registered members when a new question is posted
                  </label>
                </td>
              </tr>
              <tr>
                <th scope="row">Notify on new answers</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_notify_new_answers" value="1"
                           <?php checked( self::get( 'cc_qa_notify_new_answers' ) ); ?> />
                    Email question subscribers when a new answer or reply is posted
                  </label>
                </td>
              </tr>
              <tr>
                <th scope="row">Max email recipients</th>
                <td>
                  <input type="number" name="cc_qa_email_max_recipients" min="10" max="5000"
                         value="<?php echo esc_attr( self::get( 'cc_qa_email_max_recipients' ) ); ?>" class="small-text" />
                  <p class="description">Cap on users emailed per new question notification. (10–5000) Use a transactional email provider like Postmark or SendGrid for high numbers.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 RATE LIMITING
            ══════════════════════════════════════ -->
            <h2 class="title">Rate Limiting</h2>
            <p>Controls how many questions, answers, and votes a user can submit within a rolling time window. Admins and editors are never rate-limited.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Rate limit window</th>
                <td>
                  <input type="number" name="cc_qa_rate_limit_window" min="1" max="60"
                         value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_window' ) ); ?>" class="small-text" /> minutes
                  <p class="description">The rolling time window that applies to all three limits below. (1–60 minutes)</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Max questions per window</th>
                <td>
                  <input type="number" name="cc_qa_rate_limit_questions" min="1" max="50"
                         value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_questions' ) ); ?>" class="small-text" />
                </td>
              </tr>
              <tr>
                <th scope="row">Max answers per window</th>
                <td>
                  <input type="number" name="cc_qa_rate_limit_answers" min="1" max="50"
                         value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_answers' ) ); ?>" class="small-text" />
                </td>
              </tr>
              <tr>
                <th scope="row">Max votes per window</th>
                <td>
                  <input type="number" name="cc_qa_rate_limit_votes" min="1" max="100"
                         value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_votes' ) ); ?>" class="small-text" />
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 WEEKLY DIGEST
            ══════════════════════════════════════ -->
            <h2 class="title">Weekly Community Digest</h2>
            <p>Sends a weekly email to every subscriber with the top questions and best answers from the past 7 days. Only users who have subscribed to at least one question will receive the digest.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Enable weekly digest</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_digest_enabled" value="1"
                           <?php checked( self::get( 'cc_qa_digest_enabled' ) ); ?> />
                    Send a weekly digest email to all Q&amp;A subscribers
                  </label>
                </td>
              </tr>
              <tr>
                <th scope="row">Send on</th>
                <td>
                  <select name="cc_qa_digest_day">
                    <?php
                    $days    = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
                    $current = self::get( 'cc_qa_digest_day' );
                    foreach ( $days as $d ) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr( $d ),
                            selected( $current, $d, false ),
                            esc_html( ucfirst( $d ) )
                        );
                    }
                    ?>
                  </select>
                  <p class="description">Digest is sent at 9:00 am site time on the chosen day.</p>
                  <?php
                  $next_ts = wp_next_scheduled( 'cc_qa_weekly_digest' );
                  $last    = get_option( 'cc_qa_digest_last_sent', '' );
                  if ( $next_ts ) {
                      echo '<p class="description">Next scheduled send: <strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_ts ) ) . '</strong></p>';
                  }
                  if ( $last ) {
                      echo '<p class="description">Last sent: ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last ) ) ) . '</p>';
                  }
                  ?>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 LEADERBOARD DISPLAY
            ══════════════════════════════════════ -->
            <h2 class="title">🏆 Leaderboard Display</h2>
            <table class="form-table">
              <tr>
                <th scope="row">Max users shown</th>
                <td>
                  <input type="number" name="cc_qa_leaderboard_limit" min="3" max="50"
                         value="<?php echo esc_attr( self::get( 'cc_qa_leaderboard_limit' ) ); ?>" class="small-text" />
                  <p class="description">Maximum number of users shown per leaderboard category. (3–50)</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Sidebar sticky</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_sidebar_sticky" value="1"
                           <?php checked( self::get( 'cc_qa_sidebar_sticky' ) ); ?> />
                    Keep the sidebar leaderboard sticky (scrolls with the page)
                  </label>
                  <p class="description">When enabled the sidebar leaderboard stays visible as users scroll through a long question feed. Automatically disabled on screens narrower than 900px regardless of this setting.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 CUSTOM CSS
            ══════════════════════════════════════ -->
            <h2 class="title">🎨 Custom CSS</h2>
            <p>Add custom CSS to override or extend the plugin's default styles. This is output in a <code>&lt;style&gt;</code> tag on every front-end page. The plugin stylesheet is roughly 2,100 lines — use your browser's inspector to find class names and override here rather than editing the plugin files directly.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Custom CSS</th>
                <td>
                  <textarea name="cc_qa_custom_css" class="large-text code" rows="12"
                             placeholder="/* Example: change the primary accent colour */&#10;:root { --orange: #e63946; }"
                             style="font-family:monospace;font-size:13px;"><?php echo esc_textarea( self::get( 'cc_qa_custom_css' ) ); ?></textarea>
                  <p class="description">Plain CSS only — no <code>&lt;style&gt;</code> tags needed. HTML is stripped automatically. Changes apply immediately on save.</p>
                </td>
              </tr>
            </table>

            <!-- ══════════════════════════════════════
                 FOOTER CREDIT
            ══════════════════════════════════════ -->
            <h2 class="title">🔗 Footer Credit</h2>
            <p>Show a small, unobtrusive credit link at the bottom of the Q&amp;A forum. Keeping this enabled is appreciated and helps the plugin grow — but it is entirely optional and not required for use on WordPress.org.</p>
            <table class="form-table">
              <tr>
                <th scope="row">Show "Powered by ccQuestions"</th>
                <td>
                  <label>
                    <input type="checkbox" name="cc_qa_footer_credit" value="1"
                           <?php checked( self::get( 'cc_qa_footer_credit' ) ); ?> />
                    Display a small credit link at the bottom of the Q&amp;A forum
                  </label>
                  <p class="description">Displays: <em>"Powered by <a href="https://creatorconnected.com/questions/" target="_blank">ccQuestions</a>"</em> — small, styled subtly, placed at the very bottom of the feed. Checked by default. You are free to uncheck this.</p>
                </td>
              </tr>
            </table>

            <?php submit_button( 'Save Settings' ); ?>
          </form>

          <hr>
          <h2>📬 Send Digest Now</h2>
          <p>Send the weekly digest immediately to all current subscribers (useful for testing or a manual send).</p>
          <form method="post">
            <?php wp_nonce_field( 'cc_qa_digest_actions', 'cc_qa_digest_nonce' ); ?>
            <input type="hidden" name="cc_qa_action" value="send_digest_now">
            <?php submit_button( 'Send Digest Now', 'secondary', 'send_digest', false ); ?>
          </form>

          <hr>
          <h2>🔄 Leaderboard Reset</h2>
          <p>Reset the leaderboard so scores start fresh from today. <strong>Lifetime upvote and downvote counts shown next to usernames are never affected</strong> — only the period scores reset.</p>
          <?php
          $reset_date = get_option( 'cc_qa_leaderboard_reset_date', '' );
          if ( $reset_date ) {
              echo '<p><strong>Last reset:</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $reset_date ) ) ) . '</p>';
          } else {
              echo '<p><em>Leaderboard has never been reset — showing all-time stats.</em></p>';
          }
          ?>
          <form method="post">
            <?php wp_nonce_field( 'cc_qa_reset_leaderboard', 'cc_qa_reset_nonce' ); ?>
            <input type="hidden" name="cc_qa_action" value="reset_leaderboard">
            <?php submit_button( '⚠️ Reset Leaderboard Now', 'delete', 'reset_lb', false, array( 'onclick' => 'return confirm("Reset the leaderboard? Scores will restart from today. Lifetime vote counts are preserved.");' ) ); ?>
          </form>

          <hr>
          <h2>Shortcodes</h2>
          <p>
            <code>[cc_qa]</code> — Q&amp;A browse feed. Place on any page.<br>
            <code>[cc_qa_leaderboard]</code> — Top contributors scoreboard (standalone, any page).<br>
            <code>[cc_qa_leaderboard limit="5"]</code> — Show top 5 per category (default: 10).<br>
            Individual questions are served at <code>/questions/question-title/</code> automatically.
          </p>
          <h2>Topics</h2>
          <p><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=cc_question_topic&post_type=cc_question' ) ); ?>">
            Manage Q&amp;A Topics →
          </a></p>
          <h2>Archive Page</h2>
          <p>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'cc_question' ) ); ?>" target="_blank">View /questions/ →</a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cc_question' ) ); ?>">Manage Questions →</a>
          </p>

          <div id="ccq-footer-credit">
            <div>
              <strong>ccQuestions</strong> by
              <a href="https://creatorconnected.com" target="_blank" rel="noopener">CreatorConnected</a>
              &nbsp;·&nbsp;
              <a href="https://creatorconnected.com/questions/" target="_blank" rel="noopener">Live Demo</a>
              &nbsp;·&nbsp;
              <a href="https://github.com/mcnallen/wp-plugins" target="_blank" rel="noopener">GitHub</a>
            </div>
            <span class="ccq-version">v<?php echo esc_html( CC_QA_VERSION ); ?></span>
          </div>
        </div><!-- /#ccq-settings-wrap -->
        <?php
    }

    /**
     * Handle digest manual send from the settings page POST.
     */
    public static function handle_digest_actions() {
        if ( empty( $_POST['cc_qa_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( 'send_digest_now' === $_POST['cc_qa_action'] ) {
            check_admin_referer( 'cc_qa_digest_actions', 'cc_qa_digest_nonce' );
            CC_QA_Digest::send();
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Weekly digest sent to all subscribers.</p></div>';
            } );
        }
    }

    /**
     * When digest settings change, reschedule the cron event.
     */
    public static function on_option_saved( $option, $old, $new ) {
        if ( in_array( $option, array( 'cc_qa_digest_enabled', 'cc_qa_digest_day' ), true ) ) {
            CC_QA_Digest::reschedule();
        }
        // Rewrite rules must be flushed when homepage mode changes so the
        // /questions/ → / redirect takes effect immediately.
        if ( 'cc_qa_homepage_mode' === $option && $old !== $new ) {
            flush_rewrite_rules();
        }
    }

    public static function handle_reset_leaderboard() {
        if ( empty( $_POST['cc_qa_action'] ) || $_POST['cc_qa_action'] !== 'reset_leaderboard' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'cc_qa_reset_leaderboard', 'cc_qa_reset_nonce' );
        CC_QA_Leaderboard::reset_stats();
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Leaderboard has been reset. Scores now count from today. Lifetime vote counts are unchanged.</p></div>';
        } );
    }

    public static function question_columns( $columns ) {
        return array(
            'cb'           => $columns['cb'],
            'title'        => 'Question',
            'author'       => 'Asked By',
            'qa_votes'     => 'Votes',
            'qa_answers'   => 'Answers',
            'qa_accepted'  => 'Accepted',
            'taxonomy-cc_question_topic' => 'Topic',
            'date'         => 'Date',
        );
    }

    public static function question_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'qa_votes':   echo (int) get_post_meta( $post_id, '_cc_qa_votes', true );        break;
            case 'qa_answers': echo (int) get_post_meta( $post_id, '_cc_qa_answer_count', true ); break;
            case 'qa_accepted': echo esc_html( get_post_meta( $post_id, '_cc_qa_accepted', true ) ? '✓' : '—' ); break;
        }
    }
}
