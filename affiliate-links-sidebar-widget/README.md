# Affiliate Links Sidebar Widget

Automatically finds affiliate links (e.g. `amzn.to`) in your content and displays them in a sidebar widget or via shortcode.

**Free version:** Maximum **5 links** per post/page (configurable **1–5**).

- Donate: https://cash.app/$creatorconnected  
- License: **GPLv2 or later**  
- License URI: https://www.gnu.org/licenses/gpl-2.0.html  
- Requires WordPress: **6.0+**  
- Tested up to: **6.9.1**  
- Requires PHP: **7.4+**  
- Stable tag / Version: **1.6.11**  
- Tags: affiliate, amazon, links, shortcode, sidebar  

---

## Overview

**Affiliate Links Sidebar Widget** makes it easy to showcase the affiliate products you already mention in your content — without manual copy-pasting.

Unlike other affiliate plugins that require manual imports or API keys, this plugin automatically detects links you've already added to your content and displays them with no extra work.

The plugin scans the current post/page content for affiliate links (starting with your chosen prefix, e.g. `https://amzn.to/`) and automatically displays them in:

- A sidebar widget (**Appearance → Widgets**)
- Or an inline shortcode block: `[affiliate-links]`

Perfect for Amazon Associates, bloggers, reviewers, and content creators who want to highlight recommended products without extra work.

---

## Free Version Features

- Single affiliate prefix (e.g. Amazon `https://amzn.to/`)
- Up to **5 links** displayed per page/post (configurable **1–5**)
- Customizable title, disclosure text, and link behavior (new tab, sponsored, noopener, etc.)
- Clean, modern design with hover effects
- Mobile/desktop visibility control for shortcode
- Custom CSS options for widget and shortcode

---

## Pro Version Adds

- Unlimited links per page
- Multiple affiliate programs/prefixes at once (Amazon, ShareASale, etc.)
- No display limit (or custom max)
- More link behavior options

View Pro details → https://creatorconnected.com/affiliate-links-sidebar-widget/

---

## Great For

- Product roundups
- Reviews
- Buying guides
- Comparison posts
- Any content where you already link to affiliate products

---

## Installation

1. Upload the `affiliate-links-sidebar-widget` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Go to **Settings → Affiliate Links Sidebar Widget** to set your affiliate prefix  
   (default: `https://amzn.to/`) and customize titles/disclosure
4. Add the widget to any sidebar via **Appearance → Widgets**
5. Or place the shortcode `[affiliate-links]` anywhere in your post/page content

---

## Usage

### Sidebar Widget
Add the widget in: **Appearance → Widgets**  
It will automatically show detected affiliate links for the current post/page.

### Shortcode
Place this anywhere in content: [affiliate-links]


---

## FAQ

### What affiliate programs are supported?
Any program that uses a consistent URL prefix (e.g. `https://amzn.to/`, `https://rstyle.me/`, etc.).  
**Free version:** one prefix. **Pro version:** multiple prefixes at the same time.

### How does it find the links?
It scans the current post/page content for hyperlinks that begin with your configured prefix, then lists them using the link text (or a URL fallback).

### Can I use it without a sidebar?
Yes — use the shortcode `[affiliate-links]` anywhere (content, block, footer, etc.).

### Is there a limit on links?
**Free:** max **5** per page/post (configurable **1–5**).  
**Pro:** unlimited or custom max.

### Does it work with block themes / Full Site Editing?
Yes — the widget works in classic sidebars and the shortcode works everywhere.

### Can I style it differently?
Yes — use the built-in Custom CSS fields, or override these classes in your theme:

- `.affiliate-links-widget`
- `.affiliate-links-shortcode`

---

## Screenshots & Preview

Found here: https://creatorconnected.com/affiliate-links-sidebar-widget/

Also live here: https://creatorconnected.com/best-pcs-pre-builts-for-video-editors-streamers-creators/

---

## Changelog

### 1.6.11
- Added user-configurable Max Links to Display (1–5) in free version
- Added clear in-settings instructions for using the widget and shortcode
- Updated admin menu titles and page slugs for better consistency and discoverability
- Improved settings defaults merging with `wp_parse_args` for smoother upgrades
- Tested compatibility with WordPress 6.9.1

### 1.6.0
- Introduced link behavior controls (new tab, sponsored, nofollow, noopener)
- Added custom CSS fields for widget and shortcode styling
- Enhanced mobile/desktop visibility toggle for shortcode output

### 1.5.0
- Clarified plugin description and UI messaging: “Limited to 5 links per page in free version”
- Improved link extraction reliability and duplicate removal
- Minor security hardening and code cleanup

### 1.4.2
- Small UI improvements for better readability in settings
- Fixed minor styling edge cases on mobile

### 1.4.0
- Updated default disclosure text to include combined Amazon Associates + general affiliate statement
- Added optional plugin credit footer (configurable location)

### 1.3.0
- Default link attributes changed to `rel="sponsored noopener"` + `target="_blank"` for better compliance and UX
- Added disclosure text helpers and examples in settings

### 1.0.0
- Initial public release
- Core functionality: scan page content for affiliate links and display in widget or shortcode
- Single prefix support, basic styling, disclosure field

---

## Upgrade Notice

### 1.6.11
New: Choose exactly how many links (1–5) to display. Settings page now shows clear instructions for widget and shortcode usage. Updated for WordPress 6.9.1 compatibility.

---

## Other Notes

This is the **free version** of **Affiliate Links Sidebar Widget**.

Want unlimited links, multiple affiliate programs, no display cap, and more customization?  
Check out the Pro version: https://creatorconnected.com/affiliate-links-sidebar-widget/

Thank you for using the plugin!

