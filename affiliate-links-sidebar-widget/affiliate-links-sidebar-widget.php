<?php
/**
 * Plugin Name: Affiliate Links Sidebar Widget
 * Description: Dynamically pulls affiliate links from the current page content (default: Amazon amzn.to) and displays them in a clean sidebar widget. Limited to 5 links per page in free version. Includes shortcode [affiliate-links].
 * Version: 1.6.12
 * Author: CreatorConnected
 * Author URI: https://creatorconnected.com
 * License: GPL-2.0+
 * Text Domain: affiliate-links-sidebar-widget
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Default styles (frontend)
add_action( 'wp_head', 'affiliate_links_default_styles' );
function affiliate_links_default_styles() {
    ?>
    <style id="affiliate-links-default">
        .affiliate-links-widget, .affiliate-links-shortcode {
            margin: 2em 0;
            padding: 1.5em;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        .affiliate-links-widget h4, .affiliate-links-shortcode h4 {
            margin: 0 0 1em;
            font-size: 1.25em;
            color: #232f3e;
        }
        .affiliate-links-widget ul, .affiliate-links-shortcode ul {
            list-style: none;
            padding: 0;
            margin: 0 0 1em;
        }
        .affiliate-links-widget li, .affiliate-links-shortcode li {
            margin-bottom: 12px;
            padding: 12px 14px;
            background: white;
            border-left: 4px solid #ff9900;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .affiliate-links-widget li:hover, .affiliate-links-shortcode li:hover {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .affiliate-links-widget a, .affiliate-links-shortcode a {
            color: #232f3e;
            text-decoration: none;
            font-weight: 500;
        }
        .affiliate-links-widget a:hover, .affiliate-links-shortcode a:hover {
            color: #ff9900;
            text-decoration: underline;
        }
        .affiliate-disclosure {
            font-size: 0.85em;
            color: #666;
            text-align: center;
            margin-top: 1em;
            font-style: italic;
        }
        .plugin-credit {
            font-size: 0.75em;
            color: #aaa;
            text-align: center;
            margin-top: 12px;
        }
        @media (max-width: 768px) { .desktop-only { display: none !important; } }
    </style>
    <?php
}

// Settings page
add_action( 'admin_menu', 'affiliate_links_add_settings_page' );
function affiliate_links_add_settings_page() {
    add_options_page(
        'Affiliate Links Sidebar Widget Settings',
        'Affiliate Links Sidebar Widget',
        'manage_options',
        'affiliate-links-sidebar-widget',
        'affiliate_links_settings_page'
    );
}

function affiliate_links_settings_page() {
    if ( isset( $_POST['affiliate_links_submit'] ) && check_admin_referer( 'affiliate_links_settings_nonce' ) ) {
        $input = wp_unslash( $_POST );

        $settings = [
            'prefix'                    => esc_url_raw( trim( $input['prefix'] ?? 'https://amzn.to/' ) ),
            'sidebar_css'               => trim( $input['sidebar_css'] ?? '' ),
            'shortcode_css'             => trim( $input['shortcode_css'] ?? '' ),
            'widget_title'              => sanitize_text_field( $input['widget_title'] ?? 'Recommended Products on Page' ),
            'shortcode_title'           => sanitize_text_field( $input['shortcode_title'] ?? 'Recommended Products on Page' ),
            'disclosure'                => wp_kses_post( $input['disclosure'] ?? '' ),
            'credit_location'           => sanitize_text_field( $input['credit_location'] ?? 'none' ),
            'hide_shortcode_on_desktop' => isset( $input['hide_shortcode_on_desktop'] ) ? 1 : 0,
            'link_new_tab'              => isset( $input['link_new_tab'] ) ? 1 : 0,
            'link_rel_sponsored'        => isset( $input['link_rel_sponsored'] ) ? 1 : 0,
            'link_rel_nofollow'         => isset( $input['link_rel_nofollow'] ) ? 1 : 0,
            'link_rel_noopener'         => isset( $input['link_rel_noopener'] ) ? 1 : 0,
            'max_links_display'         => max( 1, min( 5, (int) ( $input['max_links_display'] ?? 5 ) ) ),
        ];
        update_option( 'affiliate_links_settings', $settings );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $defaults = [
        'prefix'                    => 'https://amzn.to/',
        'sidebar_css'               => ".affiliate-links-widget { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5em; }\n.affiliate-links-widget li { border-left-color: #ff9900; background: white; }\n.affiliate-links-widget a:hover { color: #ff9900; }",
        'shortcode_css'             => ".affiliate-links-shortcode { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5em; margin: 2em 0; }\n.affiliate-links-shortcode h4 { color: #232f3e; }\n.affiliate-links-shortcode li { border-left-color: #ff9900; background: white; }\n.affiliate-links-shortcode a:hover { color: #ff9900; }",
        'widget_title'              => 'Recommended Products on Page',
        'shortcode_title'           => 'Recommended Products on Page',
        'disclosure'                => 'As an Amazon Associate I earn from qualifying purchases. This site contains affiliate links, commissions may be earned at no extra cost to you.',
        'credit_location'           => 'none',
        'hide_shortcode_on_desktop' => 0,
        'link_new_tab'              => 1,
        'link_rel_sponsored'        => 1,
        'link_rel_nofollow'         => 0,
        'link_rel_noopener'         => 1,
        'max_links_display'         => 5,
    ];
    $settings = wp_parse_args( get_option( 'affiliate_links_settings', $defaults ), $defaults );

    $default_widget_css = ".affiliate-links-widget { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5em; }\n.affiliate-links-widget li { border-left-color: #ff9900; background: white; }\n.affiliate-links-widget a:hover { color: #ff9900; }";
    $default_shortcode_css = ".affiliate-links-shortcode { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5em; margin: 2em 0; }\n.affiliate-links-shortcode h4 { color: #232f3e; }\n.affiliate-links-shortcode li { border-left-color: #ff9900; background: white; }\n.affiliate-links-shortcode a:hover { color: #ff9900; }";
    ?>
    <div class="wrap">
        <h1>Affiliate Links Sidebar Widget Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'affiliate_links_settings_nonce' ); ?>

            <h2>Affiliate Prefix</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        Link Prefix
                        <a href="https://creatorconnected.com/affiliate-links-sidebar-widget/" target="_blank" class="dashicons dashicons-info" title="Want to support multiple affiliate programs (e.g., Amazon + ShareASale)? Check out Pro for multiple prefixes."></a>
                    </th>
                    <td>
                        <input type="text" name="prefix" value="<?php echo esc_attr( $settings['prefix'] ); ?>" class="regular-text">
                        <p class="description">
                            Used by both widget and shortcode.<br>
                            A prefix is the beginning part of your affiliate links (the domain/shortener before the unique code).<br>
                            The plugin scans your page content for any links that start with this prefix and displays them.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Titles</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Widget Title</th>
                    <td>
                        <input type="text" name="widget_title" value="<?php echo esc_attr( $settings['widget_title'] ); ?>" class="regular-text">
                        <p class="description">
                            Title shown above the list in the sidebar widget.<br>
                            <a href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>" target="_blank">Add the widget here → Appearance → Widgets</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Shortcode Title</th>
                    <td>
                        <input type="text" name="shortcode_title" value="<?php echo esc_attr( $settings['shortcode_title'] ); ?>" class="regular-text">
                        <p class="description">
                            Title shown above the list when using the shortcode <code>[affiliate-links]</code> in any post or page.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Disclosure</h2>
            <table class="form-table">
                <tr><th scope="row">Disclosure Text</th><td><textarea name="disclosure" rows="3" class="widefat"><?php echo esc_textarea( $settings['disclosure'] ); ?></textarea></td></tr>
            </table>

            <h2>Link Behavior</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Behavior</th>
                    <td>
                        <label><input type="checkbox" name="link_new_tab" value="1" <?php checked( $settings['link_new_tab'], 1 ); ?>> Open links in new tab</label><br>
                        <label><input type="checkbox" name="link_rel_sponsored" value="1" <?php checked( $settings['link_rel_sponsored'], 1 ); ?>> Add rel="sponsored"</label><br>
                        <label><input type="checkbox" name="link_rel_nofollow" value="1" <?php checked( $settings['link_rel_nofollow'], 1 ); ?>> Add rel="nofollow"</label><br>
                        <label><input type="checkbox" name="link_rel_noopener" value="1" <?php checked( $settings['link_rel_noopener'], 1 ); ?>> Add rel="noopener" (when new tab)</label>
                    </td>
                </tr>
            </table>

            <h2>Display Limits</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        Max Links to Display
                        <span class="dashicons dashicons-info info-tooltip" title="The free version is limited to a maximum of 5 links per page. The Pro version removes this limit and allows unlimited links."></span>
                    </th>
                    <td>
                        <select name="max_links_display">
                            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                <option value="<?php echo esc_attr( $i ); ?>"<?php selected( $settings['max_links_display'], $i ); ?>><?php echo esc_html( $i ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <p class="description">Choose how many affiliate links to show on this page (limited to 5 in free version).</p>
                    </td>
                </tr>
            </table>

            <h2>Credit Link</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Credit Location</th>
                    <td>
                        <label><input type="radio" name="credit_location" value="none" <?php checked( $settings['credit_location'], 'none' ); ?>> Never</label><br>
                        <label><input type="radio" name="credit_location" value="sidebar" <?php checked( $settings['credit_location'], 'sidebar' ); ?>> Sidebar widget only</label><br>
                        <label><input type="radio" name="credit_location" value="shortcode" <?php checked( $settings['credit_location'], 'shortcode' ); ?>> Shortcode block only</label><br>
                        <label><input type="radio" name="credit_location" value="both" <?php checked( $settings['credit_location'], 'both' ); ?>> Both</label>
                    </td>
                </tr>
            </table>

            <h2>Shortcode Visibility</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Hide shortcode on desktop</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hide_shortcode_on_desktop" value="1" <?php checked( $settings['hide_shortcode_on_desktop'], 1 ); ?>>
                            Hide shortcode block completely on desktop (show only on mobile/tablet)
                        </label>
                        <p class="description">Useful when using sidebar widget on desktop and shortcode on mobile.</p>
                    </td>
                </tr>
            </table>

            <h2>Custom CSS</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Widget CSS</th>
                    <td>
                        <textarea name="sidebar_css" rows="8" class="widefat"><?php echo esc_textarea( $settings['sidebar_css'] ?: $default_widget_css ); ?></textarea>
                        <p><button type="button" class="button" onclick="document.querySelector('[name=\'sidebar_css\']').value = '<?php echo esc_js( addslashes( $default_widget_css ) ); ?>';">Reset to Default</button></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Shortcode CSS</th>
                    <td>
                        <textarea name="shortcode_css" rows="8" class="widefat"><?php echo esc_textarea( $settings['shortcode_css'] ?: $default_shortcode_css ); ?></textarea>
                        <p><button type="button" class="button" onclick="document.querySelector('[name=\'shortcode_css\']').value = '<?php echo esc_js( addslashes( $default_shortcode_css ) ); ?>';">Reset to Default</button></p>
                    </td>
                </tr>
            </table>

            <h2>Like this plugin?</h2>
            <p style="font-size: 1.1em;">
                The Pro version adds unlimited links, multiple affiliate programs, and more custom behaviors.<br>
                <a href="https://creatorconnected.com/affiliate-links-sidebar-widget/" target="_blank">View Pro details →</a>
            </p>

            <?php submit_button( 'Save Settings', 'primary', 'affiliate_links_submit' ); ?>
        </form>
    </div>
    <style>
        .dashicons.dashicons-info {
            text-decoration: none;
            color: #666;
            font-size: 16px;
            vertical-align: middle;
            cursor: pointer;
            margin-left: 6px;
        }
        .dashicons.dashicons-info:hover {
            color: #0073aa;
        }
        .info-tooltip {
            cursor: help;
        }
    </style>
    <?php
}

// Custom CSS output – late priority
add_action( 'wp_head', 'affiliate_links_output_custom_css', 999 );
function affiliate_links_output_custom_css() {
    $settings = get_option( 'affiliate_links_settings', [] );
    $css = trim( ( $settings['sidebar_css'] ?? '' ) . "\n" . ( $settings['shortcode_css'] ?? '' ) );

    if ( $css !== '' ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User CSS sanitized with wp_strip_all_tags; safe for <style> block
        echo '<style id="affiliate-links-custom">' . wp_strip_all_tags( $css ) . '</style>';
    }
}

class Affiliate_Links_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'affiliate_links_widget',
            'Affiliate Links Sidebar (Free)',
            [ 'description' => 'Shows affiliate links from page content using global prefix (limited to 5 in free).' ]
        );
    }

    public function widget( $args, $instance ) {
        if ( ! is_singular() ) return;

        if ( ! empty( $instance['desktop_only'] ) && wp_is_mobile() ) {
            return;
        }

        global $post;
        $content = apply_filters( 'the_content', $post->post_content );

        $settings = get_option( 'affiliate_links_settings', [] );
        $prefix = rtrim( $settings['prefix'] ?? 'https://amzn.to', '/' );
        $pattern = '/(' . preg_quote( $prefix, '/' ) . '\/[^\s<>"\']+)/i';
        preg_match_all( $pattern, $content, $matches );
        $links = array_unique( $matches[1] ?? [] );
        if ( empty( $links ) ) return;

        $max_display = ! empty( $settings['max_links_display'] ) ? max( 1, min( 5, (int) $settings['max_links_display'] ) ) : 5;
        $links = array_slice( $links, 0, $max_display );

        $title = ! empty( $instance['title'] ) ? $instance['title'] : ( $settings['widget_title'] ?? 'Recommended Products on Page' );

        $class = 'affiliate-links-widget';
        if ( ! empty( $instance['desktop_only'] ) ) $class .= ' desktop-only';

        $target = ! empty( $settings['link_new_tab'] ) ? ' target="_blank"' : '';
        $rel_parts = [];
        if ( ! empty( $settings['link_rel_sponsored'] ) ) $rel_parts[] = 'sponsored';
        if ( ! empty( $settings['link_rel_nofollow'] ) )   $rel_parts[] = 'nofollow';
        if ( ! empty( $settings['link_rel_noopener'] ) && ! empty( $settings['link_new_tab'] ) ) $rel_parts[] = 'noopener';
        $rel_attr = $rel_parts ? ' rel="' . esc_attr( implode( ' ', $rel_parts ) ) . '"' : '';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core widget args; escaping would break HTML structure
        echo $args['before_widget'];
        echo '<div class="' . esc_attr( $class ) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core widget args; escaping would break HTML structure
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<ul>';
        foreach ( $links as $link ) {
            $text = $this->get_link_text( $content, $link );
            $display = $text ?: esc_html( str_replace( ['https://','http://'], '', $link ) );
            echo '<li><a href="' . esc_url( $link ) . '"' . $target . $rel_attr . '>' . esc_html( $display ) . '</a></li>';
        }
        echo '</ul>';
        if ( ! empty( $settings['disclosure'] ) ) {
            echo '<p class="affiliate-disclosure">' . wp_kses_post( $settings['disclosure'] ) . '</p>';
        }
        if ( in_array( $settings['credit_location'] ?? 'none', ['sidebar', 'both'] ) ) {
            echo '<p class="plugin-credit">Powered by <a href="https://creatorconnected.com" target="_blank" rel="nofollow">CreatorConnected</a></p>';
        }
        echo '</div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core widget args; escaping would break HTML structure
        echo $args['after_widget'];
    }

    private function get_link_text( $content, $link ) {
        $escaped = preg_quote( $link, '/' );
        preg_match( '/<a\s+[^>]*href=["\']' . $escaped . '["\'][^>]*>(.*?)<\/a>/is', $content, $m );
        return ! empty( $m[1] ) ? wp_strip_all_tags( $m[1] ) : '';
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? '';
        $desktop_only = ! empty( $instance['desktop_only'] );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>">Widget Title (overrides global)</label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr( $this->get_field_id('desktop_only') ); ?>" name="<?php echo esc_attr( $this->get_field_name('desktop_only') ); ?>" value="1" <?php checked( $desktop_only ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id('desktop_only') ); ?>">Show only on desktop (hide on mobile/tablet)</label>
        </p>
        <p style="font-size:0.9em; color:#555;">
            All settings: <a href="<?php echo esc_url( admin_url('options-general.php?page=affiliate-links-sidebar-widget') ); ?>">Settings → Affiliate Links Sidebar Widget</a>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return [
            'title'        => wp_strip_all_tags( $new_instance['title'] ?? '' ),
            'desktop_only' => ! empty( $new_instance['desktop_only'] ) ? 1 : 0,
        ];
    }
}

function affiliate_links_shortcode() {
    if ( ! is_singular() ) return '';

    $settings = get_option( 'affiliate_links_settings', [] );

    if ( ! empty( $settings['hide_shortcode_on_desktop'] ) && ! wp_is_mobile() ) {
        return '';
    }

    static $in_progress = false;
    if ( $in_progress ) return '';
    $in_progress = true;

    global $post;
    $content = apply_filters( 'the_content', $post->post_content );

    $prefix = rtrim( $settings['prefix'] ?? 'https://amzn.to', '/' );
    $pattern = '/(' . preg_quote( $prefix, '/' ) . '\/[^\s<>"\']+)/i';
    preg_match_all( $pattern, $content, $matches );
    $links = array_unique( $matches[1] ?? [] );

    if ( empty( $links ) ) {
        $in_progress = false;
        return '';
    }

    $max_display = ! empty( $settings['max_links_display'] ) ? max( 1, min( 5, (int) $settings['max_links_display'] ) ) : 5;
    $links = array_slice( $links, 0, $max_display );

    $title = $settings['shortcode_title'] ?? 'Recommended Products on Page';

    $target = ! empty( $settings['link_new_tab'] ) ? ' target="_blank"' : '';
    $rel_parts = [];
    if ( ! empty( $settings['link_rel_sponsored'] ) ) $rel_parts[] = 'sponsored';
    if ( ! empty( $settings['link_rel_nofollow'] ) )   $rel_parts[] = 'nofollow';
    if ( ! empty( $settings['link_rel_noopener'] ) && ! empty( $settings['link_new_tab'] ) ) $rel_parts[] = 'noopener';
    $rel_attr = $rel_parts ? ' rel="' . esc_attr( implode( ' ', $rel_parts ) ) . '"' : '';

    ob_start();
    ?>
    <div class="affiliate-links-shortcode">
        <h4><?php echo esc_html( $title ); ?></h4>
        <ul>
            <?php foreach ( $links as $link ) :
                $text = '';
                $escaped = preg_quote( $link, '/' );
                preg_match( '/<a\s+[^>]*href=["\']' . $escaped . '["\'][^>]*>(.*?)<\/a>/is', $content, $m );
                if ( ! empty( $m[1] ) ) $text = wp_strip_all_tags( $m[1] );
                $display = $text ?: esc_html( str_replace( ['https://','http://'], '', $link ) );
            ?>
                <li><a href="<?php echo esc_url( $link ); ?>"<?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Concatenated attributes are hardcoded + esc_attr'd earlier
                    echo $target . $rel_attr;
                ?>><?php echo esc_html( $display ); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if ( ! empty( $settings['disclosure'] ) ) : ?>
            <p class="affiliate-disclosure"><?php echo wp_kses_post( $settings['disclosure'] ); ?></p>
        <?php endif; ?>
        <?php if ( in_array( $settings['credit_location'] ?? 'none', ['shortcode', 'both'] ) ) : ?>
            <p class="plugin-credit">Powered by <a href="https://creatorconnected.com" target="_blank" rel="nofollow">CreatorConnected</a></p>
        <?php endif; ?>
    </div>
    <?php
    $in_progress = false;
    return ob_get_clean();
}
add_shortcode( 'affiliate-links', 'affiliate_links_shortcode' );

add_action( 'widgets_init', function() {
    register_widget( 'Affiliate_Links_Widget' );
} );
