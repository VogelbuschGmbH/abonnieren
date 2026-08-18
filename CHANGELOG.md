# Changelog

## 1.0.3 - 2026-08-17

### Changed

- First migration creates only `abonnieren_object_rules` and no longer imports
  rules from VB App.
- Require PHP 8.2, matching Nextcloud 34.
- Renamed the phpunit suite and removed unused constructor and helper code.

## 1.0.2 - 2026-08-12

### Fixed

- Load the Files initialization script without depending on internal Files
  classes that are unavailable during some Nextcloud 34 bootstrap sequences.
- Register the subscription tab in every active `@nextcloud/files` v4 scope so
  it is visible when the app bundle and the server use different minor scopes.

## 1.0.1 - 2026-08-12

### Fixed

- Allowed the read-only app start page to load without a CSRF failure.
- Registered the Files sidebar tab with the string display name required by
  `@nextcloud/files` 4.0.

## 1.0.0 - 2026-08-12

### Added

- Standalone file and folder subscription app for Nextcloud 34
- Files sidebar integration and subscription overview
- Download, upload, modification and deletion notifications
- Object-wide OCS integration for NextcloudShare
