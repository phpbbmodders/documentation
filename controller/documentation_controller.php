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

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\exception\http_exception;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\HttpFoundation\RedirectResponse;

class documentation_controller
{
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

	public function __construct(auth $auth, config $config, controller_helper $controller_helper, template $template, user $user, documentation_helper $doc_helper, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->controller_helper = $controller_helper;
		$this->template = $template;
		$this->user = $user;
		$this->doc_helper = $doc_helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
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

		$allowed_sections = $this->allowed_sections($lang);

		if ($section === '' ? empty($allowed_sections) : !in_array($section, $allowed_sections, true))
		{
			return $this->access_denied_response();
		}

		$result = $this->doc_helper->resolve_and_load($lang, $path, $allowed_sections);

		if ($result === false)
		{
			throw new http_exception(404, 'DOCUMENTATION_PAGE_NOT_FOUND');
		}

		$this->doc_helper->set_language_cookie($lang);

		$this->assign_template_vars($lang, $result);

		return $this->controller_helper->render('documentation_body.html', $result['title']);
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
	 * @return array Section slugs the current user may view in $lang.
	 */
	protected function allowed_sections($lang)
	{
		$allowed = array();

		foreach ($this->doc_helper->get_available_sections($lang) as $section)
		{
			if ($this->auth->acl_get('u_phpbbmodders_documentation_' . $section))
			{
				$allowed[] = $section;
			}
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
	 * @param string $lang
	 * @param array $result From documentation_helper::resolve_and_load().
	 * @return void
	 */
	protected function assign_template_vars($lang, array $result)
	{
		$this->template->assign_vars(array(
			'DOCUMENTATION_TITLE'           => $result['title'],
			'DOCUMENTATION_BREADCRUMB'      => $result['breadcrumb_html'],
			'DOCUMENTATION_SIDEBAR'         => $result['sidebar_html'],
			'DOCUMENTATION_ARTICLE'         => $result['article_html'],
			'S_DOCUMENTATION_USED_FALLBACK' => $result['used_fallback'],
			'S_DOCUMENTATION_MANUAL_SWITCH' => (bool) $this->config['phpbbmodders_documentation_manual_switch'],
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
