# Archived Reviews

These reports preserve historical review evidence. Refer to each section for its reviewed scope and archive status.

<a id="codex-review-10-01-2026"></a>

## Codex Code Review

Archived: 10/02/2026. Review date: 10/01/2026. Historical review retained after subsequent search, navigation, styling, tooltip, and image-description changes extended the code beyond the reviewed scope. Archiving does not certify those newer changes or imply a fresh full-codebase review.

Date: October 1, 2026<br>
Repository: `phpbbmodders/documentation`<br>
Reviewed commit: `4e214afcb610c03472ad277170cbe851de3cece9`<br>
Verdict: **NO_OUTSTANDING_FINDINGS**

### Scope

The review covered the current extension source, including unchanged files,
followed by runtime checks on a disposable copy of phpBB 3.3.19 with SQLite
and PHP 8.4.26. The extension and its documentation build were copied into
the test board. This report includes follow-up verification after the
stylesheet include, ACP menu depth, imported route URLs, image delivery,
fallback-language authorization, purge cleanup, and PHPUnit discovery were fixed.

The requested Hugo build was run for all languages, with its destination
changed to the disposable extension's `docs-build/` directory. Browser
checks were repeated after the build completed.

### Findings

No outstanding findings remain from this review. Coverage limits are listed
below; this is not release certification.

### Correction To The Initial Review

The initial navigation-label stored-XSS finding is withdrawn. phpBB's request
API HTML-escapes ACP input before storing it. The actual ACP form was used
to save `en=<script>window.reviewXss=1</script>`. Chromium displayed the script
as literal text, and `window.reviewXss` remained unset. Injecting raw values
directly into configuration would not reproduce the settings-input boundary.

### Follow-Up Fixes

The stylesheet include, ACP menu-depth, imported route URL, image-delivery,
fallback-language authorization, purge-cleanup, and PHPUnit-discovery findings
have been removed after their fixes were verified. The original commit identifies
the baseline; these fixes are subsequent working-tree changes.

The new [m3_acp_category.php](../../../migrations/v10x/m3_acp_category.php)
migration creates a Documentation category beneath Extensions and moves
the existing Settings module into it. Fresh install and upgrade passed on
the disposable phpBB 3.3.19 SQLite board. The upgrade preserved the module's
ID, enabled flag, and display flag. Purge removed both the Settings module
and its new category. The older migrations were not changed.

Chromium found exactly one Documentation settings link in the ACP sidebar,
under Documentation. Clicking it opened the settings form. The documentation
stylesheet returned HTTP 200; computed styles showed a desktop grid with
a 260-pixel sidebar and a single-column mobile grid. At a 390-pixel viewport,
the document width was also 390 pixels. PHP syntax and diff checks passed.

Imported Hugo links now use phpBB's controller route helper, including links
to other build languages. Sidebar permissions are filtered against the
original Hugo paths before URLs are rewritten. Image fallback resolution
also runs before rewriting so it does not depend on the board's URL prefix.
Queries and anchors are retained; external and relative links are unchanged.

The helper suite now passes 25 tests and 66 assertions when invoked directly.
Regression cases cover the web root, subdirectories, `app.php`, rewritten
URLs, query strings, anchors, sidebar filtering, and image fallback handling.
Chromium navigation passed at both `/app.php/documentation/...` and
`/forum/app.php/documentation/...`: sidebar links loaded the language root
and Quick Start section successfully.

Local article images now use the `/documentation-image/{lang}/{path}` route,
generated with phpBB's route helper. The route checks both the source-page
and asset-language permissions, the source section permission, and whether
the article references that image. Files must remain inside the selected
language directory; traversal and escaping symlinks are rejected. Supported
raster images are served with their detected MIME type, `nosniff`, and
`private, no-store`. Direct access to `docs-build/` remains blocked.

The explicit controller suite passes 39 tests and 85 assertions, including
permission denials, fallback images, unreferenced files, non-image files,
traversal, escaping symlinks, disabled documentation, and a missing build.
Chromium loaded both installation screenshots at the web root and beneath
`/forum`, with and without the page's trailing slash, and at a 390-pixel
viewport. Image requests returned HTTP 200 with `image/png`; the response
bytes matched the source PNG. An unrelated source-page query returned 404.

The controller now selects the language to serve before loading the article.
Missing-page fallback checks the fallback language's ACL and recalculates
allowed sections for that language. The loader cannot silently substitute
another language after authorization. Page paths must stay within the
selected language directory; traversal and cross-language symlinks are
rejected. The requested-language cookie and fallback notice are preserved.

The controller suite now passes 49 tests and 108 assertions. New cases cover
denied and permitted fallback, actual-language sidebar filtering, section
availability, missing pages, existing translations with fallback access
denied, and language-directory containment. On a disposable phpBB 3.3.19
SQLite board, the real guest ACL allowed Danish and denied English. Removing
the copied Danish installation page produced a login redirect with a return
URL rather than an English article. No source-board data was changed.

The purge hook now enumerates extension-owned ACL options from the database,
including languages and sections absent from the current build. phpBB's
permission-removal tool deletes their user, group, and role grants and clears
ACL caches. Purge also removes the pending permission-sync flag. Disable
continues to preserve permissions and grants. Applied migrations were not
changed.

A SQLite regression exercises the real purge hook and phpBB permission tool
with stale language and section permissions, denied grants, dual-scope ACL
options, and unrelated options. It verifies cleanup across all four ACL
tables, preservation on disable, unrelated-option preservation, cache
invalidation, and repeated purge. The full suite passes 66 tests and 147
assertions with explicit `_test.php` discovery. This regression uses an
isolated database fixture; the full board reinstall cycle was not rerun.

The PHPUnit configuration now discovers `_test.php` files and loads the
committed `tests/bootstrap.php`. The bootstrap loads phpBB's Composer
dependencies and the core and extension classes. It detects the standard
installed extension layout or accepts `PHPBB_ROOT_PATH` for standalone
checkouts. An unavailable phpBB installation produces an explicit error.
The configured suite, without command-line suffix or bootstrap overrides,
passes 66 tests and 147 assertions. Installed-layout detection and the
missing-root error were also checked. The README documents both invocations.

### Validation Results

| Check | Result |
| --- | --- |
| PHP syntax | All 17 current PHP files passed after the fallback fix. |
| PHPUnit 9.6.37, explicit test file | 20 tests and 36 assertions passed. |
| Follow-up controller suite, explicit `_test.php` discovery | 39 tests and 85 assertions passed. |
| Fallback-fix controller suite, explicit `_test.php` discovery | 49 tests and 108 assertions passed. |
| Configured PHPUnit discovery, after fix | 66 tests and 147 assertions passed with the committed bootstrap and suffix. |
| Fresh extension enablement | Passed on a board without a documentation installation. |
| Next-request permission sync | Registered 14 permissions and cleared the pending flag. |
| Repeated permission sync | No additions and no duplicate permission options. |
| Disable/re-enable | Passed. |
| Disable/purge, initial review | Commands passed; ACL cleanup failed before the purge fix. |
| Purge-fix SQLite regression and affected suite | 66 tests and 147 assertions passed; owned options and grants removed, unrelated ACLs preserved. |
| Staged migration upgrade | Installed m1 alone, restored m2, and ran `db:migrate`; m2 completed and enabled config was present. |
| Six language quickstart pages | Danish, German, formal German, English, French, and Italian returned HTTP 200 with article markup. |
| Language-free and unknown-language routes | HTTP 302 to a known language. |
| Missing page | HTTP 404. |
| Master switch disabled | HTTP 503. |
| Missing documentation build | HTTP 503. |
| Missing-build notifications | Sync created one founder notification; restoring the build and resyncing removed it. |
| ACP authentication and settings save | Passed. |
| ACP hostile navigation label | Rendered as literal text; no script execution. |
| Desktop and mobile browser checks | Article rendered at 1440x1000 and 390x844; mobile document width was 390; no page JavaScript errors. |
| Sidebar navigation | Passed after route URL fix; see Follow-Up Fixes. |
| Documentation images | Passed after protected image-route fix; see Follow-Up Fixes. |
| Missing-page language authorization | Signed-in denials and permitted fallback passed regression tests; real guest denial redirected to login. |
| Composer validation, initial static pass | Valid, with warnings about the version field and unbounded phpBB requirement. |

The requested build was executed as:

```bash
bash /home/william/Desktop/repos/phpbbdocs-hugo/phpbbdocs_hugo.sh all /tmp/documentation-review-board/ext/phpbbmodders/documentation/docs-build/
```

Hugo 0.165.0 completed with 131 pages each for English and Danish, and 129
pages each for French, formal German, German, and Italian. It reported 289
static files per language. The initial sandboxed attempt failed because
Snap Hugo could not create its runtime directory; the host-permission retry
succeeded. Rendering checks were repeated after that successful build.

### Coverage Limits

Runtime coverage was phpBB 3.3.19, PHP 8.4, SQLite, and Chromium. Other PHP
and phpBB versions, MySQL/PostgreSQL, production nginx/Apache rewrite rules,
other board styles, and every generated page were not tested. Upgrade
coverage used a staged m1-to-m2 fixture, not a historical production backup.
The separate developer-documentation generator was not run; development
pages were retained from the copied build.

These results support the findings above, not release certification. The
initial disposable localhost server was stopped after verification. The
earlier port-8131 test harness is no longer available; fallback validation
used a fresh disposable board copy and CLI bootstrap. No findings remain
outstanding from this review. The GitHub Actions workflow was not rerun
during the PHPUnit configuration fix.
