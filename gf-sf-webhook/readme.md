# GF SF Webhook

Author: AlphaSys
Version: 1.1.1
Status: Production

## Purpose

Sends mapped Gravity Forms submission data to a form-specific Salesforce webhook.

## Key features

- Per-field Salesforce mapping keys.
- Per-form webhook URL configuration.
- Structured handling of standard, compound, and checkbox values.
- Payload and delivery notes on Gravity Forms entries.
- Manual, nonce-protected payload resend.
- Native WordPress updates from GitHub releases.

## Configuration

Gravity Forms must be installed and active. Configure the webhook URL in a form's Salesforce settings and optional mapping keys in each field's advanced settings.

## External services

The plugin sends form and entry IDs plus mapped submission values to the administrator-configured webhook URL. It also checks GitHub for stable release metadata. Details and policy links are provided in `readme.txt`.
