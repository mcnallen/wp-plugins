<?php
/**
 * Q&A Community Member Profile
 *
 * Served at /questions/author/{username}/ via the cc_qa_author_name query var.
 * Unique content: badges, activity chart, expanded stats, leaderboard, Q&A history.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Resolve user from URL slug ─────────────────────────────────────────────
$author_nicename = sanitize_user( get_query_var( 'cc_qa_author_name', '' ) );
$profile_user    = $author_nicename ? get_user_by( 'slug', $author_nicename ) : null;

wp_enqueue_style(  'cc-qa-style',  CC_QA_URL . 'assets/css/ccquestions.css',  array(), CC_QA_VERSION );
wp_enqueue_script( 'cc-qa-script', CC_QA_URL . 'assets/js/ccquestions.js',    array(), CC_QA_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
wp_localize_script( 'cc-qa-script', 'CC_QA', cc_qa_js_config() );

if ( ! $profile_user ) {
    status_header( 404 );
    get_header();
    echo '<main class="cc-qa-single-page"><div class="page-wrap cc-qa-wrap">
            <p class="qa-profile-empty">Member not found.</p>
          </div></main>';
    get_footer();
    return;
}

$uid        = (int) $profile_user->ID;
$name       = ! empty( trim( $profile_user->display_name ) ) ? $profile_user->display_name : $profile_user->user_login;
$initial    = strtoupper( mb_substr( $name, 0, 1 ) ) ?: 'M';
$bio        = get_user_meta( $uid, 'description', true );
$browse_url = get_post_type_archive_link( 'cc_question' );
$site_name  = get_bloginfo( 'name' );

// Gravatar
$gravatar_url = get_avatar_url( $uid, array( 'size' => 144, 'default' => '404' ) );
$has_gravatar = $gravatar_url && strpos( $gravatar_url, 'd=404' ) === false;

// ── Stats ──────────────────────────────────────────────────────────────────
$q_count = (int) count_user_posts( $uid, 'cc_question' );

$answers_query = new WP_Query( array(
    'post_type'      => 'cc_answer',
    'author'         => $uid,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );
$a_count        = (int) $answers_query->found_posts;
$accepted_count = 0;
foreach ( (array) $answers_query->posts as $aid ) {
    if ( get_post_meta( $aid, '_cc_qa_accepted', true ) ) $accepted_count++;
}

// Backfill lifetime vote counts for users who joined before v2.5 tracking was added.
// This is a one-time lazy operation: if the meta key is already set it's a no-op.
CC_QA_Database::backfill_lifetime_votes( $uid );

$lifetime_up   = (int) get_user_meta( $uid, '_cc_qa_lifetime_upvotes',   true );
$lifetime_down = (int) get_user_meta( $uid, '_cc_qa_lifetime_downvotes', true );
// Compute lifetime score using same formula as leaderboard
// We need period upvotes/downvotes from DB for the score formula — use lifetime as proxy
$lifetime_score = ( $lifetime_up * 2 )
                + ( $accepted_count * 10 )
                + $q_count
                + $a_count
                - $lifetime_down;

// Member since days
$days_member = (int) floor( ( time() - strtotime( $profile_user->user_registered ) ) / DAY_IN_SECONDS );
$years       = floor( $days_member / 365 );
$months      = floor( ( $days_member % 365 ) / 30 );
if ( $years > 0 ) {
    $tenure = $years . ( $years === 1 ? ' year' : ' years' );
    if ( $months > 0 ) $tenure .= ', ' . $months . ( $months === 1 ? ' month' : ' months' );
} elseif ( $months > 0 ) {
    $tenure = $months . ( $months === 1 ? ' month' : ' months' );
} else {
    $tenure = $days_member . ( $days_member === 1 ? ' day' : ' days' );
}

// ── Recent Q&A History ─────────────────────────────────────────────────────
$questions = get_posts( array(
    'post_type'      => 'cc_question',
    'author'         => $uid,
    'post_status'    => 'publish',
    'posts_per_page' => 15,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

$answers = get_posts( array(
    'post_type'      => 'cc_answer',
    'author'         => $uid,
    'post_status'    => 'publish',
    'posts_per_page' => 15,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

// ── Leaderboard embed ──────────────────────────────────────────────────────
$lb_position  = CC_QA_Admin::get( 'cc_qa_leaderboard_position' );
$show_lb      = ( 'none' !== $lb_position );
$lb_is_sidebar = in_array( $lb_position, array( 'sidebar-right', 'sidebar-left' ), true );
$lb_html       = $show_lb ? CC_QA_Leaderboard::render_inline( 0, $lb_is_sidebar ? 'compact' : 'stacked' ) : '';

// ── Badges + Chart ─────────────────────────────────────────────────────────
$badge_html  = CC_QA_Badges::render_pills( $uid );
$chart_html  = CC_QA_Badges::render_activity_chart( $uid );
$profile_url = CC_QA_Badges::profile_url( $uid );

// ── SEO ────────────────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', function() use ( $name, $site_name ) {
    return esc_html( $name ) . ' — Q&A Profile · ' . esc_html( $site_name );
} );
add_action( 'wp_head', function() use ( $name, $q_count, $a_count, $accepted_count, $profile_url ) {
    $parts = array();
    if ( $q_count )        $parts[] = $q_count . ' ' . _n( 'question', 'questions', $q_count, 'cc-qa' );
    if ( $a_count )        $parts[] = $a_count . ' ' . _n( 'answer', 'answers', $a_count, 'cc-qa' );
    if ( $accepted_count ) $parts[] = $accepted_count . ' accepted';
    $summary = $parts ? implode( ', ', $parts ) : 'a community member';
    echo '<meta name="description" content="' . esc_attr( "{$name} has contributed {$summary} to the Q&A community." ) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url( $profile_url ) . '">' . "\n";
}, 1 );
add_action( 'wp_head', function() use ( $name, $profile_url, $profile_user ) {
    $schema = array( '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $name, 'url' => $profile_url );
    $desc   = get_user_meta( $profile_user->ID, 'description', true );
    if ( $desc ) $schema['description'] = $desc;
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
} );

get_header();
?>

<main id="main" class="cc-qa-single-page">

<?php if ( $show_lb && $lb_is_sidebar ) :
  $sticky_class = CC_QA_Admin::get( 'cc_qa_sidebar_sticky' ) ? '' : ' cc-qa-sidebar-no-sticky';
?>
<div class="cc-qa-layout-wrap cc-qa-layout-<?php echo esc_attr( $lb_position . $sticky_class ); ?>">
  <div class="cc-qa-layout-main">
<?php endif; ?>

<?php if ( $show_lb && 'above' === $lb_position ) : ?>
  <div class="cc-qa-lb-stacked cc-qa-lb-above">
    <?php echo $lb_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </div>
<?php endif; ?>

  <div class="page-wrap cc-qa-wrap cc-qa-profile" id="cc-qa-app">

    <a href="<?php echo esc_url( $browse_url ); ?>" class="qa-back-link">← Back to all questions</a>

    <!-- ── Profile Header ── -->
    <div class="qa-profile-header">
      <div class="qa-profile-avatar-wrap">
        <?php if ( $has_gravatar ) : ?>
          <img src="<?php echo esc_url( $gravatar_url ); ?>"
               alt="<?php echo esc_attr( $name ); ?>"
               class="qa-avatar-photo"
               width="72" height="72" loading="lazy" />
        <?php else : ?>
          <div class="qa-avatar qa-avatar-profile"><?php echo esc_html( $initial ); ?></div>
        <?php endif; ?>
      </div>
      <div class="qa-profile-info">
        <h1 class="qa-profile-name"><?php echo esc_html( $name ); ?></h1>
        <?php if ( $bio ) : ?>
          <p class="qa-profile-bio"><?php echo esc_html( $bio ); ?></p>
        <?php endif; ?>
        <p class="qa-profile-joined">
          Member for <?php echo esc_html( $tenure ); ?>
          <span class="qa-profile-since"> &mdash; joined <?php echo esc_html( date_i18n( 'F Y', strtotime( $profile_user->user_registered ) ) ); ?></span>
        </p>
      </div>
    </div>

    <!-- ── Badges ── -->
    <?php if ( $badge_html ) : ?>
    <section class="qa-profile-section qa-profile-badges-section">
      <h2 class="qa-profile-section-title">Badges</h2>
      <?php echo $badge_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </section>
    <?php endif; ?>

    <!-- ── Stats Row ── -->
    <div class="qa-profile-stats">

      <div class="qa-profile-stat">
        <span class="qa-profile-stat-value"><?php echo esc_html( number_format( $lifetime_score ) ); ?></span>
        <span class="qa-profile-stat-label">Lifetime Score</span>
      </div>

      <div class="qa-profile-stat">
        <span class="qa-profile-stat-value"><?php echo esc_html( $q_count ); ?></span>
        <span class="qa-profile-stat-label">Questions</span>
      </div>

      <div class="qa-profile-stat">
        <span class="qa-profile-stat-value"><?php echo esc_html( $a_count ); ?></span>
        <span class="qa-profile-stat-label">Answers</span>
      </div>

      <div class="qa-profile-stat">
        <span class="qa-profile-stat-value"><?php echo esc_html( $accepted_count ); ?></span>
        <span class="qa-profile-stat-label">Accepted</span>
      </div>

      <div class="qa-profile-stat">
        <?php
        if ( $lifetime_up > 0 ) {
            $up_class = 'qa-stat-votes-positive';
        } else {
            $up_class = 'qa-stat-votes-zero';
        }
        ?>
        <span class="qa-profile-stat-value <?php echo esc_attr( $up_class ); ?>">
          ▲<?php echo esc_html( number_format( $lifetime_up ) ); ?>
        </span>
        <span class="qa-profile-stat-label">Upvotes</span>
      </div>

      <div class="qa-profile-stat">
        <?php
        if ( $lifetime_down > 0 ) {
            $down_class = 'qa-stat-votes-negative';
        } else {
            $down_class = 'qa-stat-votes-zero';
        }
        ?>
        <span class="qa-profile-stat-value <?php echo esc_attr( $down_class ); ?>">
          ▼<?php echo esc_html( number_format( $lifetime_down ) ); ?>
        </span>
        <span class="qa-profile-stat-label">Downvotes</span>
      </div>

    </div><!-- /.qa-profile-stats -->

    <!-- ── Activity Chart ── -->
    <section class="qa-profile-section">
      <h2 class="qa-profile-section-title">Activity — last 12 months</h2>
      <div class="qa-chart-wrap">
        <?php echo $chart_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
      </div>
    </section>

    <!-- ── Questions ── -->
    <section class="qa-profile-section">
      <h2 class="qa-profile-section-title">
        Questions
        <span class="qa-profile-section-count"><?php echo esc_html( $q_count ); ?></span>
      </h2>
      <?php if ( ! empty( $questions ) ) : ?>
        <div class="qa-profile-questions">
          <?php foreach ( $questions as $q ) :
            $q_votes  = (int) get_post_meta( $q->ID, '_cc_qa_votes', true );
            $q_ans    = (int) get_post_meta( $q->ID, '_cc_qa_answer_count', true );
            $q_acc    = (bool) get_post_meta( $q->ID, '_cc_qa_accepted', true );
            $q_topics = wp_get_object_terms( $q->ID, 'cc_question_topic' );
          ?>
          <div class="qa-profile-q-row" data-href="<?php echo esc_url( get_permalink( $q->ID ) ); ?>">
            <div class="qa-profile-q-main">
              <span class="qa-profile-q-title"><?php echo esc_html( $q->post_title ); ?></span>
              <?php if ( ! empty( $q_topics ) && ! is_wp_error( $q_topics ) ) : ?>
                <span class="qa-profile-q-topics">
                  <?php foreach ( $q_topics as $t ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $t ) ); ?>"
                       class="qa-topic-badge"
                       title="Browse <?php echo esc_attr( $t->name ); ?> questions">
                      <?php echo esc_html( $t->name ); ?>
                    </a>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="qa-profile-q-meta">
              <?php if ( $q_acc ) : ?><span class="qa-status-accepted">✓</span><?php endif; ?>
              <span class="qa-profile-q-stat"><?php echo esc_html( $q_votes ); ?> votes</span>
              <span class="qa-profile-q-stat"><?php echo esc_html( $q_ans ); ?> <?php echo $q_ans === 1 ? 'answer' : 'answers'; ?></span>
              <span class="qa-meta-time"><?php echo esc_html( human_time_diff( get_post_time( 'U', false, $q ), time() ) . ' ago' ); ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ( $q_count > 15 ) : ?>
          <p class="qa-profile-more">Showing 15 most recent of <?php echo esc_html( $q_count ); ?> questions.</p>
        <?php endif; ?>
      <?php else : ?>
        <p class="qa-profile-empty">No questions yet.</p>
      <?php endif; ?>
    </section>

    <!-- ── Answers ── -->
    <section class="qa-profile-section">
      <h2 class="qa-profile-section-title">
        Answers
        <span class="qa-profile-section-count"><?php echo esc_html( $a_count ); ?></span>
      </h2>
      <?php if ( ! empty( $answers ) ) : ?>
        <div class="qa-profile-answers">
          <?php foreach ( $answers as $ans ) :
            $a_votes    = (int) get_post_meta( $ans->ID, '_cc_qa_votes', true );
            $a_accepted = (bool) get_post_meta( $ans->ID, '_cc_qa_accepted', true );
            $parent_q   = get_post( $ans->post_parent );
            if ( ! $parent_q ) continue;
          ?>
          <a href="<?php echo esc_url( get_permalink( $parent_q->ID ) . '#answer-' . $ans->ID ); ?>" class="qa-profile-a-row">
            <div class="qa-profile-a-main">
              <?php if ( $a_accepted ) : ?>
                <span class="qa-status-accepted qa-profile-accepted-badge">✓ Accepted</span>
              <?php endif; ?>
              <span class="qa-profile-a-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $ans->post_content ), 20, '…' ) ); ?></span>
              <span class="qa-profile-a-question">on: <?php echo esc_html( $parent_q->post_title ); ?></span>
            </div>
            <div class="qa-profile-a-meta">
              <span class="qa-profile-q-stat"><?php echo esc_html( $a_votes ); ?> votes</span>
              <span class="qa-meta-time"><?php echo esc_html( human_time_diff( get_post_time( 'U', false, $ans ), time() ) . ' ago' ); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if ( $a_count > 15 ) : ?>
          <p class="qa-profile-more">Showing 15 most recent of <?php echo esc_html( $a_count ); ?> answers.</p>
        <?php endif; ?>
      <?php else : ?>
        <p class="qa-profile-empty">No answers yet.</p>
      <?php endif; ?>
    </section>

    <div class="qa-toast" id="qa-toast" role="status" aria-live="polite" hidden></div>

  </div><!-- /.page-wrap -->

<?php if ( $show_lb && 'below' === $lb_position ) : ?>
  <div class="cc-qa-lb-stacked cc-qa-lb-below">
    <?php echo $lb_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </div>
<?php endif; ?>

<?php if ( $show_lb && $lb_is_sidebar ) : ?>
  </div><!-- /.cc-qa-layout-main -->
  <aside class="cc-qa-layout-sidebar">
    <?php echo $lb_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </aside>
</div><!-- /.cc-qa-layout-wrap -->
<?php endif; ?>

</main>

<?php get_footer(); ?>
