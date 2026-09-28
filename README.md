# Documentation

[![Tests](https://github.com/phpbbmodders/documentation/actions/workflows/tests.yml/badge.svg)](https://github.com/phpbbmodders/documentation/actions/workflows/tests.yml) [![Lint](https://github.com/phpbbmodders/documentation/actions/workflows/lint.yml/badge.svg)](https://github.com/phpbbmodders/documentation/actions/workflows/lint.yml)

Shows the board's Hugo documentation site inside phpBB, styled with the active board style.

## Features

- Serves a built Hugo docs site at `/documentation`, wrapped in the board's own style.
- Language picker: automatic (from the user's language) or manual, with fallbacks and language-code overrides.
- Per-section and per-language permissions, open to everyone by default.
- Navigation links in the board header, and **Resync permissions** when new sections or languages are added.

## Requirements

- phpBB 3.3.0 or later
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
