<?php
/**
 * Single Question Template
 *
 * Loaded for /questions/question-slug/ URLs.
 * Registered via the theme_page_templates / template_include filter.
 *
 * Each question gets its own canonical URL — clean, keyword-rich,
 * fully server-rendered for Google and AI crawlers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue assets manually since we're bypassing the shortcode check
wp_enqueue_style(  'cc-qa-style',  CC_QA_URL . 'assets/css/cc-questions.css',  array(), CC_QA_VERSION );
wp_enqueue_script( 'cc-qa-script', CC_QA_URL . 'assets/js/cc-questions.js', array(), CC_QA_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
// Use shared config so strings / nonce / login_url stay in one place
wp_localize_script( 'cc-qa-script', 'CC_QA', cc_qa_js_config( get_permalink() ) );

// Gather all data before any output
$question_id = get_the_ID();
$question    = get_post( $question_id );
$user_id     = get_current_user_id();
$author      = CC_QA_Shortcode::get_author_display( $question->post_author );
$votes       = (int) get_post_meta( $question_id, '_cc_qa_votes',          true );
$a_count     = (int) get_post_meta( $question_id, '_cc_qa_answer_count',   true );
$accepted_id = (int) get_post_meta( $question_id, '_cc_qa_accepted_answer', true );
$user_voted  = $user_id ? CC_QA_Database::user_voted( $question_id, $user_id ) : false;
$is_author   = $user_id && (int) $question->post_author === $user_id;
$topics      = wp_get_object_terms( $question_id, 'cc_question_topic' );
$browse_url  = get_post_type_archive_link( 'cc_question' );

// Fetch answers: first page only (paginated via Ajax)
$answers_per_page  = (int) CC_QA_Admin::get( 'cc_qa_answers_per_page' );
$initial_answer_q  = new WP_Query( array(
    'post_type'      => 'cc_answer',
    'post_parent'    => $question_id,
    'post_status'    => 'publish',
    'posts_per_page' => $answers_per_page,
    'paged'          => 1,
    'meta_key'       => '_cc_qa_votes',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
) );
$answers            = $initial_answer_q->posts;
$answers_max_pages  = $initial_answer_q->max_num_pages;
// Sort accepted answer to the top within this page
usort( $answers, function( $a, $b ) {
    $a_acc = (int) get_post_meta( $a->ID, '_cc_qa_accepted', true );
    $b_acc = (int) get_post_meta( $b->ID, '_cc_qa_accepted', true );
    return $b_acc - $a_acc;
} );

// ── JSON-LD Schema (QAPage) ───────────────────────────────────
$schema_answers = array();
foreach ( $answers as $a ) {
    $a_author = CC_QA_Shortcode::get_author_display( $a->post_author );
    $entry    = array(
        '@type'       => 'Answer',
        'text'        => wp_strip_all_tags( $a->post_content ),
        'dateCreated' => get_the_date( 'c', $a ),
        'upvoteCount' => (int) get_post_meta( $a->ID, '_cc_qa_votes', true ),
        'url'         => get_permalink( $question_id ) . '#answer-' . $a->ID,
        'author'      => array(
            '@type' => 'Person',
            'name'  => $a_author->name,
        ),
    );
    $schema_answers[] = $entry;
}

$schema = array(
    '@context'   => 'https://schema.org',
    '@type'      => 'QAPage',
    'name'       => get_the_title( $question_id ),
    'url'        => get_permalink( $question_id ),
    'mainEntity' => array(
        '@type'         => 'Question',
        'name'          => get_the_title( $question_id ),
        'text'          => wp_strip_all_tags( $question->post_content ),
        'dateCreated'   => get_the_date( 'c', $question ),
        'answerCount'   => $a_count,
        'upvoteCount'   => $votes,
        'url'           => get_permalink( $question_id ),
        'author'        => array(
            '@type' => 'Person',
            'name'  => $author->name,
        ),
        'suggestedAnswer' => $schema_answers,
    ),
);

// Mark accepted answer in schema
if ( $accepted_id ) {
    foreach ( $schema_answers as $sa ) {
        if ( strpos( $sa['url'], '#answer-' . $accepted_id ) !== false ) {
            $schema['mainEntity']['acceptedAnswer'] = $sa;
            break;
        }
    }
}

// Output schema in <head> via wp_head
add_action( 'wp_head', function() use ( $schema ) {
    echo '<script type="application/ld+json">'
        . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>' . "\n";
} );

// ── SEO meta overrides ────────────────────────────────────────
// Override the page <title> to be "Question title - Site Name"
add_filter( 'pre_get_document_title', function() use ( $question ) {
    return esc_html( $question->post_title ) . ' - ' . get_bloginfo( 'name' );
} );

// Override meta description with a rich snippet
add_action( 'wp_head', function() use ( $question, $a_count, $votes ) {
    $desc = wp_trim_words( wp_strip_all_tags( $question->post_content ), 25, '…' );
    if ( ! $desc ) {
        $desc = esc_html( $question->post_title );
    }
    $desc .= ' · ' . $a_count . ' ' . _n( 'answer', 'answers', $a_count, 'cc-qa' ) . ' · ' . $votes . ' votes';
    echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url( get_permalink( $question->ID ) ) . '">' . "\n";
}, 1 );

// ── Leaderboard position (same setting as archive/shortcode pages) ──
$lb_position = CC_QA_Admin::get( 'cc_qa_leaderboard_position' );
$show_lb     = $lb_position && $lb_position !== 'none';

// Load the theme's header (uses Blocksy's full header/nav)
get_header();
?>

<main id="main" class="cc-qa-single-page">

<?php if ( $show_lb && in_array( $lb_position, array( 'sidebar-right', 'sidebar-left' ), true ) ) :
      $sticky_class = CC_QA_Admin::get( 'cc_qa_sidebar_sticky' ) ? '' : ' cc-qa-sidebar-no-sticky';
?>
  <div class="cc-qa-layout-wrap cc-qa-layout-<?php echo esc_attr( $lb_position . $sticky_class ); ?>">
    <div class="cc-qa-layout-main">
<?php endif; ?>

<?php if ( $show_lb && 'above' === $lb_position ) : ?>
  <div class="cc-qa-lb-stacked cc-qa-lb-above">
    <?php echo CC_QA_Leaderboard::render_inline( 0, 'stacked' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>
<?php endif; ?>

  <div class="page-wrap cc-qa-wrap cc-qa-single" id="cc-qa-app">

    <!-- ── Back link ── -->
    <a href="<?php echo esc_url( $browse_url ); ?>" class="qa-back-link">← Back to all questions</a>

    <!-- ── Question Detail ── -->
    <article class="qa-question-detail"
             itemscope itemtype="https://schema.org/Question"
             id="question-<?php echo esc_attr( $question_id ); ?>">

      <meta itemprop="name"        content="<?php echo esc_attr( $question->post_title ); ?>">
      <meta itemprop="dateCreated" content="<?php echo esc_attr( get_the_date( 'c', $question ) ); ?>">
      <meta itemprop="answerCount" content="<?php echo esc_attr( $a_count ); ?>">
      <meta itemprop="upvoteCount" content="<?php echo esc_attr( $votes ); ?>">

      <div class="qa-detail-layout">

        <!-- Vote Column -->
        <div class="qa-vote-col">
          <button class="qa-vote-btn qa-vote-up <?php echo $user_voted ? 'voted' : ''; ?>"
                  data-post-id="<?php echo esc_attr( $question_id ); ?>"
                  data-vote="1"
                  aria-label="Upvote question"
                  <?php echo ( ! is_user_logged_in() || $is_author ) ? 'disabled' : ''; ?>>▲</button>
          <span class="qa-vote-count" id="votes-<?php echo esc_attr( $question_id ); ?>"><?php echo esc_html( $votes ); ?></span>
          <button class="qa-vote-btn qa-vote-down"
                  data-post-id="<?php echo esc_attr( $question_id ); ?>"
                  data-vote="-1"
                  aria-label="Downvote question"
                  <?php echo ( ! is_user_logged_in() || $is_author ) ? 'disabled' : ''; ?>>▼</button>
        </div>

        <!-- Content -->
        <div class="qa-detail-content">

          <?php if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) : ?>
            <div class="qa-detail-topics">
              <?php foreach ( $topics as $t ) : ?>
                <a href="<?php echo esc_url( get_term_link( $t ) ); ?>" class="qa-topic-badge">
                  <?php echo esc_html( $t->name ); ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- qa-detail-editable wraps title + body. JS toggles the whole div,
               so edit form replaces it cleanly on both this page and in card view. -->
          <div class="qa-detail-editable">

            <h1 class="qa-detail-title" itemprop="text">
              <?php echo esc_html( $question->post_title ); ?>
            </h1>

            <?php if ( $question->post_content ) : ?>
              <div class="qa-detail-body" itemprop="description">
                <?php echo nl2br( esc_html( $question->post_content ) ); ?>
              </div>
            <?php endif; ?>

          </div><!-- /.qa-detail-editable -->

          <?php
          $q_perms = CC_QA_Shortcode::get_edit_permissions( $question );
          if ( $q_perms['can_edit'] ) : ?>
          <!-- Inline edit form (hidden by default, shown when Edit is clicked) -->
          <div class="qa-inline-edit" id="edit-question-<?php echo esc_attr( $question_id ); ?>" hidden>
            <input type="text" class="qa-input qa-edit-title"
                   value="<?php echo esc_attr( $question->post_title ); ?>"
                   maxlength="<?php echo esc_attr( CC_QA_Admin::get( 'cc_qa_question_title_max' ) ); ?>"
                   data-post-id="<?php echo esc_attr( $question_id ); ?>"
                   data-type="question" />
            <textarea class="qa-textarea qa-edit-body" rows="5"
                      data-post-id="<?php echo esc_attr( $question_id ); ?>"
                      data-type="question"><?php echo esc_textarea( $question->post_content ); ?></textarea>
            <div class="qa-edit-actions">
              <button class="btn-qa-primary qa-save-edit-btn"
                      data-post-id="<?php echo esc_attr( $question_id ); ?>"
                      data-type="question">Save changes</button>
              <button class="btn-qa-ghost qa-cancel-edit-btn"
                      data-post-id="<?php echo esc_attr( $question_id ); ?>"
                      data-type="question">Cancel</button>
            </div>
          </div>
          <?php endif; ?>

          <div class="qa-detail-meta">
            <div class="qa-meta-author"
                 itemprop="author" itemscope itemtype="https://schema.org/Person">
              <div class="qa-avatar qa-avatar-sm">
                <?php echo esc_html( $author->initial ); ?>
              </div>
              <?php if ( $author->profile_url ) : ?>
                <a href="<?php echo esc_url( $author->profile_url ); ?>" class="qa-author-link" itemprop="name"><?php echo esc_html( $author->name ); ?></a>
              <?php else : ?>
                <span itemprop="name"><?php echo esc_html( $author->name ); ?></span>
              <?php endif; ?>
            </div>
            <span class="qa-meta-sep">·</span>
            <time class="qa-meta-date" datetime="<?php echo esc_attr( get_the_date( 'c', $question ) ); ?>"
                  itemprop="dateCreated">
              <?php echo esc_html( human_time_diff( get_post_time( 'U', false, $question ), time() ) . ' ago' ); ?>
            </time>
            <?php if ( (int) get_post_meta( $question_id, '_cc_qa_accepted', true ) ) : ?>
              <span class="qa-status-accepted">✓ Answered</span>
            <?php endif; ?>
            <?php if ( $q_perms['can_edit'] ) : ?>
              <button class="qa-edit-btn"
                      data-post-id="<?php echo esc_attr( $question_id ); ?>"
                      data-type="question"
                      title="<?php echo esc_attr( $q_perms['mins_remaining'] > 0 ? $q_perms['mins_remaining'] . ' min left to edit' : '' ); ?>">Edit</button>
            <?php endif; ?>
            <?php if ( $q_perms['can_delete'] ) : ?>
              <button class="qa-delete-btn"
                      data-action="delete_question"
                      data-post-id="<?php echo esc_attr( $question_id ); ?>"
                      data-redirect="<?php echo esc_url( $browse_url ); ?>">Delete</button>
            <?php endif; ?>
          </div>

        </div><!-- /.qa-detail-content -->
      </div><!-- /.qa-detail-layout -->
    </article>

    <!-- ── Answers ── -->
    <section class="qa-answers-section" aria-label="Answers">

      <div class="qa-answers-header">
        <h2 class="qa-answers-title">
          <span id="answer-count-<?php echo esc_attr( $question_id ); ?>"><?php echo esc_html( $a_count ); ?></span>
          <?php echo $a_count === 1 ? 'Answer' : 'Answers'; ?>
        </h2>
        <?php if ( $a_count > 1 ) : ?>
        <div class="qa-answer-sort">
          <button class="qa-sort-tab active" data-answer-sort="votes"   data-question="<?php echo esc_attr( $question_id ); ?>">Best</button>
          <button class="qa-sort-tab"        data-answer-sort="newest"  data-question="<?php echo esc_attr( $question_id ); ?>">Newest</button>
        </div>
        <?php endif; ?>
      </div>

      <div class="qa-answers-list" id="answers-list-<?php echo esc_attr( $question_id ); ?>">
        <?php
        if ( ! empty( $answers ) ) {
            foreach ( $answers as $answer ) {
                CC_QA_Shortcode::render_answer_card( $answer, $user_id, $is_author, $accepted_id );
            }
        } else {
            echo '<div class="qa-no-answers"><span>No answers yet.</span> <strong>Be the first!</strong></div>';
        }
        ?>
      </div>

      <?php if ( $answers_max_pages > 1 ) : ?>
      <div class="qa-load-more-wrap" id="qa-load-more-answers-wrap">
        <button class="btn-qa-load-more" id="qa-load-more-answers"
                data-question="<?php echo esc_attr( $question_id ); ?>"
                data-page="1"
                data-max="<?php echo esc_attr( $answers_max_pages ); ?>"
                data-sort="votes">
          Load more answers
        </button>
      </div>
      <?php endif; ?>

    </section>

    <!-- ── Post Answer ── -->
    <div class="qa-post-answer-section" id="qa-post-answer">
      <h3 class="qa-post-answer-title">Your Answer</h3>
      <?php if ( is_user_logged_in() ) : ?>
        <div class="qa-field">
          <textarea id="qa-answer-body" class="qa-textarea qa-answer-textarea" rows="6"
                    placeholder="Share what you know. Be specific, accurate, and helpful. Cite sources where relevant…"></textarea>
        </div>
        <div class="qa-form-actions">
          <button class="btn-qa-primary" id="qa-submit-answer"
                  data-question="<?php echo esc_attr( $question_id ); ?>">
            Post Your Answer
          </button>
        </div>
      <?php else : ?>
        <div class="qa-login-nudge">
          <span class="qa-login-nudge-text">Log in to post an answer.</span>
          <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="btn-qa-primary">Log In</a>
          <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn-qa-ghost">Join Free</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Toast ── -->
    <div class="qa-toast" id="qa-toast" role="status" aria-live="polite" hidden></div>

  </div><!-- /.page-wrap -->

<?php if ( $show_lb && 'below' === $lb_position ) : ?>
  <div class="cc-qa-lb-stacked cc-qa-lb-below page-wrap">
    <?php echo CC_QA_Leaderboard::render_inline( 0, 'stacked' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>
<?php endif; ?>

<?php if ( $show_lb && in_array( $lb_position, array( 'sidebar-right', 'sidebar-left' ), true ) ) : ?>
    </div><!-- /.cc-qa-layout-main -->
    <aside class="cc-qa-layout-sidebar">
      <?php echo CC_QA_Leaderboard::render_inline( 0, 'compact' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </aside>
  </div><!-- /.cc-qa-layout-wrap -->
<?php endif; ?>

</main>

<?php get_footer(); ?>
