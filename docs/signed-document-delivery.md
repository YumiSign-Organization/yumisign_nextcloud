# Signed document delivery

The personal settings page embeds the bundle's `SignedFolderSettings` component. The app controller is a route adapter for the bundle controller. Both paths are stored through `IUserConfig`, per app and per user, and default to the XML values exposed by the bundle configuration service. Reading or saving settings does not create either destination.

`SignedFolderService` validates both XML defaults and both effective paths. Empty or invalid values block signature preparation/submission and destination writes until corrected. Existing files are not removed to make room for folders. Paths are resolved again when an outstanding delivery is attempted.

YMS only submits globally signed workflows to `SignedDocumentService`. The adapter downloads the final documents and identifies the recorded applicant and local recipients; the bundle handles role destinations, file creation, collision suffixes, delivery receipts and Files refresh signals. External recipients have no local copy. Identical applicant/recipient destinations for one user share a single copy.

The original document is left in place. The applicant result is saved only in the configured applicant destination. The existing `overwrite` setting now governs collisions in the signed destination: disabled means `_2`, `_3`, etc.; enabled permits replacement of an existing signed destination file. The suffix format and starting number are bundle constants.

## Recovery

The app's private Nextcloud AppData holds four `signed-delivery-*` areas:

- `sources`: retrieval metadata for globally successful workflows whose final result has not yet been retained locally;
- `content`: downloaded document bytes retained until all local deliveries succeed;
- `pending`: per-document, per-user and per-role delivery progress, including interrupted attempts;
- `completed`: small receipts retained to handle repeated callbacks without duplicate files.

The background task retries retrieval and pending deliveries independently of transaction expiry. A corrected destination is used for outstanding writes, while completed writes are not repeated or relocated. Once every delivery succeeds, temporary bytes and pending state are removed. Failed writes retain their state and bytes for a later attempt. A prior checkpoint is kept to recover interrupted JSON writes.

Shared locking serializes each workflow delivery and RCDevs writes to the same physical destination folder. The Files signal is written only after the user's file has been saved. The shared browser watcher polls every 10 seconds while visible and also checks when the tab becomes visible again.

## Verification

The automated tests use disposable Nextcloud users and real storage for lazy creation, role separation, nested custom destinations, suffixes, overwrite behavior, dual-role deduplication, interrupted checkpoint recovery, retry at a changed path, and failure between writing and refreshing. Provider-adapter tests reject partial workflows and exclude external recipients. Controller tests enforce the session user; JavaScript tests cover settings and Files refresh.

This implementation uses existing Nextcloud AppData and user preferences, so no database schema migration is required. Production JavaScript must be rebuilt with `npm run build`; the repository ignores compiled `js/` assets. New PHP bundle classes require the normal Composer autoload generation (`composer dump-autoload`).
