# Distribusi dan pembaruan — alpha.23

## Owner dan alur

UpdateManager owns core source/channel/manual tag, manifest validation and reviewed candidate. ProjectManager owns the separate project connection, identity discovery, channel and reviewed project candidate. GitHubClient owns normalized transport/release discovery; shared Verifier and native WordPress upgraders remain the only installation path. Versions remain in release/components.json and each project's project.json.

Core and projects offer stable/latest, testing/latest prerelease (local/staging only) and an optional explicit stable/prerelease tag. Existing project connections without channel retain their explicit tag behavior. A new project connection may leave project/theme IDs blank: save retrieves release metadata, resolves identity and validates the complete project contract before persistence. Explicit supplied IDs constrain discovery. Subsequent checks never silently change identity. Saving a connection invalidates its reviewed candidate and observations.

Human check stores the concrete release, source/selection and checked_at. Apply rediscovers and compares the reviewed identity/tag/package; a newly published release requires fresh human check. Same/older versions are rejected. Project UI hides same/older Apply. ZIP checksum/content verification happens after download at Apply, before file replacement. No content/schema import, theme switch, homepage change or automatic installation is added.

## Periodic observations

Updates/Monitor registers one twicedaily fwf_update_check event (first due in one hour) and admin notices. It calls read-only discovery with the same validators, storing only source, timestamp, safe error and packages in non-autoload fwf_update_observations. It never writes either reviewed candidate or downloads ZIPs. An interrupted read can retry at the next scheduled event; no permanent extra update lease is created. Concurrent metadata observations cannot authorize installation.

Notices require fwf_manage_updates, exact current source and an observation no older than two days. Installed versions are compared at display time, so a completed update removes its own notice without another fetch. Discovery failures store the error and no stale packages. Updates screen shows last periodic result and manual check remains available. WP-Cron is visit-driven; reliable unattended timing needs server cron. Deactivation unschedules the event and preserves installation data. No email, external notification service or AI system tool is introduced.

## Separate project automation kit

`npm run export:project-automation -- /absolute/new-kit-folder` exports tooling and a workflow template. It never overwrites an existing folder or modifies a client repository. Copy .falcon-tooling and .github into the identified separate project repo; project.json/runtime stay at repo root. Add release-notes/VERSION.md. Tool dependencies use the bundled package-lock; copied tooling is versioned with the project commit, without a moving core download.

A main push checks out the exact SHA, builds through the existing project builder and uploads precisely ZIP + project-manifest.json to the package job artifact. Exported builder stages outside project source, excluding only Git/Actions/tooling/dependency directories from runtime discovery. Runtime files still require explicit inventory; output must remain outside source.

The publish job alone has contents:write using GitHub's scoped built-in token. It revalidates project identity/version/compatibility, clean tracked source/expected SHA, notes and ZIP checksum. The shared release publisher creates/verifies a draft, exact commit tag and exact assets/digests before publishing. Existing published releases are untouched; existing drafts/tag collisions/incomplete uploads require operator review. Automatic stable publication stays blocked until release gates are accepted. No WordPress credential/deploy/activation step exists.

Local script tests are separate from real GitHub Actions/distribution evidence in EVIDENCE.md. Source/tooling can be reviewed independently; no client data, DB/uploads, credential, docs or session-notes belong in runtime ZIPs. Backup remains HOLD.
