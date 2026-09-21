# Changelog

## 1.1.0 - 2026-09-21

### Fixed

- Send modification emails when a file is edited through a public share link
  (including Nextcloud Text on `/s/…`). File changes stay in the native Files
  activity feed so they are not listed twice.
- Resolve public, email and internal shares the same way for download, upload,
  modification and deletion, including public DAV paths that are not mounted as
  shared storage.

### Changed

- Download subscriptions now notify when a file is opened (Viewer, Text,
  Collabora, public link or Files/WebDAV), not only after an explicit download.
- Debounce all notification categories (download, upload, modification,
  deletion) for 10 minutes per actor and file.

## 1.0.6 - 2026-09-21

### Changed

- Fill App Store metadata in `info.xml`: SPDX licence, bugs URL and repository.

## 1.0.5 - 2026-09-17

### Added

- Record a Files activity entry on the downloaded file or folder when another
  user downloads it. The file owner, the sharer and members of the admin
  group receive the entry. Public link downloads stay with the existing
  sharing activity so they are not listed twice.

## 1.0.4 - 2026-09-16

### Added

- OCS share-notification endpoints accept user and group shares in addition to
  public link shares, so internal shares can enable the same object rules.

### Fixed

- Send a single download email when a shared file is actually downloaded.
  Opening the file in the Viewer, previews and media playback are ignored.
  The Files menu still probes with DAV HEAD before GET; that probe is not a
  download either.

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
