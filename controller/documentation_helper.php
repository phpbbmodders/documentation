<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\controller;

use phpbb\config\config;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\user;

/**
 * Pure logic for the documentation extension: language resolution, static
 * Hugo-build extraction, and path/lang validation. Deliberately free of any
 * phpBB template/response concerns so it's testable without a booted app.
 */
class documentation_helper
{
	const COOKIE_NAME = 'phpbb_docs_lang';

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

	/** @var array|null cached code => label */
	protected $language_labels;

	/** @var array|null cached list of language codes present in the build */
	protected $available_languages;

	public function __construct(config $config, language $language, request_interface $request, user $user, $phpbb_root_path)
	{
		$this->config = $config;
		$this->language = $language;
		$this->request = $request;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
	}

	/**
	 * Absolute filesystem path to the Hugo build's public/ directory —
	 * only if it actually contains a build. A configured path that
	 * exists but is empty (e.g. docs-build/ before proteus_hugo.sh has
	 * ever run) is treated the same as "not found": every caller of this
	 * method uses it to decide whether documentation is usable at all,
	 * and an existing-but-empty directory isn't.
	 *
	 * @return string|false
	 */
	public function get_docs_root()
	{
		$root = $this->resolve_configured_root();

		return ($root !== false && $this->has_any_language_directory($root)) ? $root : false;
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

			if (is_file($entry->getPathname() . '/index.html'))
			{
				$this->available_languages[] = $entry->getFilename();
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
		$sections = array();

		$root = $this->get_docs_root();
		if ($root === false || !$this->is_known_language($lang))
		{
			return $sections;
		}

		$lang_dir = $root . '/' . $lang;
		if (!is_dir($lang_dir))
		{
			return $sections;
		}

		foreach (new \DirectoryIterator($lang_dir) as $entry)
		{
			if ($entry->isDot() || !$entry->isDir() || $entry->getFilename() === 'images')
			{
				continue;
			}

			if (is_file($entry->getPathname() . '/index.html'))
			{
				$sections[] = $entry->getFilename();
			}
		}

		sort($sections);

		return $sections;
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
		$cookie_lang = $this->request->variable(self::COOKIE_NAME, '', true, \phpbb\request\request_interface::COOKIE);
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
		$overrides = array();

		foreach (preg_split('/[\r\n,]+/', (string) $this->config['phpbbmodders_documentation_lang_map']) as $line)
		{
			$line = trim($line);
			if ($line === '' || strpos($line, '=') === false)
			{
				continue;
			}

			list($from, $to) = array_map('trim', explode('=', $line, 2));
			if ($from !== '' && $to !== '')
			{
				$overrides[strtolower(str_replace('_', '-', $from))] = $to;
			}
		}

		return $overrides;
	}

	/**
	 * Persists a manually/automatically resolved language choice.
	 *
	 * @param string $lang
	 * @return void
	 */
	public function set_language_cookie($lang)
	{
		$this->request->overwrite(self::COOKIE_NAME, $lang, \phpbb\request\request_interface::COOKIE);
		setcookie(self::COOKIE_NAME, $lang, time() + 60 * 60 * 24 * 365, '/');
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
	 * checked against the docs root.
	 *
	 * @param string $lang
	 * @param string $path
	 * @return string|false
	 */
	public function get_content_file($lang, $path)
	{
		$root = $this->get_docs_root();
		if ($root === false || !$this->is_known_language($lang))
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

		// Containment check: resolved file must still live under docs root.
		if (strpos($real, $root . DIRECTORY_SEPARATOR) !== 0)
		{
			return false;
		}

		return $real;
	}

	/**
	 * Loads a page, falling back to the fallback language for this one
	 * page if it's missing in the requested language.
	 *
	 * @param string $lang
	 * @param string $path
	 * @param array|null $allowed_sections Sections the current user may see;
	 *        null skips filtering (used for permission-checking callers).
	 * @return array|false ['lang', 'used_fallback', 'title', 'breadcrumb_html', 'sidebar_html', 'article_html']
	 */
	public function resolve_and_load($lang, $path, array $allowed_sections = null)
	{
		$file = $this->get_content_file($lang, $path);
		$used_fallback = false;

		if ($file === false)
		{
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
	protected function extract_fragments($file, $lang, $path, array $allowed_sections = null)
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

		// Hugo's own nav/breadcrumb/language links are absolute
		// (/<lang>/...), rooted at Hugo's own site, not phpBB's mount
		// point — rewrite that one prefix so they point at our route
		// instead. Relative markdown-authored links/images inside the
		// article are left untouched; the route already mirrors Hugo's
		// path depth so those resolve correctly on their own.
		if ($sidebar_node !== null)
		{
			$this->rewrite_absolute_links($sidebar_node, $lang);

			if ($allowed_sections !== null)
			{
				$this->filter_sidebar_sections($sidebar_node, $allowed_sections);
			}
		}
		if ($breadcrumb_node !== null)
		{
			$this->rewrite_absolute_links($breadcrumb_node, $lang);
		}
		$this->rewrite_absolute_links($article_node, $lang);

		$this->process_images($article_node, $lang, $path);

		return array(
			'title'           => $title,
			'breadcrumb_html' => $breadcrumb_node !== null ? $this->outer_html($breadcrumb_node) : '',
			'sidebar_html'    => $sidebar_node !== null ? $this->outer_html($sidebar_node) : '',
			'article_html'    => $this->inner_html($article_node),
		);
	}

	/**
	 * Removes .docs-tree-section blocks the current user isn't allowed to
	 * see, identified by the section segment of each section link's
	 * (already-rewritten) /documentation/<lang>/<section>/ href.
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
			$href = $link !== null ? $link->getAttribute('href') : '';

			if (preg_match('#^/documentation/[^/]+/([^/]+)#', $href, $matches) && in_array($matches[1], $allowed_sections, true))
			{
				continue;
			}

			$section_node->parentNode->removeChild($section_node);
		}
	}

	/**
	 * Rewrites href/src attributes rooted at Hugo's own /<lang>/... site
	 * root to phpBB's /documentation/<lang>/... route.
	 *
	 * @param \DOMNode $scope
	 * @param string $lang
	 * @return void
	 */
	protected function rewrite_absolute_links(\DOMNode $scope, $lang)
	{
		$xpath = new \DOMXPath($scope->ownerDocument);
		$prefix = '/' . $lang . '/';

		foreach (array('href', 'src') as $attribute)
		{
			foreach (iterator_to_array($xpath->query('.//*[@' . $attribute . ']', $scope)) as $node)
			{
				/** @var \DOMElement $node */
				$value = $node->getAttribute($attribute);
				if (strpos($value, $prefix) === 0)
				{
					$node->setAttribute($attribute, '/documentation' . $value);
				}
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
		$root = $this->get_docs_root();
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

			if (is_file($root . '/' . $lang . '/' . $relative))
			{
				continue;
			}

			if ($lang !== $fallback_lang && is_file($root . '/' . $fallback_lang . '/' . $relative))
			{
				$img->setAttribute('src', '/documentation/' . $fallback_lang . '/' . $relative);
				continue;
			}

			$notice = $dom->createElement('span');
			$notice->setAttribute('class', 'documentation-image-missing');
			$notice->appendChild($dom->createTextNode($this->language->lang('DOCUMENTATION_IMAGE_MISSING')));
			$img->parentNode->replaceChild($notice, $img);
		}
	}

	/**
	 * Resolves an <img src> — relative (dot-notation, relative to the
	 * current page's own directory depth) or already rewritten absolute
	 * (/documentation/<lang>/...) — to a path relative to that language's
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

		if (strpos($src, '/documentation/') === 0)
		{
			$segments = explode('/', trim(substr($src, strlen('/documentation/')), '/'));
		}
		else if (strpos($src, '/') === 0)
		{
			// Absolute but not under our route (e.g. still /<lang>/...
			// if rewrite_absolute_links() ran first, which it always
			// does before this is called).
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
