<?php
/**
 * Plugin Name:       AI Reply Assistant
 * Description:       Draft AI-assisted replies to real WordPress comments (human review required). Generates editable reply drafts using OpenAI. No fake users. No auto-seeding.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            CreatorConnected
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-reply-assistant
 */

if (!defined('ABSPATH')) exit;

define('ARA_VERSION', '1.0.1');
define('ARA_PREFIX', 'ara_');
define('ARA_PAGE_SLUG', 'ara_settings');

define('ARA_OPT_API_KEY', ARA_PREFIX . 'openai_api_key');
define('ARA_OPT_MODEL', ARA_PREFIX . 'model');
define('ARA_OPT_MAX_WORDS', ARA_PREFIX . 'max_words');
define('ARA_OPT_TONE', ARA_PREFIX . 'tone');
define('ARA_OPT_DEFAULT_PENDING', ARA_PREFIX . 'default_pending');
define('ARA_OPT_SYSTEM_INSTR', ARA_PREFIX . 'system_instructions');

define('ARA_META_DRAFT', 'ara_draft_reply');
define('ARA_META_ERR', 'ara_last_error');
define('ARA_META_AI_ASSISTED', 'ara_ai_assisted');

// --------------------------
// Settings Page
// --------------------------
add_action('admin_menu', function () {
    add_options_page(
        'AI Reply Assistant',
        'AI Reply Assistant',
        'manage_options',
        ARA_PAGE_SLUG,
        'ara_settings_page'
    );
});

function ara_default_system_instructions() {
    return
        "You are helping a WordPress site moderator write a helpful, human-sounding reply to a real comment.\n" .
        "Guidelines:\n" .
        "- Be concise, specific, and friendly.\n" .
        "- Do not mention that you are AI.\n" .
        "- Do not fabricate personal experiences.\n" .
        "- Avoid em dash (—) and en dash (–). Use hyphen (-) if needed.\n" .
        "- If the commenter asks a question, answer it. If unclear, ask a clarifying question.\n";
}

function ara_settings_page() {
    if (!current_user_can('manage_options')) return;

    $notice = '';

    if (isset($_POST['ara_save']) && check_admin_referer('ara_settings_nonce')) {
        update_option(ARA_OPT_API_KEY, sanitize_text_field($_POST[ARA_OPT_API_KEY] ?? ''));
        update_option(ARA_OPT_MODEL, sanitize_text_field($_POST[ARA_OPT_MODEL] ?? 'gpt-4o-mini'));
        update_option(ARA_OPT_MAX_WORDS, max(20, min(250, intval($_POST[ARA_OPT_MAX_WORDS] ?? 120))));
        update_option(ARA_OPT_TONE, sanitize_text_field($_POST[ARA_OPT_TONE] ?? 'friendly'));
        update_option(ARA_OPT_DEFAULT_PENDING, !empty($_POST[ARA_OPT_DEFAULT_PENDING]) ? 1 : 0);
        update_option(ARA_OPT_SYSTEM_INSTR, wp_kses_post($_POST[ARA_OPT_SYSTEM_INSTR] ?? ara_default_system_instructions()));

        $notice = '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $api_key = get_option(ARA_OPT_API_KEY, '');
    $model = get_option(ARA_OPT_MODEL, 'gpt-4o-mini');
    $max_words = (int)get_option(ARA_OPT_MAX_WORDS, 120);
    $tone = get_option(ARA_OPT_TONE, 'friendly');
    $default_pending = (int)get_option(ARA_OPT_DEFAULT_PENDING, 1);
    $system_instructions = get_option(ARA_OPT_SYSTEM_INSTR, ara_default_system_instructions());
    ?>
    <div class="wrap">
        <h1>AI Reply Assistant</h1>
        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <form method="post">
            <?php wp_nonce_field('ara_settings_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ara_api_key">OpenAI API Key</label></th>
                    <td>
                        <input id="ara_api_key" type="password" class="regular-text"
                               name="<?php echo esc_attr(ARA_OPT_API_KEY); ?>"
                               value="<?php echo esc_attr($api_key); ?>" autocomplete="off" />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ara_model">Model</label></th>
                    <td>
                        <select id="ara_model" name="<?php echo esc_attr(ARA_OPT_MODEL); ?>">
                            <option value="gpt-4o-mini" <?php selected($model, 'gpt-4o-mini'); ?>>gpt-4o-mini (recommended)</option>
                            <option value="gpt-4.1-mini" <?php selected($model, 'gpt-4.1-mini'); ?>>gpt-4.1-mini</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ara_max_words">Max words</label></th>
                    <td>
                        <input id="ara_max_words" type="number" min="20" max="250"
                               name="<?php echo esc_attr(ARA_OPT_MAX_WORDS); ?>"
                               value="<?php echo esc_attr($max_words); ?>" />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ara_tone">Default tone</label></th>
                    <td>
                        <select id="ara_tone" name="<?php echo esc_attr(ARA_OPT_TONE); ?>">
                            <option value="friendly" <?php selected($tone, 'friendly'); ?>>Friendly</option>
                            <option value="professional" <?php selected($tone, 'professional'); ?>>Professional</option>
                            <option value="concise" <?php selected($tone, 'concise'); ?>>Concise</option>
                            <option value="enthusiastic" <?php selected($tone, 'enthusiastic'); ?>>Enthusiastic</option>
                            <option value="supportive" <?php selected($tone, 'supportive'); ?>>Supportive</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Default moderation</th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(ARA_OPT_DEFAULT_PENDING); ?>" value="1" <?php checked($default_pending, 1); ?> />
                            Post replies as <strong>Pending</strong> by default (recommended)
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ara_system_instructions">System instructions</label></th>
                    <td>
                        <textarea id="ara_system_instructions" name="<?php echo esc_attr(ARA_OPT_SYSTEM_INSTR); ?>"
                                  rows="10" style="width:100%;max-width:920px;"><?php echo esc_textarea($system_instructions); ?></textarea>
                    </td>
                </tr>
            </table>

            <p><button type="submit" name="ara_save" class="button button-primary">Save Changes</button></p>
        </form>
    </div>
    <?php
}

// --------------------------
// Comments List: Row Action
// --------------------------
add_filter('comment_row_actions', function ($actions, $comment) {
    if (!current_user_can('edit_comment', $comment->comment_ID)) return $actions;

    $url = wp_nonce_url(
        admin_url('admin-post.php?action=ara_generate_draft&comment_id=' . intval($comment->comment_ID)),
        'ara_generate_draft_' . intval($comment->comment_ID)
    );

    $actions['ara_generate_draft'] = '<a href="' . esc_url($url) . '">Generate AI Reply Draft</a>';
    return $actions;
}, 10, 2);

// --------------------------
// Comment Edit Screen: Metabox (NO nested form)
// --------------------------
add_action('add_meta_boxes_comment', function () {
    add_meta_box(
        'ara_reply_box',
        'AI Reply Assistant',
        'ara_comment_metabox',
        'comment',
        'normal',
        'high'
    );
});

function ara_comment_metabox($comment) {
    if (!current_user_can('edit_comment', $comment->comment_ID)) {
        echo '<p>You do not have permission to use this tool.</p>';
        return;
    }

    $draft = (string)get_comment_meta($comment->comment_ID, ARA_META_DRAFT, true);
    $last_err = (string)get_comment_meta($comment->comment_ID, ARA_META_ERR, true);

    $gen_url = wp_nonce_url(
        admin_url('admin-post.php?action=ara_generate_draft&comment_id=' . intval($comment->comment_ID)),
        'ara_generate_draft_' . intval($comment->comment_ID)
    );

    // Nonce for posting reply through the existing comment edit form
    wp_nonce_field('ara_post_reply_' . intval($comment->comment_ID), 'ara_post_reply_nonce');
    ?>
    <p>
        <a class="button button-secondary" href="<?php echo esc_url($gen_url); ?>">Generate AI Reply Draft</a>
    </p>

    <?php if (!empty($last_err)) : ?>
        <div class="notice notice-error inline"><p><strong>Last error:</strong> <?php echo esc_html($last_err); ?></p></div>
    <?php endif; ?>

    <p><strong>Draft reply (editable)</strong></p>
    <textarea name="ara_draft_reply" rows="7" style="width:100%;"><?php echo esc_textarea($draft); ?></textarea>

    <p>
        <label>
            <input type="checkbox" name="ara_approve_immediately" value="1">
            Approve immediately (otherwise reply will be pending)
        </label>
    </p>

    <p>
        <button type="submit" class="button button-primary" name="ara_post_reply" value="1">Post Reply</button>
        <span class="description" style="margin-left:8px;">Uses the existing comment edit save action (no nested forms).</span>
    </p>
    <?php
}

// --------------------------
// Generate Draft (admin-post)
// --------------------------
add_action('admin_post_ara_generate_draft', function () {
    $comment_id = isset($_GET['comment_id']) ? intval($_GET['comment_id']) : 0;

    if (!$comment_id || !current_user_can('edit_comment', $comment_id)) wp_die('Unauthorized.');
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ara_generate_draft_' . $comment_id)) wp_die('Invalid nonce.');

    $api_key = get_option(ARA_OPT_API_KEY, '');
    if (empty($api_key)) {
        update_comment_meta($comment_id, ARA_META_ERR, 'Missing OpenAI API key. Set it in Settings → AI Reply Assistant.');
        wp_safe_redirect(admin_url('comment.php?action=editcomment&c=' . $comment_id));
        exit;
    }

    $comment = get_comment($comment_id);
    if (!$comment) wp_die('Comment not found.');

    $post = get_post($comment->comment_post_ID);
    $post_title = $post ? (string)$post->post_title : '';
    $post_excerpt = $post ? wp_trim_words(wp_strip_all_tags($post->post_content), 120) : '';

    $tone = (string)get_option(ARA_OPT_TONE, 'friendly');
    $max_words = (int)get_option(ARA_OPT_MAX_WORDS, 120);
    $model = (string)get_option(ARA_OPT_MODEL, 'gpt-4o-mini');

    $system_instructions = (string)get_option(ARA_OPT_SYSTEM_INSTR, ara_default_system_instructions());
    if (trim($system_instructions) === '') $system_instructions = ara_default_system_instructions();

    $comment_text = trim(wp_strip_all_tags($comment->comment_content));

    $user_prompt =
        "Write a {$tone} reply to this comment. Keep it under {$max_words} words.\n\n" .
        "Post title: {$post_title}\n" .
        "Post excerpt: {$post_excerpt}\n\n" .
        "Commenter said:\n{$comment_text}\n\n" .
        "Reply as the site moderator. Provide a useful answer or a clarifying question. " .
        "Do not mention being AI. Avoid em dash and en dash.";

    $draft = ara_openai_chat($api_key, $model, $system_instructions, $user_prompt, $max_words);

    if ($draft === '') {
        update_comment_meta($comment_id, ARA_META_ERR, 'OpenAI returned an empty response. Check your API key/model and try again.');
    } else {
        update_comment_meta($comment_id, ARA_META_DRAFT, $draft);
        delete_comment_meta($comment_id, ARA_META_ERR);
    }

    wp_safe_redirect(admin_url('comment.php?action=editcomment&c=' . $comment_id));
    exit;
});

// --------------------------
// Post Reply (hooked into the existing comment edit form submit)
// --------------------------
add_action('edit_comment', function ($comment_id) {
    if (!current_user_can('edit_comment', $comment_id)) return;

    // Always save draft edits if present
    if (isset($_POST['ara_draft_reply'])) {
        $draft_raw = wp_unslash($_POST['ara_draft_reply']);
        update_comment_meta($comment_id, ARA_META_DRAFT, wp_kses_post($draft_raw));
    }

    // Only proceed if user clicked our Post Reply button
    if (empty($_POST['ara_post_reply'])) return;

    $nonce = $_POST['ara_post_reply_nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'ara_post_reply_' . intval($comment_id))) {
        update_comment_meta($comment_id, ARA_META_ERR, 'Security check failed (nonce). Try again.');
        return;
    }

    $draft = (string)get_comment_meta($comment_id, ARA_META_DRAFT, true);
    $plain = trim(wp_strip_all_tags($draft, false));
    if ($plain === '') {
        update_comment_meta($comment_id, ARA_META_ERR, 'Draft is empty. Generate or write a reply first.');
        return;
    }

    $parent = get_comment($comment_id);
    if (!$parent) {
        update_comment_meta($comment_id, ARA_META_ERR, 'Parent comment not found.');
        return;
    }

    $default_pending = (int)get_option(ARA_OPT_DEFAULT_PENDING, 1);
    $approve_now = !empty($_POST['ara_approve_immediately']) ? 1 : 0;
    $approved = ($approve_now === 1) ? 1 : (($default_pending === 1) ? 0 : 1);

    $user = wp_get_current_user();
    $user_id = get_current_user_id();

    $reply_id = wp_insert_comment([
        'comment_post_ID'      => (int)$parent->comment_post_ID,
        'comment_parent'       => (int)$parent->comment_ID,
        'comment_content'      => wp_kses_post($draft),
        'user_id'              => (int)$user_id,
        'comment_author'       => (string)$user->display_name,
        'comment_author_email' => (string)$user->user_email,
        'comment_approved'     => (int)$approved,
    ]);

    if (is_wp_error($reply_id) || empty($reply_id)) {
        update_comment_meta($comment_id, ARA_META_ERR, 'Failed to post reply.');
        return;
    }

    update_comment_meta($reply_id, ARA_META_AI_ASSISTED, 1);
    delete_comment_meta($comment_id, ARA_META_ERR);

    // Clear draft after successful post
    update_comment_meta($comment_id, ARA_META_DRAFT, '');

    // Add success flag to redirect
    add_filter('redirect_comment_location', function ($location) {
        return add_query_arg(['ara_posted' => '1'], $location);
    });
}, 10, 1);

// Success notice
add_action('admin_notices', function () {
    if (!is_admin()) return;
    if (!isset($_GET['ara_posted']) || $_GET['ara_posted'] !== '1') return;
    echo '<div class="notice notice-success"><p>Reply posted successfully.</p></div>';
});

// --------------------------
// OpenAI (WP HTTP API)
// --------------------------
function ara_openai_chat($api_key, $model, $system_instructions, $user_prompt, $max_words) {
    $url = 'https://api.openai.com/v1/chat/completions';

    $max_tokens = max(80, min(800, intval(((int)$max_words) * 2)));

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => (string)$system_instructions],
            ['role' => 'user', 'content' => (string)$user_prompt],
        ],
        'max_tokens' => $max_tokens,
        'temperature' => 0.7,
    ];

    $resp = wp_remote_post($url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 45,
    ]);

    if (is_wp_error($resp)) {
        error_log('ARA OpenAI request error: ' . $resp->get_error_message());
        return '';
    }

    $code = wp_remote_retrieve_response_code($resp);
    $raw  = wp_remote_retrieve_body($resp);

    if ($code < 200 || $code >= 300) {
        error_log("ARA OpenAI HTTP {$code}: {$raw}");
        return '';
    }

    $json = json_decode($raw, true);
    $text = trim((string)($json['choices'][0]['message']['content'] ?? ''));

    // Replace em/en dash with hyphen as a final output guard
    $text = str_replace(["\xE2\x80\x94", "\xE2\x80\x93"], '-', $text);

    return $text;
}
