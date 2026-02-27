<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'CC_QA_Schema' ) ) return;

/**
 * CC_QA_Schema
 *
 * JSON-LD structured data:
 *   - Single question pages  → QAPage (one Question per page — Google spec)
 *   - Archive / taxonomy     → CollectionPage
 *   - Shortcode page         → CollectionPage
 *   - Sitewide               → WebSite + SearchAction (Sitelinks Searchbox)
 *   - Sitewide               → Organization (brand authority / GEO entity)
 *
 * Additional SEO/GEO outputs:
 *   - Open Graph + Twitter Card on single question pages
 *   - BreadcrumbList on single question + archive pages
 *   - Speakable on single question pages (voice / AI GEO signal)
 *   - rel="prev" / rel="next" pagination on archive pages
 */
class CC_QA_Schema {

    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'output_website_schema'  ), 2 );
        add_action( 'wp_head', array( __CLASS__, 'output_org_schema'      ), 3 );
        add_action( 'wp_head', array( __CLASS__, 'output_single_schema'   ), 5 );
        add_action( 'wp_head', array( __CLASS__, 'output_archive_schema'  ), 5 );
        add_action( 'wp_head', array( __CLASS__, 'output_shortcode_schema'), 5 );
        add_action( 'wp_head', array( __CLASS__, 'output_noindex'         ), 1 );
    }

    /* ── Helpers ── */

    private static function json( array $schema ): void {
        echo '<script type="application/ld+json">'
            . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . '</script>' . "\n";
    }

    private static function author_name( int $user_id ): string {
        $user = get_userdata( $user_id );
        if ( ! $user ) return 'Community Member';
        return ( ! empty( trim( $user->display_name ) ) ) ? $user->display_name : $user->user_login;
    }

    /* ── 0. Noindex shortcode pages (duplicate content prevention) ── */
    public static function output_noindex(): void {
        global $post;
        if ( ! CC_QA_Admin::get( 'cc_qa_noindex_shortcode' ) ) return;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( is_post_type_archive( 'cc_question' ) ) return; // Never noindex the CPT archive itself
        if ( has_shortcode( $post->post_content, 'cc_qa' ) ) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }

    /* ── 1. WebSite + SearchAction (enables Google Sitelinks Searchbox) ── */
    public static function output_website_schema(): void {
        self::json( array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            '@id'      => home_url( '/' ) . '#website',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => array(
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url( '/' ) . '?s={search_term_string}',
                ),
                'query-input' => 'required name=search_term_string',
            ),
        ) );
    }

    /* ── 2. Organization (brand entity — essential for GEO / AI citation) ── */
    public static function output_org_schema(): void {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        $site_desc = get_bloginfo( 'description' );

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => $site_url . '#organization',
            'name'     => $site_name,
            'url'      => $site_url,
        );
        if ( $site_desc ) {
            $schema['description'] = $site_desc;
        }

        self::json( $schema );
    }

    /* ── 3. Single question — QAPage + OG + Twitter + Breadcrumb + Speakable ── */
    public static function output_single_schema(): void {
        if ( ! is_singular( 'cc_question' ) ) return;

        $question_id = get_the_ID();
        $question    = get_post( $question_id );
        if ( ! $question ) return;

        $a_count   = (int) get_post_meta( $question_id, '_cc_qa_answer_count', true );
        $votes     = (int) get_post_meta( $question_id, '_cc_qa_votes', true );
        $q_url     = get_permalink( $question_id );
        $q_title   = get_the_title( $question_id );
        $q_text    = wp_strip_all_tags( $question->post_content );
        $q_excerpt = wp_trim_words( $q_text ?: $q_title, 30, '...' );
        $modified  = get_the_modified_date( 'c', $question );
        $created   = get_the_date( 'c', $question );
        $topics    = wp_get_object_terms( $question_id, 'cc_question_topic' );

        // Fetch top answers
        $answers = get_posts( array(
            'post_type'      => 'cc_answer',
            'post_parent'    => $question_id,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'meta_key'       => '_cc_qa_votes',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ) );

        $schema_answers  = array();
        $accepted_schema = null;

        foreach ( $answers as $a ) {
            $a_votes    = (int) get_post_meta( $a->ID, '_cc_qa_votes', true );
            $a_accepted = (bool) get_post_meta( $a->ID, '_cc_qa_accepted', true );

            $entry = array(
                '@type'        => 'Answer',
                'text'         => wp_strip_all_tags( $a->post_content ),
                'dateCreated'  => get_the_date( 'c', $a ),
                'dateModified' => get_the_modified_date( 'c', $a ),
                'upvoteCount'  => $a_votes,
                'url'          => $q_url . '#answer-' . $a->ID,
                'author'       => array(
                    '@type' => 'Person',
                    'name'  => self::author_name( (int) $a->post_author ),
                ),
            );

            if ( $a_accepted ) {
                $accepted_schema = $entry;
            }
            $schema_answers[] = $entry;
        }

        // Question entity
        $question_entity = array(
            '@type'        => 'Question',
            'name'         => $q_title,
            'text'         => $q_text ?: $q_title,
            'dateCreated'  => $created,
            'dateModified' => $modified,
            'answerCount'  => $a_count,
            'upvoteCount'  => $votes,
            'url'          => $q_url,
            'author'       => array(
                '@type' => 'Person',
                'name'  => self::author_name( (int) $question->post_author ),
            ),
        );

        if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) {
            $question_entity['about'] = array_map( function( $t ) {
                return array( '@type' => 'Thing', 'name' => $t->name );
            }, $topics );
        }

        if ( ! empty( $schema_answers ) ) {
            $question_entity['suggestedAnswer'] = $schema_answers;
        }
        if ( $accepted_schema ) {
            $question_entity['acceptedAnswer'] = $accepted_schema;
        }

        // QAPage — spec requires exactly one Question as mainEntity
        self::json( array(
            '@context'     => 'https://schema.org',
            '@type'        => 'QAPage',
            '@id'          => $q_url . '#qapage',
            'name'         => $q_title,
            'url'          => $q_url,
            'dateModified' => $modified,
            'inLanguage'   => get_bloginfo( 'language' ),
            'isPartOf'     => array( '@id' => home_url( '/' ) . '#website' ),
            'mainEntity'   => $question_entity,
        ) );

        // BreadcrumbList
        $crumbs = array(
            array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home',      'item' => home_url( '/' ) ),
            array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Questions', 'item' => get_post_type_archive_link( 'cc_question' ) ),
        );
        if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) {
            $crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $topics[0]->name, 'item' => get_term_link( $topics[0] ) );
            $crumbs[] = array( '@type' => 'ListItem', 'position' => 4, 'name' => $q_title, 'item' => $q_url );
        } else {
            $crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $q_title, 'item' => $q_url );
        }
        self::json( array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ) );

        // Speakable — signals to Google/AI assistants which content to read aloud (GEO)
        self::json( array(
            '@context'  => 'https://schema.org',
            '@type'     => 'WebPage',
            '@id'       => $q_url,
            'speakable' => array(
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => array( '.qa-detail-title', '.qa-accepted-banner', '.qa-answer-content' ),
            ),
        ) );

        // Open Graph
        $og_desc = $q_excerpt;
        if ( $a_count > 0 ) {
            $og_desc .= ' — ' . $a_count . ' ' . ( $a_count === 1 ? 'answer' : 'answers' );
        }
        ?>
<meta property="og:type"        content="article" />
<meta property="og:title"       content="<?php echo esc_attr( $q_title ); ?>" />
<meta property="og:description" content="<?php echo esc_attr( $og_desc ); ?>" />
<meta property="og:url"         content="<?php echo esc_url( $q_url ); ?>" />
<meta property="og:site_name"   content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
<meta property="article:published_time" content="<?php echo esc_attr( $created ); ?>" />
<meta property="article:modified_time"  content="<?php echo esc_attr( $modified ); ?>" />
<?php if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) :
    foreach ( $topics as $t ) : ?>
<meta property="article:tag" content="<?php echo esc_attr( $t->name ); ?>" />
<?php   endforeach;
endif; ?>
<meta name="twitter:card"        content="summary" />
<meta name="twitter:title"       content="<?php echo esc_attr( $q_title ); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr( $og_desc ); ?>" />
<?php
    }

    /* ── 4. Archive + taxonomy pages — CollectionPage ── */
    public static function output_archive_schema(): void {
        $is_archive  = is_post_type_archive( 'cc_question' );
        $is_tax      = is_tax( 'cc_question_topic' );
        // Also fire on the front page when homepage mode is active
        $is_homepage = CC_QA_Admin::get( 'cc_qa_homepage_mode' ) && is_front_page() && is_home();

        if ( ! $is_archive && ! $is_tax && ! $is_homepage ) return;

        $term        = $is_tax ? get_queried_object() : null;
        // When homepage mode is active the canonical URL for the feed is /
        $archive_url = $is_tax
            ? get_term_link( $term )
            : ( $is_homepage ? home_url( '/' ) : get_post_type_archive_link( 'cc_question' ) );

        // Use admin-controlled strings for the main archive; taxonomy gets auto-generated strings
        $admin_title = CC_QA_Admin::get( 'cc_qa_archive_title' )    ?: 'Community Q&A';
        $admin_desc  = CC_QA_Admin::get( 'cc_qa_archive_meta_desc' )
                    ?: CC_QA_Admin::get( 'cc_qa_archive_subtitle' )
                    ?: get_bloginfo( 'description' )
                    ?: 'Community Q&A — ask questions and get answers.';
        $admin_seo   = CC_QA_Admin::get( 'cc_qa_archive_seo_title' ) ?: '';

        $name = $is_tax
            ? $term->name . ' Questions — ' . get_bloginfo( 'name' )
            : ( $admin_seo ?: $admin_title . ' — ' . get_bloginfo( 'name' ) );
        $desc = $is_tax
            ? 'Questions and answers about ' . $term->name . '.'
            : $admin_desc;

        self::json( array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            '@id'         => $archive_url . '#collectionpage',
            'name'        => $name,
            'description' => $desc,
            'url'         => $archive_url,
            'inLanguage'  => get_bloginfo( 'language' ),
            'isPartOf'    => array( '@id' => home_url( '/' ) . '#website' ),
        ) );

        // BreadcrumbList
        $crumbs = array(
            array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home',      'item' => home_url( '/' ) ),
            array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Questions', 'item' => get_post_type_archive_link( 'cc_question' ) ),
        );
        if ( $is_tax ) {
            $crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $term->name, 'item' => get_term_link( $term ) );
        }
        self::json( array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ) );

        // Pagination rel links
        $paged    = max( 1, (int) get_query_var( 'paged' ) );
        $max      = (int) ( $GLOBALS['wp_query']->max_num_pages ?? 1 );
        $base_url = trailingslashit( (string) $archive_url );
        if ( $paged > 1 ) {
            $prev = $paged === 2 ? $base_url : $base_url . 'page/' . ( $paged - 1 ) . '/';
            echo '<link rel="prev" href="' . esc_url( $prev ) . '">' . "\n";
        }
        if ( $paged < $max ) {
            echo '<link rel="next" href="' . esc_url( $base_url . 'page/' . ( $paged + 1 ) . '/' ) . '">' . "\n";
        }
    }

    /* ── 5. Shortcode page — CollectionPage ── */
    public static function output_shortcode_schema(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'cc_qa' ) ) return;

        self::json( array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            '@id'         => get_permalink( $post ) . '#collectionpage',
            'name'        => get_the_title( $post ) ?: 'Community Q&A',
            'description' => get_bloginfo( 'description' ) ?: 'Community Q&A — ask questions and get answers.',
            'url'         => get_permalink( $post ),
            'inLanguage'  => get_bloginfo( 'language' ),
            'isPartOf'    => array( '@id' => home_url( '/' ) . '#website' ),
        ) );
    }
}
