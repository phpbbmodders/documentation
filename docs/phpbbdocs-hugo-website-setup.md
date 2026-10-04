# Using phpbbdocs-hugo with the Documentation extension

The [phpbbdocs-hugo](https://github.com/phpbbmodders/phpbbdocs-hugo) project builds the phpBB user and developer documentation as a static Hugo site. The [Documentation](https://github.com/phpbbmodders/documentation) extension serves that generated site through phpBB at `/documentation`, wrapped in the active board style and protected by phpBB permissions.

The two projects are separate:

- **phpbbdocs-hugo** owns the documentation source, translation workflow, Hugo templates, and build scripts.
- **Documentation** owns the phpBB integration and the `docs-build/` directory that receives the finished Hugo site.

## Requirements

Install the Documentation extension normally under:

```text
ext/phpbbmodders/documentation/
```

For building phpbbdocs-hugo, the host running the build needs at least:

- Git
- Hugo
- `xsltproc`
- `rsync`

Additional translation tools are needed only when maintaining translations. See the phpbbdocs-hugo repository documentation for the translation workflow.

## Clone phpbbdocs-hugo

The Hugo project does not need to live inside the phpBB installation. It can be checked out anywhere the account running the build can access both repositories.

For example:

```bash
git clone https://github.com/phpbbmodders/phpbbdocs-hugo.git
cd phpbbdocs-hugo
```

## Update the upstream phpBB documentation

Before producing a fresh site build, update the English documentation from the official phpBB documentation repository:

```bash
./pull_upstream_docs.sh
```

This updates the local `content/en/` source from the upstream `phpbb/documentation` repository.

If translated documentation or developer documentation is being maintained, complete the appropriate translation/update workflow before the final Hugo build.

## Build directly into the extension

The Documentation extension expects the generated Hugo site in its own `docs-build/` directory by default.

Run `phpbbdocs_hugo.sh` with `all` and pass the extension's `docs-build/` directory as the destination:

```bash
./phpbbdocs_hugo.sh all /path/to/phpbb/ext/phpbbmodders/documentation/docs-build
```

For example, if phpBB is installed at `/var/www/phpbb`:

```bash
./phpbbdocs_hugo.sh all /var/www/phpbb/ext/phpbbmodders/documentation/docs-build
```

The script:

1. Finds each available `proteus_doc_<language>.xml` source.
2. Generates the Hugo Markdown content for each language.
3. Copies that language's documentation images into the Hugo static tree.
4. Runs Hugo with the supplied destination directory.

A single language can also be built by replacing `all` with its language code:

```bash
./phpbbdocs_hugo.sh fr /var/www/phpbb/ext/phpbbmodders/documentation/docs-build
```

For the website, a full `all` build is normally preferred so every available language is regenerated together.

## Enable and configure the Documentation extension

Enable the extension through:

**ACP → Customise → Manage extensions**

The default docs build path already points to the extension's own `docs-build/` directory, so no ACP path change is needed when using the layout above.

The generated documentation is then served through phpBB under:

```text
/documentation
```

The extension can automatically select a language from the user's phpBB language, or the user can choose one manually. Language-code overrides are available in the ACP for cases where a phpBB language code and the Hugo build's language code do not match.

## Permissions

Documentation is open to everyone, including guests, by default.

Permissions can be restricted using phpBB's normal permission screens. The extension supports permissions by documentation section and language.

After adding a new top-level documentation section or a new language to the Hugo build, use **Resync permissions** on the Documentation extension settings page so phpBB creates the corresponding permission entries.

## Protect docs-build from direct web access

The raw contents of `docs-build/` must not be publicly accessible.

Users should reach the documentation through phpBB's `/documentation` routes so the extension can enforce language and section permissions. Direct HTTP access to `docs-build/` would bypass those checks.

The extension includes an Apache `.htaccess` rule that denies direct access. This also works with LiteSpeed/OpenLiteSpeed when `.htaccess` processing is enabled.

Other web servers require an equivalent server-level deny rule. Ready-made examples are included in the extension for:

- Nginx
- Caddy
- IIS
- lighttpd

See [Securing the docs build directory](securing-docs-build.md) for the configuration snippets.

## Updating the website later

For a normal documentation refresh:

```bash
cd /path/to/phpbbdocs-hugo

git pull --ff-only
./pull_upstream_docs.sh
./phpbbdocs_hugo.sh all /path/to/phpbb/ext/phpbbmodders/documentation/docs-build
```

If translations or developer documentation have changed, update or rebuild those sources before the final `phpbbdocs_hugo.sh all ...` command.

There is no need to reinstall or re-enable the phpBB extension after rebuilding. The extension reads the newly generated files from `docs-build/`.

If the rebuild introduces a new language or top-level section, run **Resync permissions** in the ACP afterward.

## Search

The Hugo build generates a `search-index.json` file for each language. The Documentation extension uses these indexes for full-text documentation search while still applying the viewer's language and section permissions.

Rebuild phpbbdocs-hugo whenever documentation content changes so the search indexes stay in sync with the pages.

The sidebar **Filter** is separate and filters navigation titles only.

## Related documentation

The phpbbdocs-hugo repository contains the detailed build and translation workflow, including:

- Build & Publish Workflow
- Script Reference
- Translator Workflow

Use those guides when maintaining source content or translations. This page is specifically about connecting the finished phpbbdocs-hugo build to the phpBB Documentation extension.
