=== AI Email Spam Shield ===
Contributors: mozammelhaque
Tags: spam, email, contact form, spam filter, ai, machine learning, spam detection, antispam
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hybrid AI + rule-based spam detection for outgoing WordPress emails. Blocks spam before delivery — works with any form plugin.

== Description ==

**AI Email Spam Shield** by [Mozammel Haque](https://www.mymentech.com) protects your WordPress site from sending spam by intercepting all outgoing email before delivery and scoring it through a two-layer hybrid system.

= How It Works =

Every outgoing email is evaluated by two layers before it is sent:

1. **Rule-based engine** — instant, pure-PHP checks that run on every email with no external dependency
2. **AI scoring** — optional self-hosted Python microservice using a BERT model for deeper analysis

The two scores are combined using configurable weights (default: 70% AI, 30% rules). If the final score meets or exceeds the spam threshold, the email is blocked before delivery.

Certain high-confidence signals — explicit sexual content, darknet/underground-market phrases — **always block regardless of AI score**, because the BERT model is not trained on those categories.

= Spam Signals Detected =

* **URL density** — more than 3 links in one email
* **Spam phrases** — known commercial spam keywords (crypto, casino, free money, etc.)
* **Adult/sexual content** — explicit words matched with whole-word boundaries
* **Darknet phrases** — Tor, dark web, marketplace mirror links, anonymous access
* **Non-Latin scripts** — Cyrillic/Russian (+0.40), Arabic, CJK, Hebrew, Devanagari, Thai (+0.30)
* **Suspicious TLDs** — .ru, .top, .cc, .onion, .tk, .xyz, and more
* **Uppercase ratio** — excessive capitalisation (>40% of alphabetic characters)
* **Repeated characters** — consecutive special chars or letters (e.g. XXX, !!!!)
* **IP rate limiting** — more than 2 submissions from the same IP within 2 minutes
* **Honeypot** — hidden field auto-blocks bots instantly without scoring

= Key Features =

* Uses `pre_wp_mail` short-circuit filter — reliably blocks email before PHPMailer is invoked
* Works universally with **Contact Form 7**, **WPForms**, **Gravity Forms**, and any plugin using `wp_mail()`
* Fail-safe: AI API unavailable? Falls back to rule-based scoring — your forms never break
* Hard-block mode for sexual and darknet content — bypasses AI weighting entirely
* Transient caching: identical message hashes cached for 5 minutes to minimise API calls
* Full admin dashboard: live stats, paginated filterable logs, live test scanner
* Logs automatically pruned after 30 days via WP-Cron
* 100% free and open-source — no paid APIs or subscriptions

= Third Party Services =

This plugin can optionally connect to a **self-hosted** FastAPI microservice that you deploy on your own server. No data is sent to any third-party service. The BERT model (`AventIQ-AI/bert-spam-detection`) is downloaded to your own server during setup and runs locally.

= Privacy =

This plugin logs email metadata (subject, sender address, IP address, spam scores, blocked status) in your local WordPress database table (`wp_ai_spam_logs`). No data is transmitted externally. Logs are automatically deleted after 30 days.

== Installation ==

= Plugin Installation =

1. Upload the `ai-email-spam-shield` folder to `/wp-content/plugins/`
2. Activate through the **Plugins** menu in WordPress
3. Go to **AI Spam Shield → Settings**, enable scanning, and save

The plugin works immediately in rule-only mode — no further setup required.

= AI Microservice Setup (optional but recommended) =

1. Clone the plugin repository and navigate to the `spam-api/` directory
2. Copy `docker-compose-sample.yml` to your server and rename it (or merge the `spam-api` service into your existing `docker-compose.yml`)
3. Set `AIESS_API_KEY` to a strong random string in the compose file, then run: `docker-compose up -d spam-api`
4. Enter the API URL (e.g. `http://spam-api:8000/predict`) and your API key in **AI Spam Shield → Settings**

Requires Docker and approximately 500 MB disk space for the BERT model.

== Frequently Asked Questions ==

= Does the plugin work without the AI microservice? =

Yes. If the AI API is unavailable or not configured, the plugin falls back to rule-based scoring only. Your contact forms will never break.

= What happens if the AI API is slow or times out? =

The plugin enforces a strict 3-second timeout. If the API does not respond in time, it falls back to rule-only scoring for that email.

= Is any data sent to a third party? =

No. All processing happens on your own server. The BERT model is downloaded to your server during microservice setup and never phones home.

= Can I adjust the spam threshold? =

Yes. Go to **AI Spam Shield → Settings** and set the **Spam Threshold** (0.0–1.0, default 0.80). Lower values are stricter.

= Which form plugins are supported? =

Any plugin that sends email via WordPress's `wp_mail()` — including Contact Form 7, WPForms, Gravity Forms, Formidable Forms, Ninja Forms, and custom code.

= Can I test the scanner before going live? =

Yes. Go to **AI Spam Shield → Test Scanner**, paste a subject and message body, and click Run Scan to see the full score breakdown before enabling live blocking.

== Screenshots ==

1. Dashboard — live stats and AI API status indicator
2. Settings — API URL, key, spam threshold, and score weights
3. Logs — paginated, filterable email log with per-entry scores
4. Test Scanner — paste any email content and see the live score breakdown

== Changelog ==

= 1.0.1 =
* Fixed critical bug: switched from `wp_mail` filter to `pre_wp_mail` short-circuit — emails are now reliably blocked before delivery
* Added hard-block mode for sexual content and darknet phrases (bypasses AI weighting)
* Added `check_sexual_content()` with whole-word boundary matching
* Added `check_darknet_phrases()` — detects Tor, darknet marketplaces, mirror links (+0.30)
* Added `check_non_latin_script()` — blocks Cyrillic/Russian (+0.40) and Arabic/CJK/Hebrew/Thai (+0.30)
* Expanded suspicious TLDs: .top, .cc, .to, .onion, .su, .at, .biz
* Fixed `check_repeated_chars()` to catch letter repetitions like XXX
* Added adult/sexual content keywords to spam phrase list

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
Critical fix: emails marked as spam are now actually blocked. Upgrade immediately.

= 1.0.0 =
Initial release.
