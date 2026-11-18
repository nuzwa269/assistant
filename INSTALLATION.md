# CoachPro AI Assistant 1.4.1

## Install

1. Back up your WordPress files and database before replacing an existing version.
2. Open WordPress Admin > Plugins > Add New > Upload Plugin.
3. Select coachpro-ai-assistant.zip, install it, and activate CoachPro AI Assistant.
4. Open CoachPro AI in the admin menu and configure provider API keys and models. Never paste keys into public pages.
5. Review the plugin-created pages. Use [coachpro_landing] for the public landing page and [coachpro] for the application if creating pages manually.
6. Configure plans and manual payment details, then verify registration, login, a test conversation and payment review with test users.

## Hosting

WordPress 6.0+, PHP 8.0+ on a 64-bit server, and MySQL/MariaDB with InnoDB. PHP must support JSON and HTTPS requests through the WordPress HTTP API. Use HTTPS and allow outbound access to your selected provider. All application PHP, JavaScript, CSS, fonts and licenses are bundled; no server-side npm or Composer install is needed.

## AI, payments and optional login

Supply your own provider credentials and configure a valid model for that provider. Provider fees are separate from plugin credits. The plugin supports administrator-reviewed manual payments; configure JazzCash, EasyPaisa or bank details as applicable. Google login is optional: configure your Google client and use the callback URL shown in plugin settings. WordPress login works independently.

Credits are charged by tokens. Set each model's "Input credits per 1,000 tokens" and "Output credits per 1,000 tokens" under CoachPro AI → AI Providers. Payments are manual: enter your JazzCash / EasyPaisa / bank details under CoachPro AI → Settings; after receiving a payment, add credits or assign a plan under CoachPro AI → Users.

## Update and data

Upload this complete ZIP through WordPress and choose to replace the installed copy. Keep the folder name coachpro-ai-assistant. Do not delete the installed plugin to perform an update. Back up first and verify custom settings after updating.

Deactivation and uninstall retain data by default. Explicitly enabling the coachpro_delete_data_on_uninstall WordPress option makes uninstall permanently delete plugin data and plugin-created pages. Only enable this when deliberately removing the service after a backup.

## Troubleshooting

For AI errors, verify the API key, model name, provider quota, base URL and outbound HTTPS connectivity. For REST errors, resave Settings > Permalinks and check security/cache exclusions for /wp-json/coachpro/v1/. Exclude logged-in application pages from full-page caching. Check the WordPress debug log privately; keep display_errors disabled publicly.

The ZIP is a complete installation package. Hosting-specific behavior and live provider/payment credentials must still be verified on your staging website. WordPress.org directory approval is a separate review.
