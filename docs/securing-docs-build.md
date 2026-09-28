# Securing the docs build directory

`docs-build/` is generated output, not source — it's gitignored (aside
from a `.gitkeep` placeholder) and gets rebuilt from
`proteus_doc_<lang>.xml` any time `proteus_hugo.sh` runs.

**`docs-build/` ships with an Apache `.htaccess` denying all direct web
access**, the same pattern phpBB's own `cache/`, `files/`, `store/`, and
`config/` directories use. This matters beyond tidiness: without it,
anyone who knows or guesses the URL could browse the raw, un-styled Hugo
pages directly and read a section/language your Permissions screens are
restricting — bypassing the extension's ACL checks entirely, since those
only run when a request goes through phpBB's own `/documentation/...`
route. This has no effect on the extension's own PHP code, which reads
these files directly off disk regardless of any of the below — a deny
rule only blocks browser/HTTP access to the directory, never phpBB's own
server-side reads.

The `.htaccess` only helps on **Apache with `AllowOverride` enabled for
this path**, and on **LiteSpeed/OpenLiteSpeed**, which reads `.htaccess`
natively for Apache compatibility — nothing further needed on either.
Everything else needs an equivalent deny rule added at the server-config
level, since none of them read `.htaccess`:

- **Nginx**: [`contrib/nginx-docs-build-deny.conf`](../contrib/nginx-docs-build-deny.conf)
- **Caddy**: [`contrib/caddy-docs-build-deny.conf`](../contrib/caddy-docs-build-deny.conf)
- **IIS**: [`contrib/iis-docs-build-deny.web.config`](../contrib/iis-docs-build-deny.web.config)
- **lighttpd**: [`contrib/lighttpd-docs-build-deny.conf`](../contrib/lighttpd-docs-build-deny.conf)

Each is a drop-in snippet, not a full config file — merge it into your
existing site config near phpBB's own equivalent "deny access to
internal files" rule, don't replace your config with just that file.
