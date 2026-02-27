<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Shortcode' ) ) return;

class CC_QA_Shortcode {

    public static function init() {
        add_shortcode( 'cc_qa', array( __CLASS__, 'render' ) );
    }

    /* ── Shortcode entry point ── */
    public static function render( $atts ) {
        return self::render_list_view();
    }

    /* ── Build WP_Query args (reused by Ajax load-more) ── */
    public static function build_question_query( $page = 1, $topic = '', $sort = 'newest', $search = '' ) {
        $args = array(
            'post_type'      => 'cc_question',
            'post_status'    => 'publish',
            'posts_per_page' => (int) CC_QA_Admin::get( 'cc_qa_questions_per_page' ),
            'paged'          => $page,
        );

        if ( $sort === 'votes' ) {
            $args['meta_key'] = '_cc_qa_votes';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
        } elseif ( $sort === 'answers' ) {
            $args['meta_key'] = '_cc_qa_answer_count';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
        } elseif ( $sort === 'unanswered' ) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_cc_qa_answer_count',
                    'value'   => '0',
                    'compare' => '=',
                ),
            );
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        }

        if ( $topic ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'cc_question_topic',
                    'field'    => 'slug',
                    'terms'    => $topic,
                ),
            );
        }

        if ( $search ) {
            $args['s'] = $search;
        }

        return $args;
    }

    /* ── Question List / Browse View ─────────────────────────────
     * Public so the archive template can call it directly.
     * Also used by the [cc_qa] shortcode.
     * ────────────────────────────────────────────────────────── */
    public static function render_list_view() {
        $user_id  = get_current_user_id();
        $topics   = get_terms( array( 'taxonomy' => 'cc_question_topic', 'hide_empty' => false ) );
        $q_count  = wp_count_posts( 'cc_question' )->publish ?? 0;
        $a_count  = wp_count_posts( 'cc_answer' )->publish ?? 0;
        $query    = new WP_Query( self::build_question_query() );

        // Count unanswered questions for the hero stat
        $unanswered_count = (int) ( new WP_Query( array(
            'post_type'      => 'cc_question',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array( array(
                'key'     => '_cc_qa_answer_count',
                'value'   => '0',
                'compare' => '=',
            ) ),
        ) ) )->found_posts;

        // Admin-controlled heading / subtitle
        $heading  = CC_QA_Admin::get( 'cc_qa_archive_title' )    ?: 'Community Q&A';
        $subtitle = CC_QA_Admin::get( 'cc_qa_archive_subtitle' ) ?: 'Ask questions and get answers from the community.';

        // Leaderboard position setting
        $lb_position = CC_QA_Admin::get( 'cc_qa_leaderboard_position' );
        $show_lb     = ( 'none' !== $lb_position );

        ob_start();
        ?>
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

        <div class="cc-qa-wrap" id="cc-qa-app" data-page="1">

          <!-- ── Page Header ── -->
          <div class="qa-page-header">
            <div>
              <h1 class="qa-page-title"><?php echo esc_html( $heading ); ?></h1>
              <p class="qa-page-sub"><?php echo esc_html( $subtitle ); ?></p>
            </div>
            <div class="qa-header-stats">
              <a href="#qa-question-feed" class="qa-stat qa-stat-link">
                <span class="qa-stat-num"><?php echo number_format( $q_count ); ?></span>
                <span class="qa-stat-label">Total Questions</span>
              </a>
              <a href="#qa-question-feed" class="qa-stat qa-stat-link qa-stat-unanswered" data-filter-unanswered="1">
                <span class="qa-stat-num"><?php echo number_format( $unanswered_count ); ?></span>
                <span class="qa-stat-label">Unanswered</span>
              </a>
            </div>
          </div>

          <!-- ── Ask Question Form ── -->
          <div class="qa-ask-panel" id="qa-ask-panel">
            <?php if ( is_user_logged_in() ) : ?>
              <div class="qa-ask-trigger" id="qa-ask-trigger">
                <?php $cu = self::get_author_display( get_current_user_id() ); ?>
                <div class="qa-ask-avatar"><?php echo esc_html( $cu->initial ); ?></div>
                <div class="qa-ask-placeholder">Have a question? Ask the community…</div>
                <button class="btn-qa-ask">Ask Question</button>
              </div>
              <div class="qa-ask-form" id="qa-ask-form" hidden>
                <div class="qa-form-header">
                  <h3 class="qa-form-title">Ask the Community</h3>
                  <button class="qa-form-close" id="qa-ask-close" aria-label="Close form">✕</button>
                </div>
                <div class="qa-field">
                  <label class="qa-label" for="qa-question-title">Your Question <span class="qa-required">*</span></label>
                  <input type="text" id="qa-question-title" class="qa-input" placeholder="e.g. What's the best AI tool for writing YouTube scripts?" maxlength="200" />
                  <span class="qa-char-count" id="qa-title-count">0 / 200</span>
                </div>
                <div class="qa-field">
                  <label class="qa-label" for="qa-question-body">More detail <span class="qa-hint">(optional but helps get better answers)</span></label>
                  <textarea id="qa-question-body" class="qa-textarea" rows="4" placeholder="Add any context, what you've already tried, your use case…"></textarea>
                </div>
                <?php if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) : ?>
                <div class="qa-field">
                  <label class="qa-label">Topic</label>
                  <div class="qa-topic-pills" id="qa-topic-select">
                    <?php foreach ( $topics as $topic ) : ?>
                      <button type="button" class="qa-topic-pill" data-value="<?php echo esc_attr( $topic->term_id ); ?>">
                        <?php echo esc_html( $topic->name ); ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
                <div class="qa-form-actions">
                  <button class="btn-qa-primary" id="qa-submit-question">Post Question</button>
                  <button class="btn-qa-ghost" id="qa-cancel-question">Cancel</button>
                </div>
              </div>
            <?php else : ?>
              <div class="qa-login-nudge">
                <span class="qa-login-nudge-text">Join the community to ask questions and share answers.</span>
                <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn-qa-primary">Join Free</a>
                <a href="<?php echo esc_url( wp_login_url( get_post_type_archive_link( 'cc_question' ) ) ); ?>" class="btn-qa-ghost">Log In</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- ── Filters Bar ── -->
          <div class="qa-filters-bar">
            <div class="qa-sort-tabs" role="tablist">
              <button class="qa-sort-tab active" role="tab" data-sort="newest">Newest</button>
              <button class="qa-sort-tab" role="tab" data-sort="votes">Top Voted</button>
              <button class="qa-sort-tab" role="tab" data-sort="answers">Most Answered</button>
              <button class="qa-sort-tab" role="tab" data-sort="unanswered">Unanswered</button>
            </div>
            <div class="qa-search-wrap">
              <input type="search" class="qa-search-input" id="qa-search" placeholder="Search questions…" />
            </div>
          </div>

          <?php if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) : ?>
          <div class="qa-topic-filter-bar">
            <button class="qa-topic-filter active" data-topic="">All Topics</button>
            <?php foreach ( $topics as $topic ) : ?>
              <button class="qa-topic-filter" data-topic="<?php echo esc_attr( $topic->slug ); ?>">
                <?php echo esc_html( $topic->name ); ?>
                <span class="qa-topic-count"><?php echo (int) $topic->count; ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- ── Question Feed ── -->
          <div class="qa-question-feed" id="qa-question-feed">
            <?php
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    self::render_question_card( get_post() );
                }
                wp_reset_postdata();
            } else {
                echo '<div class="qa-empty"><span class="qa-empty-icon">💬</span><p>No questions yet. Be the first to ask!</p></div>';
            }
            ?>
          </div>

          <?php if ( $query->max_num_pages > 1 ) : ?>
          <div class="qa-load-more-wrap" id="qa-load-more-wrap">
            <button class="btn-qa-load-more" id="qa-load-more" data-page="1" data-max="<?php echo (int) $query->max_num_pages; ?>">
              Load more questions
            </button>
          </div>
          <?php endif; ?>

          <div class="qa-toast" id="qa-toast" role="status" aria-live="polite" hidden></div>

        </div>

        <?php if ( $show_lb && 'below' === $lb_position ) : ?>
          <div class="cc-qa-lb-stacked cc-qa-lb-below">
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

        <?php if ( CC_QA_Admin::get( 'cc_qa_footer_credit' ) ) : ?>
        <p class="qa-powered-by">
          Powered by <a href="https://creatorconnected.com/questions/" target="_blank" rel="noopener">ccQuestions</a>
        </p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /* ── Question Card (used in list view and Ajax load-more) ── */
    public static function render_question_card( $post ) {
        $user_id    = get_current_user_id();
        $author     = self::get_author_display( $post->post_author );
        $votes      = (int) get_post_meta( $post->ID, '_cc_qa_votes', true );
        $a_count    = (int) get_post_meta( $post->ID, '_cc_qa_answer_count', true );
        $accepted   = (bool) get_post_meta( $post->ID, '_cc_qa_accepted', true );
        $user_voted = $user_id ? CC_QA_Database::user_voted( $post->ID, $user_id ) : false;
        $is_author  = $user_id && (int) $post->post_author === $user_id;
        $topics     = wp_get_object_terms( $post->ID, 'cc_question_topic' );
        $q_url      = get_permalink( $post->ID );
        $perms      = self::get_edit_permissions( $post );
        ?>
        <div class="qa-question-card <?php echo $accepted ? 'qa-card-answered' : ''; ?>"
             id="question-<?php echo esc_attr( $post->ID ); ?>"
             itemscope itemtype="https://schema.org/Question">
          <meta itemprop="name"        content="<?php echo esc_attr( $post->post_title ); ?>">
          <meta itemprop="dateCreated" content="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
          <meta itemprop="answerCount" content="<?php echo esc_attr( $a_count ); ?>">
          <meta itemprop="url"         content="<?php echo esc_attr( $q_url ); ?>">

          <div class="qa-card-votes">
            <button class="qa-vote-btn qa-vote-up <?php echo $user_voted ? 'voted' : ''; ?>"
                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                    data-vote="1"
                    aria-label="Upvote"
                    <?php echo ( ! $user_id || $is_author ) ? 'disabled' : ''; ?>>▲</button>
            <span class="qa-vote-count" id="votes-<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $votes ); ?></span>
            <button class="qa-vote-btn qa-vote-down"
                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                    data-vote="-1"
                    aria-label="Downvote"
                    <?php echo ( ! $user_id || $is_author ) ? 'disabled' : ''; ?>>▼</button>
          </div>

          <div class="qa-card-answer-count <?php echo $accepted ? 'answered' : ( $a_count > 0 ? 'has-answers' : '' ); ?>">
            <span class="qa-answer-num" id="answer-count-<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $a_count ); ?></span>
            <span class="qa-answer-label"><?php echo $a_count === 1 ? 'answer' : 'answers'; ?></span>
            <?php if ( $accepted ) : ?><span class="qa-accepted-check">✓</span><?php endif; ?>
          </div>

          <div class="qa-card-body">
            <?php if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) : ?>
              <div class="qa-card-topics">
                <?php foreach ( $topics as $t ) : ?>
                  <button type="button" class="qa-topic-badge qa-topic-filter-link"
                          data-topic="<?php echo esc_attr( $t->slug ); ?>"
                          title="Filter by <?php echo esc_attr( $t->name ); ?>">
                    <?php echo esc_html( $t->name ); ?>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <h3 class="qa-card-title" itemprop="text">
              <a href="<?php echo esc_url( $q_url ); ?>" class="qa-card-link">
                <?php echo esc_html( $post->post_title ); ?>
              </a>
            </h3>

            <!-- Inline edit form (hidden by default) -->
            <?php if ( $perms['can_edit'] ) : ?>
            <div class="qa-inline-edit" id="edit-question-<?php echo esc_attr( $post->ID ); ?>" hidden>
              <input type="text" class="qa-input qa-edit-title"
                     value="<?php echo esc_attr( $post->post_title ); ?>"
                     maxlength="<?php echo esc_attr( CC_QA_Admin::get( 'cc_qa_question_title_max' ) ); ?>"
                     data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                     data-type="question" />
              <textarea class="qa-textarea qa-edit-body" rows="3"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        data-type="question"><?php echo esc_textarea( $post->post_content ); ?></textarea>
              <div class="qa-edit-actions">
                <button class="btn-qa-primary qa-save-edit-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        data-type="question">Save</button>
                <button class="btn-qa-ghost qa-cancel-edit-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        data-type="question">Cancel</button>
              </div>
            </div>
            <?php endif; ?>

            <?php if ( $post->post_content ) : ?>
              <p class="qa-card-excerpt qa-display-content">
                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' ) ); ?>
              </p>
            <?php endif; ?>

            <div class="qa-card-meta">
              <div class="qa-meta-author"
                   itemprop="author" itemscope itemtype="https://schema.org/Person">
                <div class="qa-avatar qa-avatar-xs">
                  <?php echo esc_html( $author->initial ); ?>
                </div>
                <?php if ( $author->profile_url ) : ?>
                  <a href="<?php echo esc_url( $author->profile_url ); ?>" class="qa-author-link" itemprop="name"><?php echo esc_html( $author->name ); ?></a>
                <?php else : ?>
                  <span itemprop="name"><?php echo esc_html( $author->name ); ?></span>
                <?php endif; ?>
              </div>
              <span class="qa-meta-dot">·</span>
              <time class="qa-meta-time" datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
                <?php echo esc_html( human_time_diff( get_post_time( 'U', false, $post ), time() ) . ' ago' ); ?>
              </time>
              <?php if ( $perms['can_edit'] ) : ?>
                <button class="qa-edit-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        data-type="question"
                        title="<?php echo esc_attr( $perms['mins_remaining'] > 0 ? $perms['mins_remaining'] . ' min left to edit' : '' ); ?>">Edit</button>
              <?php endif; ?>
              <?php if ( $perms['can_delete'] ) : ?>
                <button class="qa-delete-btn" data-action="delete_question"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">Delete</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }

    /* ── Answer Card ─────────────────────────────────────────────
     * Called from the single question template and Ajax.
     * ────────────────────────────────────────────────────────── */
    public static function render_answer_card( $post, $current_user_id = 0, $is_question_author = false, $accepted_id = 0 ) {
        $author     = self::get_author_display( $post->post_author );
        $votes      = (int) get_post_meta( $post->ID, '_cc_qa_votes', true );
        $accepted   = (bool) get_post_meta( $post->ID, '_cc_qa_accepted', true );
        $user_voted = $current_user_id ? CC_QA_Database::user_voted( $post->ID, $current_user_id ) : false;
        $is_author  = $current_user_id && (int) $post->post_author === $current_user_id;
        $replies    = get_post_meta( $post->ID, '_cc_qa_replies', true ) ?: array();
        $perms      = self::get_edit_permissions( $post );

        $can_accept = $is_question_author
                      && ! $accepted
                      && ! $is_author
                      && $current_user_id;
        ?>
        <div class="qa-answer-card <?php echo $accepted ? 'qa-answer-accepted' : ''; ?>"
             id="answer-<?php echo esc_attr( $post->ID ); ?>"
             itemscope itemtype="https://schema.org/Answer">
          <meta itemprop="dateCreated" content="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
          <meta itemprop="upvoteCount" content="<?php echo esc_attr( $votes ); ?>">

          <?php if ( $accepted ) : ?>
            <div class="qa-accepted-banner">✓ Accepted Answer</div>
          <?php endif; ?>

          <div class="qa-answer-layout">
            <div class="qa-vote-col">
              <button class="qa-vote-btn qa-vote-up <?php echo $user_voted ? 'voted' : ''; ?>"
                      data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                      data-vote="1"
                      aria-label="Upvote answer"
                      <?php echo ( ! $current_user_id || $is_author ) ? 'disabled' : ''; ?>>▲</button>
              <span class="qa-vote-count" id="votes-<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $votes ); ?></span>
              <button class="qa-vote-btn qa-vote-down"
                      data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                      data-vote="-1"
                      aria-label="Downvote answer"
                      <?php echo ( ! $current_user_id || $is_author ) ? 'disabled' : ''; ?>>▼</button>
            </div>

            <div class="qa-answer-content" itemprop="text">

              <!-- Inline edit form (hidden by default) -->
              <?php if ( $perms['can_edit'] ) : ?>
              <div class="qa-inline-edit" id="edit-answer-<?php echo esc_attr( $post->ID ); ?>" hidden>
                <textarea class="qa-textarea qa-edit-body" rows="5"
                          data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                          data-type="answer"><?php echo esc_textarea( $post->post_content ); ?></textarea>
                <div class="qa-edit-actions">
                  <button class="btn-qa-primary qa-save-edit-btn"
                          data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                          data-type="answer">Save</button>
                  <button class="btn-qa-ghost qa-cancel-edit-btn"
                          data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                          data-type="answer">Cancel</button>
                </div>
              </div>
              <?php endif; ?>

              <div class="qa-display-content"><?php echo nl2br( esc_html( $post->post_content ) ); ?></div>

              <div class="qa-answer-meta">
                <div class="qa-meta-author"
                     itemprop="author" itemscope itemtype="https://schema.org/Person">
                  <div class="qa-avatar qa-avatar-xs">
                    <?php echo esc_html( $author->initial ); ?>
                  </div>
                  <?php if ( $author->profile_url ) : ?>
                    <a href="<?php echo esc_url( $author->profile_url ); ?>" class="qa-author-link" itemprop="name"><?php echo esc_html( $author->name ); ?></a>
                  <?php else : ?>
                    <span itemprop="name"><?php echo esc_html( $author->name ); ?></span>
                  <?php endif; ?>
                </div>
                <span class="qa-meta-dot">·</span>
                <time class="qa-meta-time" datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
                  <?php echo esc_html( human_time_diff( get_post_time( 'U', false, $post ), time() ) . ' ago' ); ?>
                </time>

                <div class="qa-answer-actions">
                  <?php if ( $can_accept ) : ?>
                    <button class="qa-accept-btn" data-answer-id="<?php echo esc_attr( $post->ID ); ?>">
                      ✓ Accept Answer
                    </button>
                  <?php endif; ?>
                  <?php if ( $accepted ) : ?>
                    <span class="qa-accepted-label">✓ Accepted</span>
                  <?php endif; ?>
                  <?php if ( is_user_logged_in() ) : ?>
                    <button class="qa-reply-toggle" data-answer-id="<?php echo esc_attr( $post->ID ); ?>">
                      Reply <?php if ( ! empty( $replies ) ) : ?><span class="qa-reply-count"><?php echo count( $replies ); ?></span><?php endif; ?>
                    </button>
                  <?php endif; ?>
                  <?php if ( $perms['can_edit'] ) : ?>
                    <button class="qa-edit-btn"
                            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                            data-type="answer"
                            title="<?php echo esc_attr( $perms['mins_remaining'] > 0 ? $perms['mins_remaining'] . ' min left to edit' : '' ); ?>">Edit</button>
                  <?php endif; ?>
                  <?php if ( $perms['can_delete'] ) : ?>
                    <button class="qa-delete-btn" data-action="delete_answer"
                            data-answer-id="<?php echo esc_attr( $post->ID ); ?>">Delete</button>
                  <?php endif; ?>
                </div>
              </div>

              <!-- ── Replies ── -->
              <div class="qa-replies-section" id="replies-<?php echo esc_attr( $post->ID ); ?>">
                <?php if ( ! empty( $replies ) ) : ?>
                  <div class="qa-replies-list">
                    <?php foreach ( $replies as $rid => $reply ) :
                        self::render_reply( $rid, $reply, $post->ID, $current_user_id );
                    endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if ( is_user_logged_in() ) : ?>
                  <div class="qa-reply-form" id="reply-form-<?php echo esc_attr( $post->ID ); ?>" hidden>
                    <div class="qa-reply-input-wrap">
                      <?php $cu = self::get_author_display( $current_user_id ); ?>
                      <div class="qa-avatar qa-avatar-xs"><?php echo esc_html( $cu->initial ); ?></div>
                      <textarea class="qa-reply-input" rows="2"
                                placeholder="Write a reply…"
                                data-answer-id="<?php echo esc_attr( $post->ID ); ?>"></textarea>
                    </div>
                    <div class="qa-reply-actions">
                      <button class="btn-qa-reply-submit btn-qa-primary"
                              data-answer-id="<?php echo esc_attr( $post->ID ); ?>">Post Reply</button>
                      <button class="btn-qa-reply-cancel btn-qa-ghost"
                              data-answer-id="<?php echo esc_attr( $post->ID ); ?>">Cancel</button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>
        <?php
    }

    /* ── Single Reply Row ── */
    public static function render_reply( $reply_id, $reply, $answer_id, $current_user_id = 0 ) {
        $is_reply_author = $current_user_id && (int) $reply['user_id'] === $current_user_id;
        $is_admin        = current_user_can( 'delete_others_posts' );
        $initial         = strtoupper( substr( $reply['user_name'], 0, 1 ) ) ?: 'M';
        $time            = ! empty( $reply['created_at'] )
                           ? human_time_diff( strtotime( $reply['created_at'] ), time() ) . ' ago'
                           : 'just now';

        // 1-hour edit window for replies
        $can_edit_reply  = false;
        $reply_mins_left = 0;
        if ( $is_admin ) {
            $can_edit_reply = true;
        } elseif ( $is_reply_author && ! empty( $reply['created_at'] ) ) {
            $elapsed = time() - strtotime( $reply['created_at'] );
            if ( $elapsed < HOUR_IN_SECONDS ) {
                $can_edit_reply  = true;
                $reply_mins_left = (int) ceil( ( HOUR_IN_SECONDS - $elapsed ) / 60 );
            }
        }

        // Profile URL for the reply author
        $reply_profile_url = ! empty( $reply['user_id'] ) ? CC_QA_Badges::profile_url( (int) $reply['user_id'] ) : '';
        ?>
        <div class="qa-reply" id="reply-<?php echo esc_attr( $reply_id ); ?>"
             data-answer-id="<?php echo esc_attr( $answer_id ); ?>">
          <div class="qa-avatar qa-avatar-xs qa-avatar-reply"><?php echo esc_html( $initial ); ?></div>
          <div class="qa-reply-body">
            <div class="qa-reply-header">
              <?php if ( $reply_profile_url ) : ?>
                <a href="<?php echo esc_url( $reply_profile_url ); ?>" class="qa-author-link qa-reply-author"><?php echo esc_html( $reply['user_name'] ); ?></a>
              <?php else : ?>
                <span class="qa-reply-author"><?php echo esc_html( $reply['user_name'] ); ?></span>
              <?php endif; ?>
              <span class="qa-meta-dot">·</span>
              <span class="qa-meta-time"><?php echo esc_html( $time ); ?></span>
              <?php if ( $can_edit_reply ) : ?>
                <button class="qa-edit-reply-btn"
                        data-reply-id="<?php echo esc_attr( $reply_id ); ?>"
                        data-answer-id="<?php echo esc_attr( $answer_id ); ?>"
                        <?php if ( $reply_mins_left > 0 ) : ?>
                        title="<?php echo esc_attr( $reply_mins_left . ' min left to edit' ); ?>"
                        <?php endif; ?>>Edit</button>
              <?php endif; ?>
              <?php if ( $is_reply_author || $is_admin ) : ?>
                <button class="qa-delete-reply-btn"
                        data-reply-id="<?php echo esc_attr( $reply_id ); ?>"
                        data-answer-id="<?php echo esc_attr( $answer_id ); ?>">Delete</button>
              <?php endif; ?>
            </div>
            <div class="qa-reply-content" id="reply-content-<?php echo esc_attr( $reply_id ); ?>">
              <?php echo nl2br( esc_html( $reply['content'] ) ); ?>
            </div>
            <?php if ( $can_edit_reply ) : ?>
            <div class="qa-reply-edit-form" id="reply-edit-<?php echo esc_attr( $reply_id ); ?>" hidden>
              <textarea class="qa-textarea qa-reply-edit-textarea" rows="2"
                        data-reply-id="<?php echo esc_attr( $reply_id ); ?>"
                        data-answer-id="<?php echo esc_attr( $answer_id ); ?>"><?php echo esc_textarea( $reply['content'] ); ?></textarea>
              <div class="qa-reply-edit-actions">
                <button class="btn-qa-primary qa-save-reply-edit-btn"
                        data-reply-id="<?php echo esc_attr( $reply_id ); ?>"
                        data-answer-id="<?php echo esc_attr( $answer_id ); ?>">Save</button>
                <button class="btn-qa-ghost qa-cancel-reply-edit-btn"
                        data-reply-id="<?php echo esc_attr( $reply_id ); ?>">Cancel</button>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }

    /* ── Author Display Helper ─────────────────────────────────────
     * Returns a simple object with `name`, `initial`, and `profile_url`.
     * Public so templates can call it directly.
     * ────────────────────────────────────────────────────────────── */
    public static function get_author_display( $user_id ) {
        $user = get_userdata( $user_id );

        if ( $user && ! empty( trim( $user->display_name ) ) ) {
            $name = $user->display_name;
        } elseif ( $user ) {
            $name = $user->user_login;
        } else {
            $name = 'Community Member';
        }

        return (object) array(
            'name'        => $name,
            'initial'     => strtoupper( mb_substr( $name, 0, 1 ) ) ?: 'M',
            'profile_url' => $user_id ? CC_QA_Badges::profile_url( $user_id ) : '',
        );
    }

    /**
     * Returns whether the current user can edit/delete a post.
     * Admins (delete_others_posts) can always. Regular authors have 1 hour.
     *
     * @param WP_Post|int $post
     * @return array { can_edit: bool, can_delete: bool, mins_remaining: int }
     */
    public static function get_edit_permissions( $post ) {
        $post    = is_int( $post ) ? get_post( $post ) : $post;
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return array( 'can_edit' => false, 'can_delete' => false, 'mins_remaining' => 0 );
        }

        // Admins / editors — always allowed
        if ( current_user_can( 'delete_others_posts' ) ) {
            return array( 'can_edit' => true, 'can_delete' => true, 'mins_remaining' => -1 );
        }

        // Not the author
        if ( (int) $post->post_author !== $user_id ) {
            return array( 'can_edit' => false, 'can_delete' => false, 'mins_remaining' => 0 );
        }

        $posted_at      = get_post_time( 'U', true, $post );
        $edit_window    = 60 * MINUTE_IN_SECONDS; // 1 hour
        $elapsed        = time() - $posted_at;
        $within_window  = $elapsed < $edit_window;
        $mins_remaining = $within_window ? (int) ceil( ( $edit_window - $elapsed ) / 60 ) : 0;

        return array(
            'can_edit'       => $within_window,
            'can_delete'     => $within_window,
            'mins_remaining' => $mins_remaining,
        );
    }
}
