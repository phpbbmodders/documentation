# Documentation

[![Tests](https://github.com/phpbbmodders/documentation/actions/workflows/tests.yml/badge.svg)](https://github.com/phpbbmodders/documentation/actions/workflows/tests.yml) [![Lint](https://github.com/phpbbmodders/documentation/actions/workflows/lint.yml/badge.svg)](https://github.com/phpbbmodders/documentation/actions/workflows/lint.yml)

Shows the board's Hugo documentation site inside phpBB, styled with the active board style.

## Features

- Serves a built Hugo docs site at `/documentation`, wrapped in the board's own style.
- Language picker: automatic (from the user's language) or manual, with fallbacks and language-code overrides.
- Per-section and per-language permissions, open to everyone by default.
- Navigation links in the board header, and **Resync permissions** when new sections or languages are added.

## Requirements

- phpBB 3.3.19 or later
- PHP 8.0 or later

## Installation

1. Copy the extension to `/ext/phpbbmodders/documentation`
2. In the Administration Control Panel, go to **Customise → Manage extensions**
3. Enable the **Documentation** extension
4. Point `proteus_hugo.sh`'s build output at the extension's own `docs-build/` directory:
   `./proteus_hugo.sh all /path/to/ext/phpbbmodders/documentation/docs-build`
   This is also the default **docs build path** in the settings, so no further ACP configuration is needed unless the build lives elsewhere.
5. Documentation is open to everyone, including guests, by default. Restrict it afterwards through the normal Permissions screens if needed.
6. **Block direct web access to `docs-build/`** on anything other than Apache or LiteSpeed. Without it, the raw pages can be read directly, bypassing the extension's permissions. See [docs/securing-docs-build.md](docs/securing-docs-build.md) for Nginx, Caddy, IIS and lighttpd snippets.

If you add a new top-level section or a new language to the docs build later, use the **Resync permissions** button on the settings page.

### Language code overrides

phpBB and the docs build don't always agree on language codes, regional variants especially (phpBB's `pt_br` vs. a docs build keyed `pt-BR`, for example). Matching codes and broad-language fallbacks are handled automatically; the **Language code overrides** field in the settings covers anything that still doesn't line up.

### External menus

Leave the documentation system enabled and disable both **Show in navigation bar**
settings to hide the built-in links. Documentation remains accessible by URL.
An external Twig menu can use `U_DOCUMENTATION` and `U_DOCUMENTATION_DEVDOCS`,
with `DOCUMENTATION_NAV_TEXT` and `DOCUMENTATION_DEVDOCS_NAV_TEXT` as labels.
These remain available in combined and separate navigation modes, but are empty
when the viewer lacks access, the build is missing, or documentation is offline.
Check the URL before rendering each external menu entry. The
`S_DOCUMENTATION_NAV_VISIBLE` flags control only the built-in navigation.

## Documentation search

**Search documentation** searches titles and page text in the selected language.
Every word must match; matching titles appear first. Results include excerpts and
are limited to 50 pages. The sidebar **Filter** still filters titles only.

Hugo generates a `search-index.json` file per language on each build. Rebuild with
`phpbbdocs_hugo.sh` after changing content. Existing builds without that index
continue to serve pages, but cannot provide full-text search.

The phpBB search endpoint checks language and section permissions before returning
results. Separate navigation limits results to the current documentation side.
Keep raw access to `docs-build/` blocked, including its JSON indexes, as described
in [docs/securing-docs-build.md](docs/securing-docs-build.md).

## Tests

Run PHPUnit 9.6 from the extension directory:

```bash
phpunit -c phpunit.xml.dist
```

The bootstrap finds phpBB automatically when the extension is installed at
`ext/phpbbmodders/documentation`. For a standalone checkout, set the path to
a phpBB installation with its Composer dependencies:

```bash
PHPBB_ROOT_PATH=/path/to/phpbb phpunit -c phpunit.xml.dist
```

The tests use temporary fixtures. The purge regression requires PHP's SQLite3
extension and uses an in-memory database.

## TODO

Open documentation checks and archived reviews: [docs/TODO.md](docs/TODO.md).

## Contributing

Contributions are welcome!

- **Bug reports**: [Open an issue](https://github.com/phpbbmodders/documentation/issues).
- **Everything else** (questions, feature requests, ideas, general discussion): [Use Discussions](https://github.com/orgs/phpbbmodders/discussions), or the [community forum](https://www.phpbbmodders.com/community/).
- Pull requests are welcome for bug fixes or discussed features.

## Acknowledgments

- Code review, bug fixes, and documentation assisted by [Claude](https://www.anthropic.com/claude).

## License

This extension is licensed under the **GNU General Public License v2.0**.

See [license.txt](license.txt) for more information.
