=== CoachPro AI Assistant ===
Contributors: nuzwa269
Tags: ai, coaching, chatbot, openai, assistant
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 6.5
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete AI Coaching Assistant - chat, projects, assistants, credits, payments. 100% WordPress native. No Supabase required.

== Description ==

CoachPro AI Assistant is a fully self-hosted WordPress plugin that brings AI coaching to your WordPress site. Features include:

* Multi-model AI Chat (OpenAI, Anthropic, OpenRouter)
* Projects and Conversations management
* Custom and prebuilt AI Assistants
* Credits and Subscription Plans (free/basic/pro)
* Manual Payments (JazzCash, EasyPaisa, Bank Transfer)
* Admin panel with full CRUD for all entities
* Shortcode-based frontend - embed anywhere

== Installation ==

1. In WordPress go to Plugins > Add New > Upload Plugin, select coachpro-ai-assistant.zip, and click Install Now. Alternatively upload the extracted `coachpro-ai-assistant` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **CoachPro AI > AI Providers** and enter your API keys, then set each model's token prices
4. Add the shortcode `[coachpro]` to any page

== Shortcodes ==

* `[coachpro_landing]` / `[coachpro_home]` - Public CoachPro landing page

* `[coachpro]` - Full app
* `[coachpro_dashboard]` - Dashboard
* `[coachpro_chat]` - Chat interface
* `[coachpro_projects]` - Projects list
* `[coachpro_assistants]` - Assistants
* `[coachpro_saved]` - Saved Responses
* `[coachpro_buy_credits]` - Buy Credits / Plans
* `[coachpro_settings]` - Profile Settings
* `[coachpro_login]` - Login Form
* `[coachpro_register]` - Register Form
* `[coachpro_transactions]` - Credit History

== Frequently Asked Questions ==

= Which AI providers are supported? =
OpenAI (GPT-4o, etc.), Anthropic (Claude), and OpenRouter.

= Is Supabase required? =
No. Everything runs on WordPress + MySQL.

== Changelog ==

= 1.5.3 =
* Fix: a model using the Custom provider key could be saved with the OpenAI Base URL, so its key was sent to api.openai.com and every chat failed with HTTP 401. Such models now use the Custom provider URL, and changing the Custom URL moves these models with it.
* Fix: the chat model list no longer offers models whose provider key is missing.
* Fix: code, HTML and text containing "<" are kept in chat messages (they were stripped before).
* Fix: "Sending…" and "Thinking…" showed as "Sending?" and "Thinking?". The non-existent fallback model "gpt-4o-mini" is no longer sent.
* Security: Google Sign-In uses a one-time random state and builds its callback URL from the site address instead of the request Host header. An existing account's display name is no longer overwritten at each Google login.
* Security: the Google Client Secret is no longer printed into the Settings page; leave the field empty to keep the saved secret.
* Security: passwords need at least 8 characters; sign-ups are limited to 5 per IP per hour (each account receives free credits).
* Security: removed the unused POST /conversations/{id}/messages endpoint, which allowed adding unpaid messages to chat history.
* Security: assistant names (120) and system prompts (20,000 characters) have a maximum length; the pricing page escapes its embedded data.

= 1.5.2 =
* Users can upload their own profile picture/logo in Settings (JPG/PNG/WebP, max 2 MB, re-encoded to 256x256). Without one, a neutral person icon is shown. The user's name is no longer shown in the header or chat welcome.
* Security: prebuilt assistants' instructions (system prompts) and settings are no longer sent to users' browsers.
* Security: sign-in is paused for 15 minutes after 5 failed attempts per account (20 per IP), with one generic error message.

= 1.5.1 =
* New admin page "Pricing Calculator": enter the dollar rate, target profit and each model's API price; it shows the credits per 1,000 tokens to charge, the profit on every plan and pack, the cost of a typical message, and the last 30 days of real API cost vs credits charged. One click applies the recommended prices.
* Recommended models added and priced for 50% profit: Claude Haiku 5.5 (default) and GPT-6 Luna (all plans), Claude Sonnet 5.5 and GPT-6.1 Sol (Pro). GPT-4o / GPT-4o mini are retired (OpenAI shuts them down on 14 Jan 2027); assistants using them move to GPT-6 Luna.
* Prebuilt assistants get 4096 max tokens so code answers are not cut off.
* Users no longer see tokens, model prices or provider/technical errors anywhere; they see only their balance and what each reply deducted. Admins see full details (Users → Credit history, Pricing Calculator).

= 1.5.0 =
* Credits are now charged by tokens: each model has "credits per 1,000 input tokens" and "credits per 1,000 output tokens". Existing models get prices derived from their old per-message cost.
* Every reply shows the credits and input/output tokens it used; credit history shows each charge.
* No credits are charged when the AI fails; a reply that costs more than the remaining balance takes the balance to zero (never negative).
* Payments are display-only: users see JazzCash / EasyPaisa / bank details, contact number and instructions; admins add credits or assign plans from the Users page.
* Removed the experimental shadow/actual billing engine (quotes, reservations, reconciliation, pricing approvals), the payment submission/approval flow, the inactive model catalog drafts, the unused "lovable" protocol and the duplicate OpenRouter key field. Existing data is kept in the database.

= 1.4.2 =
* Fix: OpenAI requests now use max_completion_tokens and skip temperature for o-series / GPT-5+ models (these were rejected with HTTP 400).
* Fix: Anthropic temperature is clamped to 0-1; base URLs entered with /v1 now work.
* Fix: Gemini key sent in a header; thinking models get enough output budget to answer.
* Fix: Custom/OpenRouter models fall back to the saved Custom Base URL and the Settings-page OpenRouter key.
* Fix: Provider errors now explain the cause (bad key, unknown model, quota, rate limit); admins also see the provider's own message.
* Fix: Model activation is no longer blocked by Billing Controls unless "actual" billing is enabled.
* Fix: Duplicate billing hooks removed; chat requests extend the PHP time limit; provider timeout raised to 120s.
* New: "Test" button for each model under AI Providers sends a real prompt and shows the exact result.

= 1.4.1 =
* Complete standalone installation package with WordPress and PHP requirements, license and setup guide.
* Include all billing tables in explicit opt-in uninstall cleanup.


= 1.4.0 =
* Exactly 20 one-time signup credits; no recurring Free or payment-trial grants.
* Additive precise billing wallets, immutable ledger, reservations and versioned pricing.
* Provider usage evidence, protected legacy balances and reconciliation controls.
* Atomic duplicate-reference payment safeguards and compensating refunds/reversals.
* Production actual usage charging and new model activation remain approval-gated.


= 1.3.1 =
* Integrated the approved public landing page, self-hosted Poppins, responsive typography, assistant marquee and motion assets.
* Retained existing authenticated workspace, assistant management, authentication, API and database implementations.

= 1.3.0 =
* Added instant trial credits (50 credits) on first payment proof submission for free-plan users.
* Trial credits are automatically reverted if the payment is later rejected.
* Added `trial_credits_granted` and `trial_credits_reverted` columns to the payments table.

= 1.0.0 =
* Initial release - full WordPress-native implementation.

== Requirements and setup ==

WordPress 6.0 or later, PHP 8.0 or later (64-bit), and MySQL/MariaDB with InnoDB are required. Use HTTPS. No Node.js, Composer, Supabase, or separate frontend build is required on the server. See INSTALLATION.md for configuration and update instructions.

AI generation requires your own valid provider API key and provider account; provider charges are separate. Payments are manual: users pay you directly and an administrator adds credits or assigns plans. Credits are charged per input and output token at the prices set for each model.

== External services and privacy ==

When a user requests AI generation, the configured provider receives the model identifier, conversation content, assistant instructions and relevant request parameters. Provider endpoints depend on the administrator's model configuration. Configure only providers whose terms and privacy policy you accept. API keys are stored in WordPress options; restrict administrator and database access and protect backups.

Common provider service policies:
* OpenAI: https://openai.com/policies/terms-of-use/ and https://openai.com/policies/privacy-policy/
* Anthropic: https://www.anthropic.com/legal/commercial-terms and https://www.anthropic.com/legal/privacy
* OpenRouter: https://openrouter.ai/terms and https://openrouter.ai/privacy
* Groq: https://groq.com/terms-of-use/ and https://groq.com/privacy-policy/
* Google Gemini: https://ai.google.dev/gemini-api/terms and https://policies.google.com/privacy

Optional Google sign-in redirects the user to accounts.google.com and exchanges an authorization code with oauth2.googleapis.com; www.googleapis.com returns the user's profile/email for the WordPress account. This requires an administrator-configured Google OAuth client. Google policies: https://policies.google.com/terms and https://policies.google.com/privacy.

WordPress stores accounts, conversations, projects, credit history in the site's database. Deactivation retains data. Uninstall also retains data by default; enabling coachpro_delete_data_on_uninstall permanently removes plugin tables, settings, plugin user metadata and plugin-created pages. Back up before enabling this option.

== Bundled licenses ==

Plugin code: GPLv2 or later; see LICENSE.txt. Font and icon licenses are included under public/fonts/ and public/LUCIDE-LICENSE.txt.
