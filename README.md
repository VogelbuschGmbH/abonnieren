# Abonnieren

Abonnieren is a Nextcloud 34 app for object-based email subscriptions. A user can subscribe to an individual file or folder and select notifications for download, upload, modification and deletion. Folder subscriptions can optionally include descendants.

## Features

- Files sidebar tab **Subscribe**
- Overview **My subscriptions**
- One rule per user and file/folder node
- Stable node IDs, so rules survive rename and move operations
- Optional recursive folder rules
- Suppression of the subscriber's own authenticated actions
- Deduplication of overlapping direct and recursive rules
- Public-link downloads, uploads, modifications and deletions
- Complete folder ZIP download notifications
- OCS endpoint for trusted clients such as NextcloudShare

Event mask: upload `1`, modification `2`, deletion `4`, download `8`.

## NextcloudShare API

```text
POST /ocs/v2.php/apps/abonnieren/api/v1/share-notifications
```

Parameters:

- `shareId`: numeric OCS share ID
- `eventMask`: value from `1` through `15`

Authentication uses the user's normal Nextcloud app password. The app verifies that the public link belongs to that user, resolves its shared node and creates or replaces the corresponding object-wide rule. The share ID is not stored in the rule.

## Development

```bash
pnpm install --frozen-lockfile
pnpm build
composer install
composer lint
```

Release archives must contain a single top-level folder named `abonnieren`.

## License

AGPL-3.0-or-later
