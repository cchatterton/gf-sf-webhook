=== GF SF Webhook ===
Contributors: alphasys
Tags: gravity forms, salesforce, webhook, integration
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.3
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends mapped Gravity Forms submission data to a Salesforce webhook and records delivery notes on each entry.

== Description ==

GF SF Webhook adds Salesforce mapping keys to Gravity Forms fields and a Salesforce webhook URL to each form. On submission, it creates a structured payload, records the payload against the entry, and sends it to the configured endpoint through a signed internal relay.

Standard, compound, and checkbox fields retain the plugin's established payload behavior. Administrators with Gravity Forms entry-editing permission can manually resend a payload from the entry screen.

Gravity Forms must be installed and active.

== Installation ==

1. Upload `gf-sf-webhook.zip` through Plugins > Add New > Upload Plugin.
2. Activate GF SF Webhook.
3. Open a Gravity Form and configure its Salesforce Webhook URL in the form settings.
4. Optionally configure a Salesforce Mapping Key in each field's advanced settings.

== External services ==

= Administrator-configured webhook =

The plugin sends the Gravity Forms entry ID, form ID, and mapped submission values to the webhook URL configured by a site administrator. Data is sent after a form submission and when an authorised administrator manually resends an entry. The destination operator's terms and privacy policy apply. For Salesforce endpoints, see https://www.salesforce.com/company/legal/ and https://www.salesforce.com/company/privacy/.

= GitHub =

The plugin checks public release metadata hosted by GitHub to provide native WordPress updates. These requests include the site's IP address as part of normal internet communication and a user-agent containing the plugin version. No form submission data is sent to GitHub. GitHub terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service. GitHub privacy statement: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement.

== Changelog ==

= 1.1.3 - 2026-08-31 =

* Fixed Time fields being omitted when Gravity Forms stores the submitted time against the main field ID rather than separate sub-input IDs.

= 1.1.2 - 2026-08-28 =

* Fixed multi-checkbox payload handling so selections after Gravity Forms' skipped input IDs, including the final choice, are included.

= 1.1.1 - 2026-08-28 =

* Aligned metadata, licensing, documentation, packaging, security checks, and GitHub update delivery with AlphaSys standards.
* Preserved the established submission mapping and webhook workflow.

= 1.1 - 2026-08-05 =

* Used checkbox values rather than checkbox labels in webhook payloads.

= 1.0 - 2026-05-15 =

* Added Gravity Forms entry and form IDs to flat JSON payloads.
