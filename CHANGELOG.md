# Changelog

## 1.1.4 - 2026-09-26

- Replace independent GitHub updater with the guarded AlphaSys controller client.
- Standardise metadata, links, licensing and WordPress 7.0 / PHP 7.4 compatibility.
- Preserve existing functionality, settings, hooks and package identity.

All notable changes to GF SF Webhook are recorded here.

## 1.1.3 - 2026-08-31

- Fixed Time fields being omitted when Gravity Forms stores the submitted time against the main field ID rather than separate sub-input IDs.

## 1.1.2 - 2026-08-28

- Fixed multi-checkbox payload handling so selections after Gravity Forms' skipped input IDs, including the final choice, are included.

## 1.1.1 - 2026-08-28

- Aligned plugin metadata, licensing, documentation, packaging, and GitHub update delivery with AlphaSys standards.
- Added capability checks, URL validation, and signed internal relay requests without changing the submission payload or webhook workflow.

## 1.1 - 2026-08-05

- Used checkbox values rather than checkbox labels in webhook payloads.

## 1.0 - 2026-05-15

- Added Gravity Forms entry and form IDs to flat JSON payloads.
