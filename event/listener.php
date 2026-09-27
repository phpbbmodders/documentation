<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\event;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\template\template;
use phpbbmodders\documentation\controller\documentation_helper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects the "Documentation" link into phpBB's top navbar — and, if the
 * ACP's "split" setting is on, a second independent "Developer
 * Documentation" link — only for users who hold at least one relevant
 * section permission and at least one language permission, and only if
 * each link's own ACP setting is enabled. Disabling a nav link only
 * hides it; its documentation stays reachable by URL for anyone with the
 * underlying permissions. The master "enabled" setting is the one
 * exception: turning that off takes the documentation offline entirely,
 * hiding both links and returning 503 from both routes (see
 * documentation_controller).
 */
class listener implements EventSubscriberInterface
{
	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var controller_helper */
	protected $controller_helper;

	/** @var documentation_helper */
	protected $doc_helper;

	/** @var template */
	protected $template;

	public function __construct(auth $auth, config $config, controller_helper $controller_helper, documentation_helper $doc_helper, template $template)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->controller_helper = $controller_helper;
		$this->doc_helper = $doc_helper;
		$this->template = $template;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup'  => 'user_setup',
			'core.permissions' => 'add_permissions',
			'core.page_header' => 'add_navigation_link',
		);
	}

	/**
	 * Registers our language file for automatic loading on every page, so
	 * NOTIFICATION_DOCUMENTATION_NO_BUILD is available whenever the
	 * header's notification bell renders for a founder, and DOCUMENTATION_*
	 * strings are ready wherever a template needs them — without every
	 * call site having to remember to load it itself.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function user_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'phpbbmodders/documentation',
			'lang_set' => 'documentation',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Registers a readable label for each currently-known section/language
	 * permission on the ACP Permissions screen. The permission set is
	 * dynamic (whatever sections/languages the docs build actually has),
	 * so unlike a normal extension's fixed ACL_* language keys, these
	 * labels are built at runtime and passed straight through as the
	 * 'lang' value — phpBB's lang() falls back to echoing an unresolved
	 * key verbatim, so a plain label works the same as a real key.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function add_permissions($event)
	{
		foreach ($this->doc_helper->get_available_languages() as $lang)
		{
			$event->update_subarray('permissions', 'u_phpbbmodders_documentation_lang_' . $lang, array(
				'lang' => 'Documentation: view language "' . $lang . '"',
				'cat'  => 'misc',
			));

			foreach ($this->doc_helper->get_available_sections($lang) as $section)
			{
				$event->update_subarray('permissions', 'u_phpbbmodders_documentation_' . $section, array(
					'lang' => 'Documentation: view section "' . ucwords(str_replace(array('-', '_'), ' ', $section)) . '"',
					'cat'  => 'misc',
				));
			}
		}
	}

	/**
	 * Builds both nav links every time (rather than a single combined
	 * one) and lets the "split" config decide which of them actually
	 * render — see overall_header_navigation_append.html. Under the
	 * default combined mode this reproduces the extension's original
	 * single-link behavior exactly; splitting only changes which of the
	 * two S_..._NAV_VISIBLE flags can independently be true.
	 *
	 * @return void
	 */
	public function add_navigation_link()
	{
		$blank = array(
			'S_DOCUMENTATION_NAV_VISIBLE'          => false,
			'U_DOCUMENTATION'                      => '',
			'DOCUMENTATION_NAV_TEXT'               => '',
			'S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE'  => false,
			'U_DOCUMENTATION_DEVDOCS'              => '',
			'DOCUMENTATION_DEVDOCS_NAV_TEXT'       => '',
		);

		if (!(bool) $this->config['phpbbmodders_documentation_enabled'] || $this->doc_helper->get_docs_root() === false)
		{
			$this->template->assign_vars($blank);

			return;
		}

		if (!(bool) $this->config['phpbbmodders_documentation_split_nav_links'])
		{
			$visible = (bool) $this->config['phpbbmodders_documentation_nav_link'] && $this->has_any_access();

			$this->template->assign_vars(array_merge($blank, array(
				'S_DOCUMENTATION_NAV_VISIBLE' => $visible,
				'U_DOCUMENTATION'             => $visible ? $this->controller_helper->route('phpbbmodders_documentation_root') : '',
				'DOCUMENTATION_NAV_TEXT'      => $visible ? $this->doc_helper->get_nav_label('phpbbmodders_documentation_nav_label_map', 'DOCUMENTATION') : '',
			)));

			return;
		}

		$docs_visible = (bool) $this->config['phpbbmodders_documentation_nav_link'] && $this->has_any_access(false);
		$devdocs_visible = (bool) $this->config['phpbbmodders_documentation_devdocs_nav_link'] && $this->has_any_access(true);

		$this->template->assign_vars(array(
			'S_DOCUMENTATION_NAV_VISIBLE'         => $docs_visible,
			'U_DOCUMENTATION'                     => $docs_visible ? $this->controller_helper->route('phpbbmodders_documentation_root') : '',
			'DOCUMENTATION_NAV_TEXT'              => $docs_visible ? $this->doc_helper->get_nav_label('phpbbmodders_documentation_nav_label_map', 'DOCUMENTATION') : '',

			'S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE' => $devdocs_visible,
			'U_DOCUMENTATION_DEVDOCS'             => $devdocs_visible ? $this->devdocs_url() : '',
			'DOCUMENTATION_DEVDOCS_NAV_TEXT'      => $devdocs_visible ? $this->doc_helper->get_nav_label('phpbbmodders_documentation_devdocs_nav_label_map', 'DOCUMENTATION_DEV') : '',
		));
	}

	/**
	 * The docs-only link reuses the extension's existing lang-less root
	 * route (the controller resolves + redirects to the viewer's actual
	 * language, same as it always has). There's no equivalent lang-less
	 * route for a fixed path, so the developer-docs link resolves the
	 * language up front instead and links straight to the resolved URL.
	 *
	 * @return string
	 */
	protected function devdocs_url()
	{
		return $this->controller_helper->route('phpbbmodders_documentation_page', array(
			'lang' => $this->doc_helper->resolve_default_language(),
			'path' => documentation_helper::DEVDOCS_SECTION,
		));
	}

	/**
	 * @param bool|null $devdocs_only null: any section counts (combined
	 *        mode). true: only the developer-docs section counts. false:
	 *        only non-developer-docs sections count.
	 * @return bool
	 */
	protected function has_any_access($devdocs_only = null)
	{
		foreach ($this->doc_helper->get_available_languages() as $lang)
		{
			if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang))
			{
				continue;
			}

			foreach ($this->doc_helper->get_available_sections($lang) as $section)
			{
				if ($devdocs_only !== null && $this->doc_helper->is_devdocs_section($section) !== $devdocs_only)
				{
					continue;
				}

				if ($this->auth->acl_get('u_phpbbmodders_documentation_' . $section))
				{
					return true;
				}
			}
		}

		return false;
	}
}
