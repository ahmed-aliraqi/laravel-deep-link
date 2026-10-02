# Changelog

All notable changes to `laravel-deep-link` are documented in this file.

## v1.0.0 - 2026-10-03

Initial release.

- Dynamic `apple-app-site-association` and `assetlinks.json` verification files, served from config and returning `404` until configured.
- `DeepLink` manager and facade: share, scheme and `intent://` URL builders, platform detection, store URLs and Smart App Banner id.
- `DeepLinkable` contract and `HasDeepLink` trait for shareable models.
- Fluent `Landing` responsable: Open Graph/Twitter tags, Smart App Banner, open-app-or-store fallback script, per-platform behaviour.
- Publishable config, landing view and translations (English and Arabic).
- Integration guides for Flutter, native Android, native iOS and React Native.
