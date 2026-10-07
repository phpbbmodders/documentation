# Securing the docs build directory

The docs build is the generated Hugo site that `phpbbdocs_hugo.sh` writes
from `proteus_doc_<lang>.xml`. By default it lives in phpBB's
`store/docs_build/` directory.

**The build must not be readable directly from the web.** Anyone who could
browse the raw Hugo pages could read a section or language your Permissions
screens restrict, bypassing the extension's checks entirely, since those
only run when a request goes through phpBB's own `/documentation/...`
routes. A deny rule has no effect on the extension itself: its PHP code
reads the files straight off disk.

Article images are served through phpBB's `/documentation-image/{lang}/{path}`
route, not through direct access to the build. The route checks the source
page's language and section permissions, the image language's permission,
and that the article references the requested image. Only supported raster
images are served; SVG files are not served by this route.

Search bundles are served the same way, through
`/documentation-search-bundle/{lang}/{section}/{path}`. Each section has its
own Pagefind bundle, and the route serves a file only when the user may read
that language and section. Content-hashed index files may be cached
privately by the browser for the ACP's **Search index cache time** (default 60
minutes; 0 turns it off). The server-side search indexes
(`<lang>/<section>/search-index.json`) are never served; the search page reads
the indexes of sections the user may read. Direct access to the build would
expose every section's bundle and index, so the deny rule matters for search
too.

## Trusting the build

The extension shows the build's article, sidebar and breadcrumb HTML inside
the board as-is, scripts included. Treat the docs build path like code: only
put a build there that you produced yourself, and keep the directory
writable only by administrators and the build process.

## The default location: `store/`

phpBB already denies web access to `store/`:

- **Apache** and **LiteSpeed/OpenLiteSpeed**: phpBB's own `store/.htaccess`
  (needs `AllowOverride` on Apache).
- **Nginx**, **lighttpd** and **IIS**: the `store` rule in phpBB's
  `docs/nginx.sample.conf`, `docs/lighttpd.sample.conf` and root
  `web.config`. Check your server config includes it if it wasn't built from
  those samples.
- **Caddy**: phpBB ships no Caddy sample, so add the snippet below.

## Other locations

A build kept somewhere else under the web root needs its own deny rule.
The snippets in `contrib/` cover `store/docs_build/` and the
old default, `ext/phpbbmodders/documentation/docs-build/`; adjust the path
for any other location:

- **Nginx**: [`contrib/nginx-docs-build-deny.conf`](../contrib/nginx-docs-build-deny.conf)
- **Caddy**: [`contrib/caddy-docs-build-deny.conf`](../contrib/caddy-docs-build-deny.conf)
- **IIS**: [`contrib/iis-docs-build-deny.web.config`](../contrib/iis-docs-build-deny.web.config)
- **lighttpd**: [`contrib/lighttpd-docs-build-deny.conf`](../contrib/lighttpd-docs-build-deny.conf)

The old default directory, `docs-build/` inside the extension, still ships
with an Apache `.htaccess` that denies all access. Boards upgraded from an
earlier version keep using it if a build was already there. Move that build
to `store/docs_build/` and update the docs build path:
updating the extension replaces its directory and deletes the build.

Each snippet is a drop-in, not a full config file. Merge it into your
existing site config near phpBB's own "deny access to internal files" rule;
don't replace your config with just that file.
