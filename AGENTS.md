# Falcon WF development

- Work locally in this repository. Read engineering/STATUS.md and engineering/DECISIONS.md before changing code.
- Product: FP + FT, one installer/menu, local runtime, per-component versions, per-installation data. Rizal is a separate pilot.
- Both AI directions are mandatory for 0.1. Never count mock tests as real provider/client acceptance or label a prerelease complete.
- Keep one owner for schema, permissions, manifests and versions. Fix causes, remove replaced logic, verify complete runtime chains.
- Preserve content on disable/deactivation/uninstall; never switch themes without an explicit human action.
- Enforce capabilities/nonce at admin boundaries, scope/revisions at agent boundaries. No AI publish/delete/system/update tools.
- No credentials, database/uploads, reference docs or session notes in Git or release ZIPs.
- docs/ and session-notes/ are local only. New session notes go only in session-notes/YYYY-MM-DD-NN-topic.md (WIB). engineering/ contains implementation contracts and evidence, not session transcripts.
- Run npm run verify after changes. Runtime tests use isolated Docker WordPress through npm run test:integration. Build output is generated; edit packages/ sources.
- Do not create a GitHub repository or production deployment without an identified owner/target. No guessed account access.
