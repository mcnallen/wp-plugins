=== ccQuestions — Community Q&A Forum ===
Contributors: creatorconnected
Tags: q&a, questions, answers, community, seo, schema, faq, forum, voting, leaderboard
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO-first community Q&A. Every question gets its own URL, QAPage JSON-LD schema, Open Graph tags, and Speakable markup — out of the box.

== Description ==

ccQuestions turns your WordPress site into a structured community Q&A platform. Every question is a WordPress post with its own canonical URL, server-rendered HTML, and complete QAPage JSON-LD schema — the exact format Google uses to surface Q&A content in rich snippets, People Also Ask boxes, and AI Overviews.

No JavaScript rendering. No iframe embeds. No third-party dependencies. No jQuery.

**Live demo:** https://creatorconnected.com/questions/

= Why ccQuestions Is Different: SEO & GEO First =

Most Q&A plugins render everything via a single shortcode page — one URL, no per-question structured data, effectively invisible to crawlers. ccQuestions works the opposite way.

Every question gets its own permanent URL (`/questions/my-question/`). Every page ships with the structured data Google and AI search engines need to surface your content in search results, rich snippets, and AI citations.

= GEO (Generative Engine Optimisation) =

AI search engines like Perplexity, ChatGPT Browse, and Google AI Overviews favour content with clear Q&A structure, explicit authorship, date signals, topic tagging, and Speakable content markers. ccQuestions outputs all of these automatically on every question page.

= Core Q&A Features =

* Ask questions with a title and optional body text
* Categorise questions by topic (custom taxonomy with live AJAX filter)
* Answer questions inline with no page reload
* Accept an answer (question author or admin only)
* Threaded replies on answers — one level deep
* Sort questions: Newest, Top Voted, Most Answered, Unanswered
* Filter by topic with instant AJAX
* Live search within the feed
* Load-more pagination — no full page reloads
* Edit questions and answers within a 1-hour window
* Delete your own questions and answers

= Voting & Scoring =

* Upvote and downvote questions and answers
* Cannot vote on your own posts
* Lifetime vote tracking that persists across leaderboard resets
* Automatic backfill for users who existed before vote tracking was added

= Leaderboard =

* Four tabs: Top Score, Most Questions, Most Answers, Most Accepted
* Five position options: none, above feed, below feed, sidebar left, sidebar right
* Optional sticky sidebar
* Configurable max users shown (3 to 50)
* Transient-cached and busted on every vote, answer, and reset

= User Profiles =

* Unique profile URLs at /questions/author/username/
* Gravatar support with letter-initial fallback
* Lifetime score, coloured upvote and downvote counts
* 10 badges across 4 tiers: Bronze, Silver, Gold, Diamond
* SVG 12-month activity chart
* Recent questions and answers list
* Topic badge links navigate to the filtered tag archive

= Email Notifications & Weekly Digest =

* Notifies question author when a new answer is posted
* Notifies answer author when a reply is posted
* Weekly digest email via WP-Cron on a configurable day
* Token-based one-click unsubscribe — no login required
* Admin button to send digest immediately for testing
* Configurable maximum recipients per send

= Settings & Customisation =

* Homepage Mode — serve Q&A at your site root with automatic 301 redirect from /questions/
* Admin-editable heading, subtitle, SEO title, and meta description for the archive page
* Questions per page, answers per page, max answers per question page
* Minimum and maximum content length validation
* Rate limiting per user for questions, answers, and votes
* Custom CSS field — override plugin styles without editing files
* Question moderation mode — hold new questions for admin approval
* Noindex shortcode pages to prevent duplicate content with the CPT archive
* Footer credit toggle — shown by default, completely optional and freely removable
* Full compatibility with Yoast SEO and RankMath

= SEO & GEO Schema =

* QAPage JSON-LD on every single question page
* Question and Answer entities with author, dates, and vote counts
* acceptedAnswer marked in schema when set
* BreadcrumbList on single, archive, and taxonomy pages
* CollectionPage schema on archive and topic pages
* Organization schema sitewide for brand entity recognition
* WebSite and SearchAction for Google Sitelinks Searchbox eligibility
* Speakable specification on question pages — AI and voice citation signal
* Open Graph and Twitter Card meta on every question page
* Explicit canonical link on every page type
* dateModified updated on every edit, answer, and reply
* Microdata itemscope and itemprop on question cards in the feed

= Shortcodes =

[cc_qa] — Embed the full Q&A feed on any page.

[cc_qa_leaderboard] — Embed a standalone leaderboard on any page.

[cc_qa_leaderboard limit="5"] — Show top 5 users per tab (default is 10).

= No External Dependencies =

The plugin JavaScript is vanilla JS with no jQuery requirement. Styles are self-contained with no external CDN requests. Everything runs on your server.

== Installation ==

1. Download the plugin zip file.
2. Go to Plugins > Add New > Upload Plugin in your WordPress admin.
3. Upload the zip file and click Install Now.
4. Click Activate Plugin.
5. Go to Settings > Permalinks and click Save Changes. This flushes rewrite rules and is required once after first installation.
6. Visit Questions > Settings to configure the plugin.

= Manual Installation =

1. Unzip the plugin folder into `/wp-content/plugins/cc-qa/`
2. Activate the plugin from the Plugins screen.
3. Go to Settings > Permalinks and click Save Changes.

= After Activation =

* Your Q&A feed is live at yoursite.com/questions/
* Individual questions are served at yoursite.com/questions/question-title/
* User profiles are served at yoursite.com/questions/author/username/
* Add topics at Questions > Q&A Topics
* Customise the archive page heading and description at Questions > Settings

== Frequently Asked Questions ==

= Does it work with any theme? =

Yes. The plugin uses its own page templates that call get_header() and get_footer() from your active theme. All styles are self-contained. You can override any template by placing a copy in your theme root folder — for example yourtheme/single-cc_question.php.

= Will it conflict with Yoast SEO or RankMath? =

No. If either plugin is active, their title and meta description tags take precedence on Q&A pages. The plugin's JSON-LD schema blocks output independently and do not conflict with SEO plugin schema output.

= How do I prevent duplicate content if I use both /questions/ and a shortcode page? =

Enable "Noindex shortcode pages" in Questions > Settings. This adds a noindex meta tag to any page containing the [cc_qa] shortcode so only the CPT archive at /questions/ is indexed. Alternatively, enable Homepage Mode — your Q&A then has only one canonical URL at your site root.

= What is Homepage Mode? =

Homepage Mode serves the Q&A feed directly at your site root (yoursite.com/) instead of at yoursite.com/questions/. To use it, go to Settings > Reading and set "Your homepage displays" to "Your latest posts", then enable Homepage Mode in Questions > Settings. The /questions/ archive issues a 301 redirect to / automatically. Individual question pages at /questions/slug/ are unaffected.

= Can users edit their questions and answers? =

Yes, within a 1-hour window after posting. After that window, only administrators and editors can edit content. The time limit is enforced server-side.

= How does email delivery work? =

Notifications are sent via wp_mail() using your site's configured mail settings. For reliable delivery it is recommended to use a transactional email plugin such as WP Mail SMTP, Postmark for WordPress, or SendGrid.

= Is jQuery required? =

No. The plugin JavaScript is written in vanilla JS with no jQuery dependency.

= Does it create custom database tables? =

Yes. Two tables are created on activation: one for vote records and one for email subscriptions. Both are prefixed with your WordPress table prefix. Data is removed if you uninstall the plugin via the Plugins screen.

= Can I remove the "Powered by ccQuestions" credit? =

Yes, absolutely. Go to Questions > Settings > Footer Credit and uncheck the option. The credit is shown by default but is entirely optional and not required.

= Does it work with WordPress Multisite? =

The plugin has not been tested on Multisite installations and is not officially supported in that environment.

== Screenshots ==

1. The Q&A feed — questions list with topic filters, search, and sort tabs
2. Single question page with answers, voting, and accepted answer
3. User profile with badges, stats, activity chart, and recent questions
4. Leaderboard sidebar showing top contributors
5. Admin settings page

== Changelog ==

= 2.9.0 =
* Plugin renamed to ccQuestions — Community Q&A Forum for improved WordPress.org search discoverability.
* Main plugin file renamed from cc-qa.php to cc-questions.php. Plugin folder slug is now cc-questions.
* Asset files renamed to match: cc-questions.css and cc-questions.js.

= 2.8.2 =
* Bugfix: Topic badges in user profiles now correctly link to the tag-filtered archive page. Fixed an anchor-inside-anchor HTML invalidity using a CSS overlay and JavaScript delegation pattern so the row click and the tag link click are fully independent.

= 2.8.1 =
* Settings page visual refresh with branded section headings, orange save button, and styled footer credit card.
* Removed the search icon character from the main Q&A feed search box.

= 2.8.0 =
* Official plugin name: ccQuestions — Community Q&A.
* Code audit: fixed undefined variable errors in the schema class ($is_tax and $is_homepage).
* Replaced all hardcoded /register/ URLs with wp_registration_url() for full portability.
* Generic default archive title and subtitle — no site-specific copy baked in.
* Settings page footer with live demo and GitHub links.
* Footer credit toggle added to Settings — checked by default, freely removable.

= 2.7.1 =
* Bugfix: Profile pages had no horizontal padding on mobile screens.
* Bugfix: Lifetime vote counts showed 0 for users who existed before vote tracking was added — now lazily backfilled from the votes table on first profile visit.
* Bugfix: Leaderboard position setting was ignored on profile pages — now respects all five position options.

= 2.7.0 =
* Homepage Mode: serve the Q&A feed at the site root with automatic 301 redirect from /questions/.
* Explicit canonical link tag output on the archive template.
* Rewrite rules are flushed automatically when homepage mode is toggled.

= 2.6.1 =
* Profile question titles now wrap correctly on mobile instead of being clipped.
* Hero stats: Total Questions links to the feed anchor; Unanswered activates the unanswered filter tab.

= 2.6.0 =
* New setting: leaderboard max users (3 to 50).
* New setting: sidebar sticky toggle.
* New setting: custom CSS field.
* Leaderboard now appears on profile pages with full position awareness.
* Expanded profile stats: lifetime score, coloured upvote and downvote counts.
* Topic badge fix: clicking a tag on a question card now activates the AJAX topic filter instead of navigating away.
* Load-more pagination added for answers on single question pages.

= 2.5.1 =
* Bugfix: Edit forms were visible on page load due to a CSS specificity conflict.
* Bugfix: Leaderboard profile links were using the wrong URL format.

= 2.5.0 =
* User profile pages at /questions/author/username/.
* 10 badges across 4 tiers: Bronze, Silver, Gold, Diamond.
* SVG 12-month activity chart on profiles.
* Gravatar support with letter-initial fallback.
* Person schema on profile pages.

= 2.4.0 =
* Rate limiting for questions, answers, and votes with configurable window.
* Question moderation mode — hold new questions for admin approval.

= 2.3.0 =
* Leaderboard with four tabs and configurable position.

= 2.2.0 =
* Threaded replies on answers — one level deep.

= 2.1.0 =
* Weekly digest email via WP-Cron with configurable day and manual send.

= 2.0.0 =
* Email notifications for new answers and replies with token-based unsubscribe.

= 1.0.0 =
* Initial release: questions, answers, voting, topics, archive template, QAPage schema.

== Upgrade Notice ==

= 2.9.0 =
Clean rename to ccquestions slug — safe fresh install. No database changes.

= 2.8.2 =
Fixes topic badge links in user profiles — tags now correctly navigate to the filtered archive page.

= 2.8.0 =
Recommended for all users. Fixes undefined variable errors in the schema class and removes site-specific hardcoded URLs. Safe to update from any previous version.
