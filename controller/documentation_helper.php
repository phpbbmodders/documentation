<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\controller;

use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\user;

/**
 * Language resolution, Hugo-build extraction, and path validation for the
 * documentation extension. The controller helper supplies board URLs.
 */
class documentation_helper
{
	/** Cookie name, prefixed with the board's cookie name by \phpbb\user::set_cookie(). */
	const COOKIE_NAME = 'docs_lang';

	/** How long the language choice is remembered, in seconds (one year). */
	const COOKIE_LIFETIME = 31536000;

	/** ACL option prefix for section permissions. */
	const PERMISSION_PREFIX = 'u_phpbbmodders_documentation_';

	/** ACL option prefix for language permissions. */
	const LANG_PERMISSION_PREFIX = 'u_phpbbmodders_documentation_lang_';

	/** Length of phpBB's acl_options.auth_option column. */
	const AUTH_OPTION_MAX_LENGTH = 50;

	/** Characters a language or section directory name may use. */
	const BUILD_NAME_PATTERN = '/\A[a-z0-9][a-z0-9_-]*\z/';

	/** Page whose wide tables get wrapped in a horizontally scrollable region. */
	const EVENTS_LIST_PATH = 'development/extensions/events_list';

	/** Character Hugo's breadcrumb puts before its home link. */
	const HUGO_HOME_GLYPH = '⌂';

	/** Font Awesome icon shown instead. */
	const BREADCRUMB_HOME_ICON = 'fa-info-circle';

	/**
	 * The one top-level section name the developer docs build produces
	 * (see phpbbdocs_hugo_devdocs.sh) — the sole boundary between
	 * "Documentation" and "Developer Documentation" when the nav is
	 * split into two links. Not admin-configurable: this already matches
	 * how the Hugo build itself separates the two document sets, so
	 * there's no real scenario where it needs to move.
	 */
	const DEVDOCS_SECTION = 'development';

	/**
	 * File types the browser loads from a Pagefind search bundle, and the
	 * Content-Type each is served as. Stylesheets are left out because
	 * documentation-search.js renders results without Pagefind's UI.
	 */
	const SEARCH_BUNDLE_TYPES = array(
		'js'          => 'text/javascript',
		'json'        => 'application/json',
		'pagefind'    => 'application/octet-stream',
		'pf_fragment' => 'application/octet-stream',
		'pf_index'    => 'application/octet-stream',
		'pf_meta'     => 'application/octet-stream',
		'pf_filter'   => 'application/octet-stream',
	);

	/**
	 * Pagefind names these files after a hash of their content, so a
	 * rebuild never changes one in place. They may be cached privately for
	 * the ACP's search cache time; the entry file and scripts that point to
	 * them are never cached. After a permission is revoked, a browser can
	 * keep using files it already cached until they expire.
	 */
	const SEARCH_BUNDLE_CACHEABLE = array('pf_fragment', 'pf_index', 'pf_meta', 'pf_filter');

	/** Upper limit of the ACP search cache time, in minutes (24 hours). */
	const SEARCH_CACHE_MAX_MINUTES = 1440;

	/** @var config */
	protected $config;

	/** @var language */
	protected $language;

	/** @var request_interface */
	protected $request;

	/** @var user */
	protected $user;

	/** @var string */
	protected $phpbb_root_path;

	/** @var controller_helper */
	protected $controller_helper;

	/** @var array|null cached code => label */
	protected $language_labels;

	/** @var array|null cached list of language codes present in the build */
	protected $available_languages;

	/** @var array cached section lists, keyed by language code */
	protected $available_sections = array();

	/** @var array configured docs path => resolved docs root (or false) */
	protected $docs_roots = array();

	/** @var array build directory names skipped by is_usable_build_name() */
	protected $ignored_build_names = array();

	public function __construct(config $config, language $language, request_interface $request, user $user, $phpbb_root_path, controller_helper $controller_helper)
	{
		$this->config = $config;
		$this->language = $language;
		$this->request = $request;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->controller_helper = $controller_helper;
	}

	/**
	 * Absolute filesystem path to the Hugo build's public/ directory —
	 * only if it actually contains a build. A configured path that
	 * exists but is empty (e.g. before the Hugo build has ever been
	 * written to it) is treated the same as "not found": every caller of this
	 * method uses it to decide whether documentation is usable at all,
	 * and an existing-but-empty directory isn't.
	 *
	 * @return string|false
	 */
	public function get_docs_root()
	{
		$configured = (string) $this->config['phpbbmodders_documentation_docs_path'];
		if (!array_key_exists($configured, $this->docs_roots))
		{
			$root = $this->resolve_configured_root();
			$this->docs_roots[$configured] = ($root !== false && $this->has_any_language_directory($root)) ? $root : false;
		}

		return $this->docs_roots[$configured];
	}

	/**
	 * @return string|false The configured docs path, resolved and
	 *         existence-checked, but without requiring it to be non-empty.
	 */
	protected function resolve_configured_root()
	{
		$configured = (string) $this->config['phpbbmodders_documentation_docs_path'];

		if ($configured === '')
		{
			return false;
		}

		$root = (strpos($configured, '/') === 0) ? $configured : $this->phpbb_root_path . $configured;
		$real = realpath($root);

		return ($real !== false && is_dir($real)) ? $real : false;
	}

	/**
	 * @param string $root
	 * @return bool
	 */
	protected function has_any_language_directory($root)
	{
		foreach (new \DirectoryIterator($root) as $entry)
		{
			if (!$entry->isDot() && $entry->isDir() && is_file($entry->getPathname() . '/index.html'))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Language codes actually present in the build (each a directory
	 * under the docs root containing its own index.html).
	 *
	 * @return array
	 */
	public function get_available_languages()
	{
		if ($this->available_languages !== null)
		{
			return $this->available_languages;
		}

		$this->available_languages = array();

		$root = $this->resolve_configured_root();
		if ($root === false)
		{
			return $this->available_languages;
		}

		foreach (new \DirectoryIterator($root) as $entry)
		{
			if ($entry->isDot() || !$entry->isDir())
			{
				continue;
			}

			if (!is_file($entry->getPathname() . '/index.html'))
			{
				continue;
			}

			if ($this->is_usable_build_name($entry->getFilename(), self::LANG_PERMISSION_PREFIX))
			{
				$this->available_languages[] = $entry->getFilename();
			}
			else
			{
				$this->ignored_build_names[$entry->getFilename()] = true;
			}
		}

		sort($this->available_languages);

		return $this->available_languages;
	}

	/**
	 * Top-level sections actually present for a language (each a directory
	 * under <docs_root>/<lang>/ containing its own index.html).
	 *
	 * @param string $lang
	 * @return array
	 */
	public function get_available_sections($lang)
	{
		$root = $this->get_docs_root();
		if ($root === false || !$this->is_known_language($lang))
		{
			return array();
		}

		if (isset($this->available_sections[$lang]))
		{
			return $this->available_sections[$lang];
		}

		$sections = array();
		$lang_dir = $root . '/' . $lang;
		if (is_dir($lang_dir))
		{
			foreach (new \DirectoryIterator($lang_dir) as $entry)
			{
				if ($entry->isDot() || !$entry->isDir() || $entry->getFilename() === 'images'
					|| !is_file($entry->getPathname() . '/index.html'))
				{
					continue;
				}

				// A section named lang_<x> would share its ACL option with
				// the permission for language <x>.
				$name = $entry->getFilename();
				if (strpos($name, 'lang_') === 0 || !$this->is_usable_build_name($name, self::PERMISSION_PREFIX))
				{
					$this->ignored_build_names[$lang . '/' . $name] = true;
					continue;
				}

				$sections[] = $name;
			}
		}

		sort($sections);
		$this->available_sections[$lang] = $sections;

		return $sections;
	}

	/**
	 * Whether a build directory name can become part of an ACL option:
	 * lowercase letters, digits, "_" and "-" only, and short enough that
	 * $prefix . $name fits phpBB's auth_option column.
	 *
	 * @param string $name
	 * @param string $prefix
	 * @return bool
	 */
	protected function is_usable_build_name($name, $prefix)
	{
		return preg_match(self::BUILD_NAME_PATTERN, $name)
			&& strlen($prefix . $name) <= self::AUTH_OPTION_MAX_LENGTH;
	}

	/**
	 * Build directories skipped because their names can't be used as
	 * permission names. Only complete after get_available_languages() and
	 * get_available_sections() have run for every language.
	 *
	 * @return array Directory names, relative to the docs root.
	 */
	public function get_ignored_build_names()
	{
		$names = array_keys($this->ignored_build_names);
		sort($names);

		return $names;
	}

	/**
	 * Human-readable label per language code, read once from the
	 * language-switcher markup Hugo already rendered on the homepage of
	 * the first available language.
	 *
	 * @return array code => label
	 */
	public function get_language_labels()
	{
		if ($this->language_labels !== null)
		{
			return $this->language_labels;
		}

		$this->language_labels = array();
		$languages = $this->get_available_languages();

		if (empty($languages))
		{
			return $this->language_labels;
		}

		$root = $this->get_docs_root();
		$index_file = $root . '/' . $languages[0] . '/index.html';

		if (is_file($index_file))
		{
			$dom = new \DOMDocument();
			libxml_use_internal_errors(true);
			$dom->loadHTMLFile($index_file);
			libxml_clear_errors();

			$xpath = new \DOMXPath($dom);
			foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " language-switcher ")]//a') as $link)
			{
				$code = $link->getAttribute('lang');
				$label = trim($link->textContent);

				if ($code !== '' && $label !== '')
				{
					$this->language_labels[$code] = $label;
				}
			}
		}

		// Any language missing a label (e.g. the probed page had no
		// switcher) just falls back to its own code.
		foreach ($languages as $code)
		{
			if (!isset($this->language_labels[$code]))
			{
				$this->language_labels[$code] = $code;
			}
		}

		return $this->language_labels;
	}

	/**
	 * @param string $lang
	 * @return bool
	 */
	public function is_known_language($lang)
	{
		return $lang !== '' && in_array($lang, $this->get_available_languages(), true);
	}

	/**
	 * @param string $section
	 * @return bool
	 */
	public function is_devdocs_section($section)
	{
		return $section === self::DEVDOCS_SECTION;
	}

	/**
	 * @return string
	 */
	public function get_fallback_language()
	{
		$configured = (string) $this->config['phpbbmodders_documentation_fallback_lang'];

		if ($this->is_known_language($configured))
		{
			return $configured;
		}

		$languages = $this->get_available_languages();

		return !empty($languages) ? $languages[0] : 'en';
	}

	/**
	 * Priority chain used when no language segment is present in the URL:
	 * cookie -> phpBB board language -> browser Accept-Language -> fallback.
	 *
	 * @return string
	 */
	public function resolve_default_language()
	{
		$cookie_lang = $this->request->variable($this->get_cookie_name(), '', true, \phpbb\request\request_interface::COOKIE);
		if ($this->is_known_language($cookie_lang))
		{
			return $cookie_lang;
		}

		if (!empty($this->user->data['is_registered']))
		{
			$matched = $this->find_best_language_match($this->user->data['user_lang']);
			if ($matched !== false)
			{
				return $matched;
			}
		}

		$browser_lang = $this->best_accept_language_match();
		if ($browser_lang !== false)
		{
			return $browser_lang;
		}

		return $this->get_fallback_language();
	}

	/**
	 * @return string|false
	 */
	protected function best_accept_language_match()
	{
		$header = $this->request->header('Accept-Language');
		if (empty($header))
		{
			return false;
		}

		$candidates = array();

		foreach (explode(',', $header) as $part)
		{
			$pieces = explode(';', trim($part));
			$tag = trim($pieces[0]);
			$quality = 1.0;

			if (isset($pieces[1]) && preg_match('/q=([0-9.]+)/', $pieces[1], $m))
			{
				$quality = (float) $m[1];
			}

			if ($tag !== '')
			{
				$candidates[$tag] = max($quality, isset($candidates[$tag]) ? $candidates[$tag] : 0);
			}
		}

		arsort($candidates);

		foreach (array_keys($candidates) as $tag)
		{
			$matched = $this->find_best_language_match($tag);
			if ($matched !== false)
			{
				return $matched;
			}
		}

		return false;
	}

	/**
	 * Maps an external code (a phpBB language pack name, or a browser
	 * Accept-Language tag) to one of our available Hugo-build language
	 * codes. Tries, in order: an ACP-configured explicit override, an
	 * exact match (case/separator-insensitive: pt_br == pt-BR), then a
	 * broad-language fallback (pt_br -> pt if only the broader code is
	 * actually built) — since phpBB and Hugo don't coordinate on codes
	 * and regularly diverge for regional variants.
	 *
	 * @param string $code
	 * @return string|false
	 */
	protected function find_best_language_match($code)
	{
		if ($code === '')
		{
			return false;
		}

		$available = $this->get_available_languages();
		$normalized = strtolower(str_replace('_', '-', $code));

		$overrides = $this->get_language_overrides();
		if (isset($overrides[$normalized]) && in_array($overrides[$normalized], $available, true))
		{
			return $overrides[$normalized];
		}

		foreach ($available as $available_code)
		{
			if (strtolower(str_replace('_', '-', $available_code)) === $normalized)
			{
				return $available_code;
			}
		}

		$primary = explode('-', $normalized)[0];
		foreach ($available as $available_code)
		{
			$available_primary = explode('-', strtolower(str_replace('_', '-', $available_code)))[0];
			if ($available_primary === $primary)
			{
				return $available_code;
			}
		}

		return false;
	}

	/**
	 * ACP-configured explicit code overrides, for cases the automatic
	 * broad-language fallback can't resolve on its own (e.g. phpBB's "no"
	 * needing to map to a Hugo site keyed "nb"). One "from=to" pair per
	 * line; "from" is normalized the same way as everything else here.
	 *
	 * @return array normalized-from-code => hugo-code
	 */
	protected function get_language_overrides()
	{
		return $this->parse_code_value_map((string) $this->config['phpbbmodders_documentation_lang_map']);
	}

	/**
	 * Shared parser for this extension's "one code=value pair per line"
	 * config format — used for the docs-language-code overrides above,
	 * and for the per-phpBB-language nav link label overrides below.
	 *
	 * @param string $raw
	 * @return array normalized-code => value
	 */
	protected function parse_code_value_map($raw)
	{
		$map = array();

		foreach (preg_split('/[\r\n,]+/', $raw) as $line)
		{
			$line = trim($line);
			if ($line === '' || strpos($line, '=') === false)
			{
				continue;
			}

			list($from, $to) = array_map('trim', explode('=', $line, 2));
			if ($from !== '' && $to !== '')
			{
				$map[strtolower(str_replace('_', '-', $from))] = $to;
			}
		}

		return $map;
	}

	/**
	 * Display text for a nav link: an ACP-configured override for the
	 * viewer's own phpBB language, if one exists in $config_key's
	 * "code=text" map, else the given language key's default string.
	 * Unlike the docs-language overrides above (keyed by docs-build
	 * language), this is keyed by the *viewer's phpBB interface
	 * language* — a plain phpBB config value has no per-language
	 * variants of its own, so this is what makes an override still
	 * translate per viewer instead of becoming one fixed string for
	 * everyone regardless of their own forum language.
	 *
	 * @param string $config_key      Config key holding the "code=text" override map.
	 * @param string $default_lang_key Language key to fall back to (e.g. 'DOCUMENTATION').
	 * @return string
	 */
	public function get_nav_label($config_key, $default_lang_key)
	{
		$overrides = $this->parse_code_value_map((string) $this->config[$config_key]);
		$user_lang = isset($this->user->data['user_lang']) ? (string) $this->user->data['user_lang'] : '';
		$normalized = strtolower(str_replace('_', '-', $user_lang));

		if ($normalized !== '' && isset($overrides[$normalized]))
		{
			return $overrides[$normalized];
		}

		return $this->language->lang($default_lang_key);
	}

	/** @return string A single Font Awesome icon class, or no icon. */
	public function normalize_nav_icon($icon)
	{
		$icon = trim((string) $icon);
		return strlen($icon) <= 64 && preg_match('/\Afa-[a-z0-9]+(?:-[a-z0-9]+)*\z/', $icon) ? $icon : '';
	}

	public function get_nav_icon($config_key)
	{
		return !empty($this->config[$config_key . '_enabled']) && isset($this->config[$config_key])
			? $this->normalize_nav_icon($this->config[$config_key]) : '';
	}

	/**
	 * Persists a manually/automatically resolved language choice.
	 *
	 * @param string $lang
	 * @return void
	 */
	public function set_language_cookie($lang)
	{
		$this->request->overwrite($this->get_cookie_name(), $lang, \phpbb\request\request_interface::COOKIE);
		$this->user->set_cookie(self::COOKIE_NAME, $lang, time() + self::COOKIE_LIFETIME);
	}

	/**
	 * Full name of the language cookie, as \phpbb\user::set_cookie()
	 * writes it: the board's cookie name, "_", then COOKIE_NAME.
	 *
	 * @return string
	 */
	protected function get_cookie_name()
	{
		return $this->config['cookie_name'] . '_' . self::COOKIE_NAME;
	}

	/**
	 * @param string $path
	 * @return string The first path segment ("" for the section-less overview).
	 */
	public function get_section($path)
	{
		$path = trim($path, '/');

		if ($path === '')
		{
			return '';
		}

		$segments = explode('/', $path);

		return $segments[0];
	}

	/**
	 * Resolves realpath-validated path to a page's index.html, containment
	 * checked against the selected language directory.
	 *
	 * @param string $lang
	 * @param string $path
	 * @return string|false
	 */
	public function get_content_file($lang, $path)
	{
		$root = $this->get_docs_root();
		if ($root === false || !$this->is_known_language($lang)
			|| strpos($path, "\0") !== false || strpos($path, '\\') !== false
			|| preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path))
		{
			return false;
		}

		$path = trim($path, '/');
		$candidate = $root . '/' . $lang . ($path !== '' ? '/' . $path : '') . '/index.html';

		$real = realpath($candidate);
		if ($real === false)
		{
			return false;
		}

		$lang_root = realpath($root . '/' . $lang);
		if ($lang_root === false || strpos($lang_root, $root . DIRECTORY_SEPARATOR) !== 0
			|| !is_file($real) || strpos($real, $lang_root . DIRECTORY_SEPARATOR) !== 0)
		{
			return false;
		}

		return $real;
	}

	/**
	 * Sections of $lang, limited to $allowed_sections, that have a Pagefind
	 * search bundle (written by phpbbdocs-hugo's build_search_index.sh to
	 * <lang>/<section>/pagefind/).
	 *
	 * @param string $lang
	 * @param array $allowed_sections Sections the current user may read.
	 * @return array Section slugs, in $allowed_sections order.
	 */
	public function get_search_bundle_sections($lang, array $allowed_sections)
	{
		$sections = array();
		foreach ($allowed_sections as $section)
		{
			if ($this->get_search_bundle_file($lang, $section, 'pagefind.js') !== false)
			{
				$sections[] = $section;
			}
		}

		return $sections;
	}

	/**
	 * Resolves a file inside one section's Pagefind bundle, containment
	 * checked against <docs_root>/<lang>/<section>/pagefind/. Callers must
	 * check the user's language and section permissions first.
	 *
	 * @param string $lang
	 * @param string $section
	 * @param string $path File path relative to the bundle directory.
	 * @return string|false
	 */
	public function get_search_bundle_file($lang, $section, $path)
	{
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if (!isset(self::SEARCH_BUNDLE_TYPES[$extension])
			|| !preg_match('#\A[A-Za-z0-9_-][A-Za-z0-9_.-]*(?:/[A-Za-z0-9_-][A-Za-z0-9_.-]*)?\z#', $path)
			|| !in_array($section, $this->get_available_sections($lang), true))
		{
			return false;
		}

		$root = $this->get_docs_root();
		$bundle_root = realpath($root . '/' . $lang . '/' . $section . '/pagefind');
		$file = realpath($root . '/' . $lang . '/' . $section . '/pagefind/' . $path);

		return $bundle_root !== false && strpos($bundle_root, $root . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $section . DIRECTORY_SEPARATOR) === 0
			&& $file !== false && is_file($file) && strpos($file, $bundle_root . DIRECTORY_SEPARATOR) === 0
			? $file : false;
	}

	/**
	 * @param string $path
	 * @return string The Content-Type a Pagefind bundle file is served as.
	 */
	public function get_search_bundle_type($path)
	{
		return self::SEARCH_BUNDLE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))];
	}

	/**
	 * @param string $path
	 * @return string The Cache-Control header for a Pagefind bundle file.
	 */
	public function get_search_bundle_cache_control($path)
	{
		$minutes = isset($this->config['phpbbmodders_documentation_search_cache_minutes'])
			? min(max((int) $this->config['phpbbmodders_documentation_search_cache_minutes'], 0), self::SEARCH_CACHE_MAX_MINUTES)
			: 60;

		return $minutes > 0 && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::SEARCH_BUNDLE_CACHEABLE, true)
			? 'private, max-age=' . ($minutes * 60) : 'private, no-store';
	}

	/** @return string|false An image contained in its own language build. */
	public function get_image_file($lang, $path)
	{
		$root = $this->get_docs_root();
		if ($root === false || !$this->is_known_language($lang)
			|| strpos($path, "\0") !== false || strpos($path, '\\') !== false
			|| preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path))
		{
			return false;
		}
		$lang_root = realpath($root . '/' . $lang);
		$file = realpath($root . '/' . $lang . '/' . $path);
		return $lang_root !== false && strpos($lang_root, $root . DIRECTORY_SEPARATOR) === 0
			&& $file !== false && is_file($file) && strpos($file, $lang_root . DIRECTORY_SEPARATOR) === 0
			? $file : false;
	}

	/** @return string|false Only images referenced by the supplied page may be served. */
	public function get_referenced_image_file($page_lang, $page, $image_lang, $image_path)
	{
		if (strpos($page, "\0") !== false || strpos($page, '\\') !== false
			|| preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $page))
		{
			return false;
		}
		$file = $this->get_image_file($image_lang, $image_path);
		$page_file = $this->get_content_file($page_lang, $page);
		$lang_root = realpath($this->get_docs_root() . '/' . $page_lang);
		if ($file === false || $page_file === false || $lang_root === false
			|| strpos($page_file, $lang_root . DIRECTORY_SEPARATOR) !== 0)
		{
			return false;
		}
		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->loadHTMLFile($page_file);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded)
		{
			return false;
		}
		$xpath = new \DOMXPath($dom);
		$images = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " docs-article ")]//img');
		foreach ($images as $image)
		{
			$relative = $this->resolve_image_relative_path($page_lang, $page, $image->getAttribute('src'));
			if ($relative === false)
			{
				continue;
			}
			$referenced = $this->get_image_file($page_lang, $relative);
			if ($referenced === false)
			{
				$referenced = $this->get_image_file($this->get_fallback_language(), $relative);
			}
			if ($referenced === $file)
			{
				return $file;
			}
		}
		return false;
	}

	/**
	 * Loads a page, falling back to the fallback language for this one
	 * page if it's missing in the requested language.
	 *
	 * @param string $lang
	 * @param string $path
	 * @param array|null $allowed_sections Sections the current user may see;
	 *        null skips filtering (used for permission-checking callers).
	 * @param bool $allow_fallback False when the caller already authorized the selected language.
	 * @return array|false ['lang', 'used_fallback', 'title', 'breadcrumb_html', 'sidebar_html', 'article_html']
	 */
	public function resolve_and_load($lang, $path, ?array $allowed_sections = null, $allow_fallback = true)
	{
		$file = $this->get_content_file($lang, $path);
		$used_fallback = false;

		if ($file === false)
		{
			if (!$allow_fallback)
			{
				return false;
			}
			$fallback_lang = $this->get_fallback_language();

			if ($fallback_lang === $lang)
			{
				return false;
			}

			$file = $this->get_content_file($fallback_lang, $path);
			if ($file === false)
			{
				return false;
			}

			$lang = $fallback_lang;
			$used_fallback = true;
		}

		$extracted = $this->extract_fragments($file, $lang, $path, $allowed_sections);
		if ($extracted === false)
		{
			return false;
		}

		return array_merge($extracted, array(
			'lang'          => $lang,
			'used_fallback' => $used_fallback,
		));
	}

	/**
	 * @param string $file Absolute path to a rendered Hugo page.
	 * @param string $lang Language the page was actually loaded in.
	 * @param string $path Requested doc path (for resolving relative image URLs).
	 * @param array|null $allowed_sections Sections to keep in the sidebar; null skips filtering.
	 * @return array|false ['title', 'breadcrumb_html', 'sidebar_html', 'article_html']
	 */
	protected function extract_fragments($file, $lang, $path, ?array $allowed_sections = null)
	{
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		if (!$dom->loadHTMLFile($file))
		{
			libxml_clear_errors();
			return false;
		}
		libxml_clear_errors();

		$xpath = new \DOMXPath($dom);

		$title_nodes = $xpath->query('//title');
		$title = $title_nodes->length ? trim($title_nodes->item(0)->textContent) : '';

		$sidebar_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " docs-nav-panel ")]')->item(0);
		$breadcrumb_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " utility-bar ")]')->item(0);
		$article_node = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " docs-article ")]')->item(0);

		if ($article_node === null)
		{
			return false;
		}

		if (trim($path, '/') === self::EVENTS_LIST_PATH)
		{
			foreach (iterator_to_array($article_node->getElementsByTagName('table')) as $table)
			{
				$table->setAttribute('class', trim($table->getAttribute('class') . ' documentation-events-table'));
				$table->setAttribute('data-columns', (string) $xpath->query('./thead/tr[1]/th', $table)->length);
				$wrapper = $dom->createElement('div');
				$wrapper->setAttribute('class', 'documentation-table-scroll');
				$wrapper->setAttribute('tabindex', '0');
				$wrapper->setAttribute('role', 'region');
				$heading = $table->previousSibling;
				while ($heading !== null && !($heading instanceof \DOMElement))
				{
					$heading = $heading->previousSibling;
				}
				$wrapper->setAttribute('aria-label', $heading !== null ? $heading->textContent : $title);
				$table->parentNode->insertBefore($wrapper, $table);
				$wrapper->appendChild($table);
			}
		}

		// Filter and resolve images against Hugo paths before converting
		// absolute URLs to phpBB routes, which may include app.php or a subdirectory.
		if ($sidebar_node !== null)
		{
			if ($allowed_sections !== null)
			{
				$this->filter_sidebar_sections($sidebar_node, $allowed_sections);
			}
			$this->rewrite_absolute_links($sidebar_node);
		}
		if ($breadcrumb_node !== null)
		{
			// The extension supplies the permission-filtered language picker.
			foreach (iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " language-switcher ")]', $breadcrumb_node)) as $switcher)
			{
				$switcher->parentNode->removeChild($switcher);
			}
			$this->use_breadcrumb_home_icon($breadcrumb_node);
			$this->rewrite_absolute_links($breadcrumb_node);
		}
		$this->process_images($article_node, $lang, $path);
		$this->rewrite_absolute_links($article_node);

		return array(
			'title'           => $title,
			'breadcrumb_html' => $breadcrumb_node !== null ? $this->outer_html($breadcrumb_node) : '',
			'sidebar_html'    => $sidebar_node !== null ? $this->outer_html($sidebar_node) : '',
			'article_html'    => $this->inner_html($article_node),
		);
	}

	/**
	 * Replaces the small "⌂" character Hugo puts before the breadcrumb's
	 * home link with a Font Awesome icon, sized like the board's own
	 * breadcrumb icon.
	 *
	 * @param \DOMNode $breadcrumb_node
	 * @return void
	 */
	protected function use_breadcrumb_home_icon(\DOMNode $breadcrumb_node)
	{
		$xpath = new \DOMXPath($breadcrumb_node->ownerDocument);
		$text = $xpath->query('.//a[1]/text()[1]', $breadcrumb_node)->item(0);
		if ($text === null || strpos(ltrim($text->nodeValue), self::HUGO_HOME_GLYPH) !== 0)
		{
			return;
		}

		$text->nodeValue = ltrim(substr(ltrim($text->nodeValue), strlen(self::HUGO_HOME_GLYPH)));
		$icon = $breadcrumb_node->ownerDocument->createElement('i');
		$icon->setAttribute('class', 'icon ' . self::BREADCRUMB_HOME_ICON . ' fa-fw');
		$icon->setAttribute('aria-hidden', 'true');
		$text->parentNode->insertBefore($icon, $text);
	}

	/**
	 * Removes .docs-tree-section blocks the current user isn't allowed to
	 * see, identified by the section segment of each section link's
	 * original Hugo /<lang>/<section>/ href before URL rewriting.
	 *
	 * @param \DOMNode $sidebar_node
	 * @param array $allowed_sections
	 * @return void
	 */
	protected function filter_sidebar_sections(\DOMNode $sidebar_node, array $allowed_sections)
	{
		$xpath = new \DOMXPath($sidebar_node->ownerDocument);

		foreach (iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " docs-tree-section ")]', $sidebar_node)) as $section_node)
		{
			/** @var \DOMElement $section_node */
			$link = $xpath->query('.//a', $section_node)->item(0);
			$href = $link !== null ? parse_url($link->getAttribute('href'), PHP_URL_PATH) : '';

			if (is_string($href) && preg_match('#^/[^/]+/([^/]+)#', $href, $matches) && in_array($matches[1], $allowed_sections, true))
			{
				continue;
			}

			$section_node->parentNode->removeChild($section_node);
		}
	}

	/**
	 * Rewrites href/src attributes rooted at Hugo's own /<lang>/... site
	 * root to phpBB routes, including links to other build languages.
	 *
	 * @param \DOMNode $scope
	 * @return void
	 */
	protected function rewrite_absolute_links(\DOMNode $scope)
	{
		$xpath = new \DOMXPath($scope->ownerDocument);

		foreach (array('href', 'src') as $attribute)
		{
			foreach (iterator_to_array($xpath->query('.//*[@' . $attribute . ']', $scope)) as $node)
			{
				/** @var \DOMElement $node */
				$value = $node->getAttribute($attribute);
				if (!preg_match('~^/([A-Za-z0-9_-]+)(?:/|$|[?#])~', $value, $matches) || !$this->is_known_language($matches[1]))
				{
					continue;
				}
				$parts = parse_url($value);
				if ($parts === false)
				{
					continue;
				}
				$path = ltrim(substr($parts['path'], strlen('/' . $matches[1])), '/');
				$params = array();
				if (isset($parts['query']))
				{
					parse_str($parts['query'], $params);
				}
				$params['lang'] = $matches[1];
				if ($path !== '')
				{
					$params['path'] = $path;
				}
				$url = $this->controller_helper->route($path !== '' ? 'phpbbmodders_documentation_page' : 'phpbbmodders_documentation_lang_root', $params, false);
				$node->setAttribute($attribute, $url . (isset($parts['fragment']) ? '#' . $parts['fragment'] : ''));
			}
		}
	}

	/**
	 * @param \DOMNode $node
	 * @return string
	 */
	protected function outer_html(\DOMNode $node)
	{
		return $node->ownerDocument->saveHTML($node);
	}

	/**
	 * @param \DOMNode $node
	 * @return string
	 */
	protected function inner_html(\DOMNode $node)
	{
		$html = '';
		foreach ($node->childNodes as $child)
		{
			$html .= $node->ownerDocument->saveHTML($child);
		}

		return $html;
	}

	/**
	 * Per-image fallback: use the fallback language's identical-path
	 * image if the selected language is missing it; otherwise replace the
	 * <img> with a boxed "image isn't available" notice.
	 *
	 * @param \DOMNode $article_node
	 * @param string $lang
	 * @param string $path Requested doc path, for resolving relative image URLs.
	 * @return void
	 */
	protected function process_images(\DOMNode $article_node, $lang, $path)
	{
		$dom = $article_node->ownerDocument;
		$fallback_lang = $this->get_fallback_language();
		$xpath = new \DOMXPath($dom);

		foreach (iterator_to_array($xpath->query('.//img', $article_node)) as $img)
		{
			/** @var \DOMElement $img */
			$src = $img->getAttribute('src');
			$relative = $this->resolve_image_relative_path($lang, $path, $src);

			if ($relative === false)
			{
				continue;
			}

			$image_lang = $lang;
			$file = $this->get_image_file($lang, $relative);
			if ($file === false && $lang !== $fallback_lang)
			{
				$image_lang = $fallback_lang;
				$file = $this->get_image_file($fallback_lang, $relative);
			}
			if ($file !== false)
			{
				$img->setAttribute('src', $this->controller_helper->route('phpbbmodders_documentation_image', array(
					'lang' => $image_lang,
					'path' => $relative,
					'page_lang' => $lang,
					'page' => $path,
				), false));
				continue;
			}

			$notice = $dom->createElement('span');
			$notice->setAttribute('class', 'documentation-image-missing');
			$description = trim($img->getAttribute('alt'));
			if ($description !== '')
			{
				$notice->setAttribute('data-doc-tooltip', $description);
				$notice->setAttribute('tabindex', '0');
			}
			$notice->appendChild($dom->createTextNode($this->language->lang('DOCUMENTATION_IMAGE_MISSING')));
			$img->parentNode->replaceChild($notice, $img);
		}
	}

	/**
	 * Resolves an <img src> — relative (dot-notation, relative to the
	 * current page's own directory depth) or absolute Hugo URL
	 * (/<lang>/...) — to a path relative to that language's
	 * build directory. Ignores external/absolute-elsewhere URLs.
	 *
	 * @param string $lang
	 * @param string $path
	 * @param string $src
	 * @return string|false
	 */
	protected function resolve_image_relative_path($lang, $path, $src)
	{
		if ($src === '' || preg_match('#^(https?:)?//#i', $src) || strpos($src, 'data:') === 0)
		{
			return false;
		}
		$src = parse_url($src, PHP_URL_PATH);
		if (!is_string($src) || $src === '')
		{
			return false;
		}

		if (strpos($src, '/' . $lang . '/') === 0)
		{
			$segments = explode('/', trim($src, '/'));
		}
		else if (strpos($src, '/') === 0)
		{
			// Absolute URLs outside this language's build are left alone.
			return false;
		}
		else
		{
			$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
			array_unshift($segments, $lang);

			foreach (explode('/', $src) as $part)
			{
				if ($part === '' || $part === '.')
				{
					continue;
				}
				if ($part === '..')
				{
					array_pop($segments);
					continue;
				}
				$segments[] = $part;
			}
		}

		if (empty($segments) || $segments[0] !== $lang)
		{
			return false;
		}

		array_shift($segments);

		return implode('/', $segments);
	}
}
