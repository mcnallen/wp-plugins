# AI Reply Assistant

Draft AI-assisted replies to real WordPress comments (human review required).

## Plugin Details

- **Contributors:** creatorconnected  
- **Donate:** https://cash.app/$creatorconnected  
- **Tags:** ai, comments, moderation, replies, assistant, openai  
- **Requires at least:** 6.0  
- **Tested up to:** 6.9.1  
- **Requires PHP:** 7.4  
- **Version:** 1.0.1  
- **License:** GPLv2 or later  

License URI: https://www.gnu.org/licenses/gpl-2.0.html

Draft AI-assisted replies to real WordPress comments (human review required). Generates editable reply drafts using OpenAI. No fake users. No auto-seeding.

== Description ==

AI Reply Assistant helps site owners and moderators respond to real comments faster by generating an editable reply draft using OpenAI.

This plugin is designed for **human-in-the-loop** workflows:
- It generates a draft reply for a real comment
- You can edit the draft
- You choose when to post it
- Replies can be posted as **Pending** by default (recommended)

**What this plugin does**
- Adds "Generate AI Reply Draft" to comment actions in wp-admin
- Adds an AI draft box on the comment edit screen
- Lets you edit and post a reply as the current logged-in user
- Optional: post as Pending by default

**What this plugin does NOT do**
- Does not create fake users
- Does not auto-seed comments
- Does not auto-post without review

**OpenAI API Key Required**
You must provide your own OpenAI API key in:
Settings → AI Reply Assistant

== Installation ==

1. Upload the `ai-reply-assistant` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → AI Reply Assistant and add your OpenAI API key
4. Go to Comments and click "Generate AI Reply Draft" on any comment

== Frequently Asked Questions ==

= Where do I generate replies? =
Go to wp-admin → Comments. Use the "Generate AI Reply Draft" action, or open a comment and use the AI Reply Assistant box.

= Does it auto-post replies? =
No. It generates a draft that you can edit. Posting requires clicking "Post Reply".

= Can replies be moderated first? =
Yes. Enable "Post replies as Pending by default" in settings.

= Does it work with block themes / FSE? =
Yes. It works in wp-admin and uses the standard WordPress comment system.

= What data is sent to OpenAI? =
The post title, a short excerpt, and the comment text are sent to generate a reply draft.

== Screenshots ==

1. Comment row action: Generate AI Reply Draft
2. Comment edit screen: AI draft box
3. Settings page

== Changelog ==

= 1.0.1 =
* Initial release
* Generate editable AI reply drafts for real comments
* Post replies (pending by default)
* No fake users, no auto-seeding
* Uses WordPress HTTP API for requests
* Tested up to WordPress 6.9.1

== Upgrade Notice ==

= 1.0.1 =
First release.
