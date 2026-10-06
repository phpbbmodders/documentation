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

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\exception\http_exception;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class documentation_controller
{
	/** Stands in for a page path in the page route given to the search script. */
	const SEARCH_PATH_PLACEHOLDER = 'documentation-search-path';

	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var controller_helper */
	protected $controller_helper;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var documentation_helper */
	protected $doc_helper;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/** @var request_interface */
	protected $request;

	public function __construct(auth $auth, config $config, controller_helper $controller_helper, template $template, user $user, documentation_helper $doc_helper, $phpbb_root_path, $php_ext, request_interface $request)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->controller_helper = $controller_helper;
		$this->template = $template;
		$this->user = $user;
		$this->doc_helper = $doc_helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->request = $request;
	}

	/**
	 * Handles all three documentation routes ({lang} and {path} both optional).
	 *
	 * @param string|null $lang
	 * @param string $path
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle($lang, $path)
	{
		$path = trim((string) $path, '/');

		if (!(bool) $this->config['phpbbmodders_documentation_enabled'])
		{
			throw new http_exception(503, 'DOCUMENTATION_DISABLED');
		}

		if ($this->doc_helper->get_docs_root() === false)
		{
			throw new http_exception(503, 'DOCUMENTATION_NOT_BUILT');
		}

		if (empty($lang))
		{
			$resolved = $this->doc_helper->resolve_default_language();
			$this->doc_helper->set_language_cookie($resolved);

			return new RedirectResponse($this->route_for($resolved, $path), 302);
		}

		if (!$this->doc_helper->is_known_language($lang))
		{
			$fallback = $this->doc_helper->get_fallback_language();

			return new RedirectResponse($this->route_for($fallback, $path), 302);
		}

		$section = $this->doc_helper->get_section($path);

		if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang))
		{
			return $this->access_denied_response();
		}

		$allowed_sections = $this->allowed_sections($lang, $section);

		if ($section === '' ? empty($allowed_sections) : !in_array($section, $allowed_sections, true))
		{
			return $this->access_denied_response();
		}

		$content_lang = $lang;
		if ($this->doc_helper->get_content_file($lang, $path) === false)
		{
			$content_lang = $this->doc_helper->get_fallback_language();
			if ($content_lang === $lang || $this->doc_helper->get_content_file($content_lang, $path) === false)
			{
				throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
			}
			if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $content_lang))
			{
				return $this->access_denied_response();
			}
			$allowed_sections = $this->allowed_sections($content_lang, $section);
			if ($section === '' ? empty($allowed_sections) : !in_array($section, $allowed_sections, true))
			{
				return $this->access_denied_response();
			}
		}

		// Do not let the loader substitute another language after authorization.
		$result = $this->doc_helper->resolve_and_load($content_lang, $path, $allowed_sections, false);

		if ($result === false)
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}

		$result['used_fallback'] = $content_lang !== $lang;
		$this->doc_helper->set_language_cookie($lang);

		$this->assign_template_vars($lang, $result, $section);

		return $this->controller_helper->render('documentation_body.html', $result['title']);
	}

	/**
	 * Search page. Results come from Pagefind in the browser
	 * (documentation-search.js); this only passes it the search bundles
	 * of sections the user may read.
	 *
	 * @param string $lang
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function search($lang)
	{
		if (!(bool) $this->config['phpbbmodders_documentation_enabled'])
		{
			throw new http_exception(503, 'DOCUMENTATION_DISABLED');
		}
		if ($this->doc_helper->get_docs_root() === false)
		{
			throw new http_exception(503, 'DOCUMENTATION_NOT_BUILT');
		}
		if (!$this->doc_helper->is_known_language($lang))
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}
		$scope = $this->request->variable('scope', '') === documentation_helper::DEVDOCS_SECTION
			? documentation_helper::DEVDOCS_SECTION : '';
		$sections = $this->allowed_sections($lang, $scope);
		if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang) || empty($sections))
		{
			return $this->access_denied_response();
		}
		$bundles = array();
		foreach ($this->doc_helper->get_search_bundle_sections($lang, $sections) as $section)
		{
			$bundles[] = $this->search_bundle_url($lang, $section);
		}
		if (empty($bundles))
		{
			throw new http_exception(503, 'DOCUMENTATION_SEARCH_UNAVAILABLE');
		}
		$query = trim(htmlspecialchars_decode($this->request->variable('q', '', true), ENT_QUOTES));
		// Pagefind returns Hugo paths (/<lang>/<path>/); the search script
		// swaps the placeholder in this page route for each <path>.
		$page_template = $this->controller_helper->route('phpbbmodders_documentation_page',
			array('lang' => $lang, 'path' => self::SEARCH_PATH_PLACEHOLDER), false);
		$this->template->assign_vars(array(
			'U_DOCUMENTATION_SEARCH' => $this->controller_helper->route('phpbbmodders_documentation_search', array('lang' => $lang)),
			'U_DOCUMENTATION_SEARCH_BACK' => $this->route_for($lang, $scope),
			'DOCUMENTATION_SEARCH_SCOPE' => $scope,
			'DOCUMENTATION_SEARCH_QUERY' => mb_substr($query, 0, 100),
			'DOCUMENTATION_SEARCH_BUNDLES' => json_encode($bundles),
			'DOCUMENTATION_SEARCH_LINKS' => json_encode(array(
				'prefix' => '/' . $lang . '/',
				'template' => $page_template,
				'placeholder' => self::SEARCH_PATH_PLACEHOLDER,
			)),
			'DOCUMENTATION_SEARCH_MESSAGES' => json_encode(array(
				'length' => $this->user->lang('DOCUMENTATION_SEARCH_LENGTH'),
				'noResults' => $this->user->lang('DOCUMENTATION_SEARCH_NO_RESULTS'),
				'unavailable' => $this->user->lang('DOCUMENTATION_SEARCH_UNAVAILABLE'),
				'loading' => $this->user->lang('DOCUMENTATION_SEARCH_LOADING'),
			)),
		));
		$response = $this->controller_helper->render('documentation_search.html', $this->user->lang('DOCUMENTATION_SEARCH'));
		$response->headers->set('Cache-Control', 'private, no-store');
		return $response;
	}

	/**
	 * Serves one file of a section's Pagefind search bundle, with the same
	 * language and section permission checks as the section's pages.
	 *
	 * @param string $lang
	 * @param string $section
	 * @param string $path File path inside the bundle.
	 * @return BinaryFileResponse
	 */
	public function search_bundle($lang, $section, $path)
	{
		if (!(bool) $this->config['phpbbmodders_documentation_enabled'])
		{
			throw new http_exception(503, 'DOCUMENTATION_DISABLED');
		}
		if ($this->doc_helper->get_docs_root() === false)
		{
			throw new http_exception(503, 'DOCUMENTATION_NOT_BUILT');
		}
		if (!$this->doc_helper->is_known_language($lang))
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}
		if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang)
			|| !in_array($section, $this->allowed_sections($lang, $section), true))
		{
			throw new http_exception(403, 'DOCUMENTATION_ACCESS_DENIED');
		}

		$file = $this->doc_helper->get_search_bundle_file($lang, $section, $path);
		if ($file === false)
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}

		return new BinaryFileResponse($file, 200, array(
			'Content-Type' => $this->doc_helper->get_search_bundle_type($path),
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control' => $this->doc_helper->get_search_bundle_cache_control($path),
		), false);
	}

	public function image($lang, $path)
	{
		if (!(bool) $this->config['phpbbmodders_documentation_enabled'])
		{
			throw new http_exception(503, 'DOCUMENTATION_DISABLED');
		}
		if ($this->doc_helper->get_docs_root() === false)
		{
			throw new http_exception(503, 'DOCUMENTATION_NOT_BUILT');
		}

		$page = $this->request->variable('page', '');
		$page_lang = $this->request->variable('page_lang', (string) $lang);
		if (!$this->doc_helper->is_known_language($lang) || !$this->doc_helper->is_known_language($page_lang))
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}
		if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang)
			|| !$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $page_lang))
		{
			throw new http_exception(403, 'DOCUMENTATION_ACCESS_DENIED');
		}
		$section = $this->doc_helper->get_section($page);
		$allowed_sections = $this->allowed_sections($page_lang, $section);
		if ($section === '' ? empty($allowed_sections) : !in_array($section, $allowed_sections, true))
		{
			throw new http_exception(403, 'DOCUMENTATION_ACCESS_DENIED');
		}

		$file = $this->doc_helper->get_referenced_image_file($page_lang, $page, $lang, $path);
		$info = $file !== false ? @getimagesize($file) : false;
		if ($info === false || !in_array($info['mime'], array('image/png', 'image/gif', 'image/jpeg', 'image/webp', 'image/avif', 'image/bmp', 'image/x-icon'), true))
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}

		return new BinaryFileResponse($file, 200, array(
			'Content-Type' => $info['mime'],
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control' => 'private, no-store',
		), false);
	}

	/**
	 * A guest lacking the required permission is sent to log in (with a
	 * return URL back to this page) rather than shown a bare 403 — they
	 * may well have access once signed in. A signed-in user without
	 * access gets the 403, matching phpBB's usual pattern for restricted
	 * areas (login_box() vs. SORRY_AUTH_READ).
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function access_denied_response()
	{
		if (empty($this->user->data['is_registered']))
		{
			$login_url = append_sid(
				"{$this->phpbb_root_path}ucp.{$this->php_ext}",
				'mode=login&redirect=' . urlencode($this->controller_helper->get_current_url())
			);

			return new RedirectResponse($login_url, 302);
		}

		throw new http_exception(403, 'DOCUMENTATION_ACCESS_DENIED');
	}

	/**
	 * @param string $lang
	 * @param string $current_section The section of the page actually
	 *        being requested — used to scope the result to just its own
	 *        side (docs vs. developer docs) when the nav is split into
	 *        two links, so e.g. a Developer Documentation page's sidebar
	 *        never lists end-user sections and vice versa.
	 * @return array Section slugs the current user may view in $lang.
	 */
	protected function allowed_sections($lang, $current_section = '')
	{
		$allowed = array();
		$split = (bool) $this->config['phpbbmodders_documentation_split_nav_links'];
		$devdocs_side = $split && $this->doc_helper->is_devdocs_section($current_section);

		foreach ($this->doc_helper->get_available_sections($lang) as $section)
		{
			if (!$this->auth->acl_get('u_phpbbmodders_documentation_' . $section))
			{
				continue;
			}

			if ($split && $this->doc_helper->is_devdocs_section($section) !== $devdocs_side)
			{
				continue;
			}

			$allowed[] = $section;
		}

		return $allowed;
	}

	/**
	 * @param string $lang
	 * @param string $path
	 * @return string
	 */
	protected function route_for($lang, $path)
	{
		return $path !== ''
			? $this->controller_helper->route('phpbbmodders_documentation_page', array('lang' => $lang, 'path' => $path))
			: $this->controller_helper->route('phpbbmodders_documentation_lang_root', array('lang' => $lang));
	}

	/**
	 * Base URL of a section's search bundle, ending in "/". Pagefind
	 * appends file names to it, so any query string (such as a session
	 * ID) is dropped; bundle requests rely on the session cookie.
	 *
	 * @param string $lang
	 * @param string $section
	 * @return string
	 */
	protected function search_bundle_url($lang, $section)
	{
		$url = $this->controller_helper->route('phpbbmodders_documentation_search_bundle',
			array('lang' => $lang, 'section' => $section, 'path' => 'pagefind.js'), false);
		$url = strtok($url, '?#');

		return substr($url, 0, strrpos($url, '/') + 1);
	}

	/**
	 * @param string $lang
	 * @param array $result From documentation_helper::resolve_and_load().
	 * @return void
	 */
	protected function assign_template_vars($lang, array $result, $section = '')
	{
		$this->template->assign_vars(array(
			'DOCUMENTATION_TITLE'           => $result['title'],
			'DOCUMENTATION_BREADCRUMB'      => $result['breadcrumb_html'],
			'DOCUMENTATION_SIDEBAR'         => $result['sidebar_html'],
			'DOCUMENTATION_ARTICLE'         => $result['article_html'],
			'S_DOCUMENTATION_USED_FALLBACK' => $result['used_fallback'],
			'S_DOCUMENTATION_MANUAL_SWITCH' => (bool) $this->config['phpbbmodders_documentation_manual_switch'],
			'U_DOCUMENTATION_SEARCH' => $this->controller_helper->route('phpbbmodders_documentation_search', array('lang' => $lang)),
			'DOCUMENTATION_SEARCH_SCOPE' => $this->doc_helper->is_devdocs_section($section) ? documentation_helper::DEVDOCS_SECTION : '',
		));

		$labels = $this->doc_helper->get_language_labels();

		foreach ($this->doc_helper->get_available_languages() as $code)
		{
			if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $code))
			{
				continue;
			}

			$this->template->assign_block_vars('documentation_language', array(
				'CODE'      => $code,
				'LABEL'     => isset($labels[$code]) ? $labels[$code] : $code,
				'U_SELECT'  => $this->controller_helper->route('phpbbmodders_documentation_lang_root', array('lang' => $code)),
				'S_CURRENT' => $code === $lang,
			));
		}
	}
}
