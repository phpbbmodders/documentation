<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
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
 * Injects the "Documentation" link into phpBB's top navbar, only for users
 * who hold at least one section permission and at least one language
 * permission — and only if the ACP setting for it is enabled. Disabling
 * the nav link only hides it; the documentation itself stays reachable by
 * URL for anyone with the underlying permissions.
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
	 * @return void
	 */
	public function add_navigation_link()
	{
		$visible = (bool) $this->config['phpbbmodders_documentation_nav_link']
			&& $this->doc_helper->get_docs_root() !== false
			&& $this->has_any_access();

		$this->template->assign_vars(array(
			'S_DOCUMENTATION_NAV_VISIBLE' => $visible,
			'U_DOCUMENTATION'             => $visible ? $this->controller_helper->route('phpbbmodders_documentation_root') : '',
		));
	}

	/**
	 * @return bool
	 */
	protected function has_any_access()
	{
		foreach ($this->doc_helper->get_available_languages() as $lang)
		{
			if (!$this->auth->acl_get('u_phpbbmodders_documentation_lang_' . $lang))
			{
				continue;
			}

			foreach ($this->doc_helper->get_available_sections($lang) as $section)
			{
				if ($this->auth->acl_get('u_phpbbmodders_documentation_' . $section))
				{
					return true;
				}
			}
		}

		return false;
	}
}
