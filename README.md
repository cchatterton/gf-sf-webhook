# GF SF Webhook

GF SF Webhook is an AlphaSys WordPress plugin that sends mapped Gravity Forms submissions to a configured Salesforce webhook.

## Features

- Adds a Salesforce mapping key to Gravity Forms fields.
- Adds a per-form Salesforce webhook URL setting.
- Builds structured payloads for standard, compound, and checkbox fields.
- Records payload and delivery notes against Gravity Forms entries.
- Supports nonce-protected manual resend from the entry screen.
- Delivers updates through native WordPress plugin update controls from GitHub releases.

## Requirements

- WordPress 6.0 or later.
- PHP 8.1 or later.
- Gravity Forms installed and active.

## Installation

1. Download `gf-sf-webhook.zip` from the latest GitHub release.
2. In WordPress, go to Plugins > Add New > Upload Plugin.
3. Upload the ZIP, install it, and activate GF SF Webhook.
4. Configure the Salesforce Webhook URL in each Gravity Form's settings.
5. Optionally assign Salesforce Mapping Keys in each field's advanced settings.

## Release packaging

Run `scripts/build-plugin-zip.sh` from any directory. It creates both `dist/gf-sf-webhook.zip` and the committed root `gf-sf-webhook.zip`.

## Tests

Run the payload regression checks with `php tests/payload-checkbox.php` and `php tests/payload-time.php`.

## External services

Submission data is sent to the webhook URL configured by a site administrator. When that URL is a Salesforce endpoint, Salesforce's terms and privacy policy apply. The updater checks public files and release metadata hosted by GitHub. See the packaged `readme.txt` for full disclosure.

## License

GPL v2 or later. See `gf-sf-webhook/LICENSE`.
