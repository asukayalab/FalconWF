# Page-template assignment — alpha.19

Owner UI/application: Admin/PageDesign.php, reached through Dashboard pages render and save_page_design action. Runtime storage and template rendering remain owned by WordPress. No second mapping table/option and no new agent tool.

## Read chain

Dashboard capability → WP_Query Page list (20/paged; published, draft, pending, private, future; per-object edit check) → active WP_Theme get_page_templates → native _wp_page_template → edit/permalink/preview links.

Only templates installed in the active theme and registered for Page are selectable; an installed inactive project does not expose templates here. Named PHP templates use native selection; this is not a block/FSE template management UI. front-page.php and the rest of WordPress hierarchy still take precedence where applicable.

Missing assignments remain visible as disabled unavailable entries; reads never reset them. Default is an explicit valid selection, not an automatic migration. No Page creation, role resolver or menu/homepage changes.

## Write chain

Dashboard action capability fwf_manage_system → check_admin_referer → audit start → PageDesign::save → native metadata → audit result + one-shot notice + redirect to list page.

Save also checks per-object edit_post, numeric Page ID, supported status/type, scalar template/revision, catalog membership, safe filename and located file, confirmation=yes, revision, native edit-lock and shared Packages/Lock per Page. Revision binds native content/title/excerpt/slug/parent/status/modified timestamp, current assignment, active stylesheet/theme version and Page template catalog. Rechecked inside own lease.

Existing metadata uses previous value in update_post_meta; absent metadata uses unique add_post_meta. Save verifies persisted assignment. Unchanged default is a successful no-op. No content repository mutation is needed because only native presentation assignment changes; title/body/URL/status/modified/other custom values stay untouched.

## Concurrency and limitations

One Apply per form, not an atomic multi-page batch. Native editor and this screen share assignment storage. Other-user native editor lock blocks dashboard Apply. Previously rendered forms are rejected after known content/template/theme changes.

Own lease serializes Falcon writers; native/third-party writers do not all participate in it. This is not a universal transaction with native editors, metadata hooks or filesystem theme updates. Do not claim conflict-free arbitrary concurrent external writes. Theme version/catalog changes invalidate forms; editing file bytes without changing metadata/version is not a supported deployment workflow.

## Evidence owners

- tests/page-design.php: actual named template files, native resolution and bidirectional assignment, preservation, revisions/edit-lock/capability/invalid target, missing template/default and pagination.
- tests/page-design-http.test.mjs + owned fixture: actual dashboard form, nonce, Apply, frontend render, stale form/native update and foreign template rejection; cleanup owned Pages/templates/users.
- tests/admin.test.mjs: screen render, missing nonce, editor screen/action denial.
- scripts/local.mjs includes both new suites in isolated integration chain with existing site-settings restoration.
- guides/PAGES-DESIGN.md is the user flow; ignored handbook carries portable reference/status. Local evidence is not hosted acceptance.
