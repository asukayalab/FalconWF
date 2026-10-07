# Contract konten Alpha.2 — builder revision

`Content/Definitions.php` owns persistent validated definitions in `fwf_builder` (types, groups, taxonomies). `Content/Schema.php` owns value validation/defaults/meta registration/revisions/MCP field inventory. Forms, Repository, scoped agent tools and FT consume these owners; they do not maintain parallel field lists. Protected `_fwf_<key>` post meta survives disabling/removing definitions.

## Builder

- Jenis Konten: name, immutable `fwf_` key (max 20 characters), URL slug, active flag, Posts-like nonhierarchical/archive or Pages-like hierarchy/parent/order.
- Field Groups: locations include Posts, Pages and custom types. Eight field kinds: text, textarea, integer, select, date, URL, image attachment ID, relationship post ID. Label/key/help/default/required/public visibility; choices for select and target for relationships.
- Field rows add/duplicate/remove/order using drag or keyboard buttons. Keys auto-fill until manually edited. Removing a field does not purge existing metadata. Saved kind/relationship target contracts cannot silently change; use a new key.
- Kategori & Tag: custom WordPress taxonomy, category hierarchy or tag behavior, locations across selected types, unique slug. Terms managed in native WP screens.
- Urutan Menu: site setting affects order, retains native capability filtering; supports drag/buttons/reset. New menus append after saved order.
- Definition saves require `fwf_manage_modules`, nonce, expected definition revision and writer lock. Value saves require native `edit_post`, object nonce and metadata/definition revision.

Initial builder migration creates Project, editable Detail Project fields (cover_image/location/project_year/project_stage), and Kategori Project. Project admin navigation is position 21, after Pages. Publications/Learning retain compatibility descriptors/presets for legacy data/extensions and regression checks, but are not defaults, not shown in builder/modules, and not seeded by new demo runs. Initial transition activates only Project without deleting legacy posts/meta/media.

## Persistence and AI

Native title/body/summary and custom typed values use Repository. Dynamic create validates required fields; partial agent edits validate supplied fields. Agent tools remain draft-only, require grant per action/type/object/field and current revision. `describe_schema` is read-only and returns only permitted type/fields. It grants no structural mutation or publish/delete/system access. Outbound provider remains selected title/body/summary; arbitrary custom-field generation from dashboard and account OAuth are not implemented.

Metaboxes live in native WordPress edit screens, including auto-draft, pending/private/scheduled human content. Custom metadata validation and conflict detection are separate from WordPress main-content saving. **A failed field validation does not roll back an already saved native title/body/status**; notice explicitly states field values were not applied. Full atomic main-content+metadata validation/publish gating remains follow-up work. Agent Repository mutation and native revisions share validators and persistence checks. Multi-step writes are not a universal DB transaction; post-write audit/metadata failure is reported for review, never falsely as success.

Integer values are integers (form strings converted at human boundary), nonnegative; preset limits persist on group edit. URLs require HTTP(S) without credentials. Dates require valid YYYY-MM-DD. Media/relationships require valid targets, no password, allowed status, and edit permission for unpublished references. Media picker is currently a dropdown of 100 newest items, not modal/search/repeater. Field groups have a 100-field limit. No repeater, rich text, decimal, conditional-field logic or taxonomy-valued field kind yet; classifications use taxonomy builder/native term UI.

Fields are hidden from native REST metadata. New custom fields default to private visibility. Public Falcon content REST and FT detail honor visibility; authenticated editors/authorized scoped agents retain their configured access. AI discovery is a union for request typing plus scoped per-type description; runtime rejects cross-type unknown fields.

## Listing

`[falcon_listing type="fwf_project" limit="12"]` shows all public Project content. Filter by category with `[falcon_listing type="fwf_project" taxonomy="fwf_project_cat" term="bangunan" limit="12"]`. Query excludes drafts and password-protected posts, verifies taxonomy/type association, bounds limit to 1–50, and escapes output. Shortcode generator is available in Content Builder → Listing with a 1–50 limit input and asynchronously loaded term checkboxes restricted to the chosen type. Comma-separated term slugs use OR; `term="*"` matches any assigned term in that taxonomy (including future terms), excluding unclassified objects. Check all uses this wildcard, while uncheck all disables copying until a selection exists. Term choices paginate at 200 per fetch, require builder capability and nonce, and never accept unrelated taxonomy/type pairs. Specific selections are capped at 200. This first iteration provides a fixed card layout, not a visual query/block layout editor or meta-field filter builder.

Local-only idempotent seed creates/preserves one fictional Project plus category Bangunan and page Karya Bangunan. It preserves human edits and existing old examples and does not change homepage. Example source and images stay outside installer runtime.

Evidence: tests/builder.php, tests/admin.test.mjs, tests/content.php, tests/mcp.test.mjs, tests/frontend.test.mjs, tests/demo.test.mjs. Mock provider/updater checks do not certify live API/ChatGPT connections. WordPress APIs: https://developer.wordpress.org/reference/functions/register_post_type/ and https://developer.wordpress.org/reference/functions/register_taxonomy/.

## Builder interactions

New type/group forms provide cancel links; existing groups provide Batal Perubahan. Cancel navigates to the list without posting. Field rows and native menu order use pointer drag handles (mouse/touch implementation) with arrow alternatives. Open/close-all and location check/uncheck-all only affect current unsaved form state. Field order is derived from form DOM order at submit. Actual touchscreen/Safari acceptance remains pending. AI setup and current limits are documented in guides/AI-CONNECTIONS.md.
