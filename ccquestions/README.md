# ccQuestions — Community Q&A for WordPress

**SEO-first, GEO-optimised community Q&A.** Every question gets its own URL, full QAPage JSON-LD schema, Open Graph tags, BreadcrumbList, and Speakable markup — out of the box, zero configuration required.

[![Version](https://img.shields.io/badge/version-2.9.0-orange)](https://github.com/mcnallen/wp-plugins)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

🔗 **Live demo:** [creatorconnected.com/questions/](https://creatorconnected.com/questions/)  
📦 **Repository:** [github.com/mcnallen/wp-plugins](https://github.com/mcnallen/wp-plugins)

---

## What It Does

ccQuestions turns any WordPress site into a structured community Q&A platform. Think self-hosted Stack Overflow or Quora — but built for SEO and AI search from the ground up, not bolted on afterward.

Every question is a WordPress post with its own canonical URL. Every page ships with the structured data Google and AI engines need to surface your content in rich results, People Also Ask boxes, and AI Overviews.

---

## Why It's Different: SEO & GEO First

Most Q&A plugins render everything via shortcode on a single page — one URL, no per-question structured data, invisible to crawlers. ccQuestions works the opposite way.

| | Traditional Q&A Plugins | ccQuestions |
|---|---|---|
| URLs | One page for all questions | Every question gets `/questions/my-question/` |
| Schema | None or basic | Full QAPage JSON-LD per question |
| Crawlability | JS-dependent feeds | Server-rendered HTML |
| AI citation signals | None | Speakable, Person, Organization, dateModified |
| Duplicate content | Unmanaged | Canonical tags + noindex controls |

### GEO (Generative Engine Optimisation)

AI search engines like Perplexity, ChatGPT Browse, and Google AI Overviews favour:

- Clear Q&A structure (`Question`/`Answer` schema)
- Explicit authorship (`Person` entities)
- Date signals (`dateCreated`, `dateModified`)
- Topic tagging (`about` → `Thing` entities)
- Speakable content markers

ccQuestions implements all of these out of the box.

---

## Features

### Core Q&A
- Ask questions with title + optional body text
- Categorise by topic (custom taxonomy with AJAX filter)
- Answer questions inline — no page reload
- Accept an answer (question author or admin)
- Threaded replies on answers — one level deep
- Sort by: Newest, Top Voted, Most Answered, Unanswered
- Live search within the feed
- Load-more pagination

### Voting & Scoring
- Upvote / downvote questions and answers
- Lifetime vote tracking — persists across leaderboard resets
- Historical vote backfill for pre-existing users

### Leaderboard
- 4 tabs: Top Score, Most Questions, Most Answers, Most Accepted
- 5 position options: none, above, below, sidebar left, sidebar right
- Optional sticky sidebar, configurable max users (3–50)
- Transient-cached, busted on every interaction

### User Profiles
- Unique URLs: `/questions/author/username/`
- Gravatar with letter-initial fallback
- Lifetime score, coloured up/downvote counts
- 10 badges across 4 tiers (Bronze → Silver → Gold → Diamond)
- SVG 12-month activity chart
- Recent questions and answers list
- Topic badge links navigate to filtered archive

### Email & Digest
- Notifications on new answer and new reply
- Weekly digest via WP-Cron (configurable day)
- Token-based one-click unsubscribe
- Admin "Send Digest Now" for testing

### Settings
- **Homepage Mode** — serve Q&A at `/` with automatic 301 from `/questions/`
- Custom heading, subtitle, SEO title, and meta description
- Rate limiting per user (questions, answers, votes)
- Custom CSS field — override styles without editing files
- Question moderation mode
- Noindex shortcode pages to prevent duplicate content
- Footer credit toggle (on by default, freely removable)

---

## SEO & GEO Schema Reference

| Schema Signal | Applied On |
|---|---|
| `QAPage` JSON-LD | Every single question page |
| `Question` + `Answer` + `acceptedAnswer` | Per-question with author, dates, vote count |
| `BreadcrumbList` | Single, archive, and taxonomy pages |
| `CollectionPage` | Archive and topic taxonomy pages |
| `Organization` | Sitewide — brand entity for AI citation |
| `WebSite` + `SearchAction` | Sitewide — Sitelinks Searchbox eligibility |
| `Speakable` | Single question pages — AI/voice signal |
| Open Graph + Twitter Card | Every single question page |
| Canonical `<link>` | Every page type — no ambiguity |
| `dateModified` | Updated on every edit, answer, and reply |
| Microdata (`itemscope`/`itemprop`) | Question cards in the feed |

---

## Installation

**From WordPress Admin:**
1. Download `cc-qa.zip`
2. Plugins → Add New → Upload Plugin
3. Activate, then visit **Questions → Settings**
4. Go to **Settings → Permalinks** and click Save Changes (once, to flush rewrite rules)

**Manual:**
1. Unzip into `/wp-content/plugins/cc-qa/`
2. Activate from Plugins screen

---

## Shortcodes

```
[cc_qa]                        Embed the Q&A feed on any page
[cc_qa_leaderboard]            Embed a standalone leaderboard
[cc_qa_leaderboard limit="5"]  Show top 5 per tab (default 10)
```

---

## File Structure

```
cc-qa/
├── cc-qa.php                     Plugin entry, constants, hooks
├── README.md                     GitHub readme
├── readme.txt                    WordPress.org readme
├── assets/
│   ├── css/cc-qa.css             All styles — no external dependencies
│   └── js/cc-qa.js               Vanilla JS — no jQuery
├── includes/
│   ├── class-admin.php           Settings page, sanitizers, custom CSS
│   ├── class-ajax.php            AJAX handlers, rate limiting
│   ├── class-badges.php          Badge system, activity chart
│   ├── class-database.php        Custom DB tables, vote recording, backfill
│   ├── class-digest.php          Weekly digest, WP-Cron
│   ├── class-email.php           Notifications, token unsubscribe
│   ├── class-leaderboard.php     Leaderboard stats, caching
│   ├── class-post-types.php      CPT, taxonomy, rewrite rules, routing
│   ├── class-schema.php          All JSON-LD, OG, Twitter Card, Speakable
│   └── class-shortcode.php       [cc_qa] shortcode, card rendering
└── templates/
    ├── archive-cc_question.php   /questions/
    ├── author-cc_question.php    /questions/author/{username}/
    └── single-cc_question.php    /questions/{slug}/
```

---

## Changelog

### 2.9.0
- Plugin renamed to **ccQuestions — Community Q&A Forum** for better WordPress.org discoverability
- Main file renamed from `cc-qa.php` to `ccquestions.php`, assets renamed to match
- Plugin folder slug is now `ccquestions`

### 2.8.2
- **Bugfix:** Topic badges in user profiles now link to the filtered tag archive page instead of navigating to the question. Fixed anchor-in-anchor HTML invalidity using CSS overlay + JS delegation pattern.

### 2.8.1
- Settings page visual refresh — branded section headings, orange save button, styled footer
- Removed search box icon from main Q&A feed

### 2.8.0
- Official plugin name: **ccQuestions**
- Code audit: fixed undefined `$is_tax` / `$is_homepage` in schema class
- Replaced hardcoded `/register/` links with `wp_registration_url()`
- Generic default title and subtitle
- Settings page footer with live demo and GitHub links
- Footer credit toggle (on by default, freely removable)

### 2.7.1
- Mobile padding fix on profile pages
- Lifetime vote counts backfill from votes table for pre-2.5 installs
- Leaderboard position now respected on profile pages

### 2.7.0
- Homepage Mode: serve Q&A at `/` with 301 redirect from `/questions/`
- Explicit canonical tag on archive template
- Rewrite rules auto-flushed on homepage mode toggle

### 2.6.0 – 2.6.1
- Leaderboard max users, sticky sidebar toggle, custom CSS field
- Leaderboard on profile pages with full position support
- Expanded profile stats with coloured vote counts
- Topic filter fix on question cards
- Load-more answer pagination
- Profile title wrapping on mobile
- Hero unanswered stat and feed anchor links

### 2.5.0 – 2.5.1
- User profiles at `/questions/author/{username}/`
- 10 badges, 4 tiers, SVG activity chart, Gravatar

### 2.0.0 – 2.4.x
- Weekly digest, threaded replies, leaderboard, rate limiting, moderation

### 1.0.0
- Initial release

---

## Contributing

Issues and pull requests welcome at [github.com/mcnallen/wp-plugins](https://github.com/mcnallen/wp-plugins).

---

## Credits

Built by [CreatorConnected](https://creatorconnected.com) · [Live demo](https://creatorconnected.com/questions/)  
Licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
