<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_DOCUMENTATION_TITLE'    => 'Documentation',
	'ACP_DOCUMENTATION_SETTINGS' => 'Documentation settings',
	'ACP_DOCUMENTATION_SETTINGS_EXPLAIN' => 'Configure where the built documentation site lives and how it behaves inside the forum.',

	'ACP_DOCUMENTATION_BUILD_MISSING' => 'No documentation build was found at the configured path. Documentation will stay hidden from the forum until this is fixed.',

	'ACP_DOCUMENTATION_DOCS_PATH'         => 'Docs build path',
	'ACP_DOCUMENTATION_DOCS_PATH_EXPLAIN' => 'Filesystem path to the Hugo build’s public/ directory — either absolute, or relative to the phpBB root.',

	'ACP_DOCUMENTATION_FALLBACK_LANG'         => 'Fallback language',
	'ACP_DOCUMENTATION_FALLBACK_LANG_EXPLAIN' => 'Used when a page or a whole language isn’t available. Pre-filled from the board’s default language.',

	'ACP_DOCUMENTATION_ENABLED'         => 'Documentation enabled',
	'ACP_DOCUMENTATION_ENABLED_EXPLAIN' => 'Master switch for the whole documentation system. Disabling this takes it offline entirely — both navigation links disappear and the documentation URLs stop responding — but this settings page stays reachable, unlike disabling the extension itself from Customise » Extensions.',

	'ACP_DOCUMENTATION_SPLIT_NAV_LINKS'         => 'Separate navigation links',
	'ACP_DOCUMENTATION_SPLIT_NAV_LINKS_EXPLAIN' => 'Show “Documentation” and “Developer Documentation” as two independent links instead of one combined link. When separate, each page’s sidebar only lists sections from its own side too — a Developer Documentation page never lists end-user sections and vice versa.',

	'ACP_DOCUMENTATION_SEARCH_CACHE_MINUTES'         => 'Search index cache time',
	'ACP_DOCUMENTATION_SEARCH_CACHE_MINUTES_EXPLAIN' => 'How long each visitor’s browser may keep the search index files it downloads, in minutes. Longer makes repeat searches faster, but after you remove someone’s access to a section, their browser can still search that section’s cached files until they expire. 0 turns caching off.',
	'ACP_DOCUMENTATION_SEARCH_CACHE_MINUTES_INVALID' => 'Search index cache time must be a whole number of minutes from 0 to %d.',

	'ACP_DOCUMENTATION_NAV_LINK'         => 'Show “Documentation” in navigation bar',
	'ACP_DOCUMENTATION_NAV_LINK_EXPLAIN' => 'Add a “Documentation” entry to the forum’s top navigation bar. Disabling this only hides the link — the documentation itself stays reachable at its URL for anyone with permission, so you can link it from your own site menu instead.',

	'ACP_DOCUMENTATION_DEVDOCS_NAV_LINK'         => 'Show “Developer Documentation” in navigation bar',
	'ACP_DOCUMENTATION_DEVDOCS_NAV_LINK_EXPLAIN' => 'Only used when separate navigation links are on, above. Same behavior as the “Documentation” link’s own toggle: disabling this only hides the link, the developer documentation itself stays reachable at its URL.',

	'ACP_DOCUMENTATION_NAV_LABEL_MAP'         => '“Documentation” link text overrides',
	'ACP_DOCUMENTATION_NAV_LABEL_MAP_EXPLAIN' => 'Override the “Documentation” link’s text for specific phpBB board languages. One override per line, as phpbb_lang_code=text. A board language not listed here shows the default text, translated normally.',

	'ACP_DOCUMENTATION_DEVDOCS_NAV_LABEL_MAP'         => '“Developer Documentation” link text overrides',
	'ACP_DOCUMENTATION_DEVDOCS_NAV_LABEL_MAP_EXPLAIN' => 'Same as the “Documentation” link’s text overrides above, but for the “Developer Documentation” link. Only used when separate navigation links are on.',

	'ACP_DOCUMENTATION_MANUAL_SWITCH'         => 'Manual language switcher',
	'ACP_DOCUMENTATION_MANUAL_SWITCH_EXPLAIN' => 'Let users pick a documentation language independently of their forum language.',

	'ACP_DOCUMENTATION_NOTIFY_RECIPIENTS'         => 'Missing-build notification recipients',
	'ACP_DOCUMENTATION_NOTIFY_RECIPIENTS_EXPLAIN' => 'Who gets a personal notification when no documentation build is found at the configured path. The ACP banner is always shown to every admin regardless of this setting.',
	'ACP_DOCUMENTATION_NOTIFY_FOUNDERS'           => 'Founders only',
	'ACP_DOCUMENTATION_NOTIFY_FOUNDERS_ADMINS'    => 'Founders and board admins',

	'ACP_DOCUMENTATION_LANG_MAP'         => 'Language code overrides',
	'ACP_DOCUMENTATION_LANG_MAP_EXPLAIN' => 'phpBB and the docs build don’t always use the same language codes (e.g. phpBB’s “no” vs a docs build keyed “nb”). One override per line, as phpbb_code=docs_code. Most boards won’t need this — matching codes (en, da, de…) and regional variants (pt_br falling back to pt) are handled automatically.',

	'ACP_DOCUMENTATION_NAV_ICON_ENABLED' => 'Show Documentation icon',
	'ACP_DOCUMENTATION_NAV_ICON' => 'Documentation icon',
	'ACP_DOCUMENTATION_NAV_ICON_EXPLAIN' => 'Optional Font Awesome icon name supported by your board style, such as fa-file-text-o. Leave blank for no icon.',
	'ACP_DOCUMENTATION_DEVDOCS_NAV_ICON_ENABLED' => 'Show Developer Documentation icon',
	'ACP_DOCUMENTATION_DEVDOCS_NAV_ICON' => 'Developer Documentation icon',
	'ACP_DOCUMENTATION_DEVDOCS_NAV_ICON_EXPLAIN' => 'Optional Font Awesome icon name, such as fa-code. Used only with separate navigation links. Leave blank for no icon.',

	'ACP_DOCUMENTATION_DETECTED'       => 'Detected content',
	'ACP_DOCUMENTATION_LANG_CODE'      => 'Language',
	'ACP_DOCUMENTATION_LANG_SECTIONS'  => 'Sections',

	'ACP_DOCUMENTATION_RESYNC'         => 'Resync permissions',
	'ACP_DOCUMENTATION_RESYNC_EXPLAIN' => 'Run this after rebuilding the docs if you’ve added a new top-level section or a new language — it registers any permissions that don’t exist yet.',
	'ACP_DOCUMENTATION_RESYNC_NONE'    => 'Nothing new to register — all detected sections and languages already have permissions.',
	'ACP_DOCUMENTATION_RESYNC_ADDED'   => 'Registered new permissions: %s',
));
