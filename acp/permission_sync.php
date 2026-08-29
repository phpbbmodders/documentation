<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\acp;

use phpbb\db\driver\driver_interface;
use phpbb\cache\service as cache_service;
use phpbb\auth\auth;
use phpbb\notification\manager as notification_manager;
use phpbbmodders\documentation\controller\documentation_helper;

/**
 * Reconciles registered u_phpbbmodders_documentation_* ACL permissions
 * against whatever sections/languages are currently found in the docs
 * build, and keeps the missing-build notification in sync. Shared by the
 * install migration and the ACP "resync permissions" action so both go
 * through the exact same idempotent logic.
 */
class permission_sync
{
	/**
	 * Documentation defaults to being generally accessible — a newly
	 * discovered section/language permission is granted the moment it's
	 * first registered, matching how phpBB's own public content (e.g.
	 * f_read) defaults to open. This only happens once, at creation time:
	 * a resync never re-grants a permission an admin has since
	 * deliberately revoked.
	 *
	 * Registered/Registered COPPA go through the standard user roles
	 * (matching how phpBB's own default install grants u_-prefixed
	 * permissions to those groups, and how sibling phpbbmodders
	 * extensions like banlist do it). Guests and Bots are granted
	 * directly on the group instead: phpBB's own install fixture
	 * (install/schemas/schema_data.sql) sets the Guests group's default
	 * u_ permissions with auth_role_id = 0 — a direct group grant, not a
	 * role — because Guests were never assigned ROLE_USER_* in the first
	 * place.
	 */
	const DEFAULT_GRANTED_ROLES = array('ROLE_USER_FULL', 'ROLE_USER_STANDARD');
	const DEFAULT_GRANTED_GROUPS = array('GUESTS', 'BOTS');


	/** @var driver_interface */
	protected $db;

	/** @var cache_service */
	protected $cache;

	/** @var auth */
	protected $auth;

	/** @var notification_manager */
	protected $notification_manager;

	/** @var documentation_helper */
	protected $doc_helper;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(driver_interface $db, cache_service $cache, auth $auth, notification_manager $notification_manager, documentation_helper $doc_helper, $phpbb_root_path, $php_ext)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->auth = $auth;
		$this->notification_manager = $notification_manager;
		$this->doc_helper = $doc_helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * @return array Newly-registered permission names, for feedback to the admin.
	 */
	public function sync()
	{
		$added = array();
		$permission_tool = new \phpbb\db\migration\tool\permission($this->db, $this->cache, $this->auth, $this->phpbb_root_path, $this->php_ext);

		foreach ($this->doc_helper->get_available_languages() as $lang)
		{
			$permission = 'u_phpbbmodders_documentation_lang_' . $lang;
			if ($this->add_if_missing($permission_tool, $permission))
			{
				$added[] = $permission;
			}

			foreach ($this->doc_helper->get_available_sections($lang) as $section)
			{
				$permission = 'u_phpbbmodders_documentation_' . $section;
				if ($this->add_if_missing($permission_tool, $permission))
				{
					$added[] = $permission;
				}
			}
		}

		$this->auth->acl_clear_prefetch();
		$this->sync_build_notification();

		return $added;
	}

	/**
	 * @return void
	 */
	public function sync_build_notification()
	{
		if ($this->doc_helper->get_docs_root() === false)
		{
			$this->notification_manager->add_notifications('notification.type.phpbbmodders_documentation_no_build', array());
		}
		else
		{
			// item_id 1: this is a singleton system-status notification,
			// not tied to a specific row, so the type always reports a
			// fixed item id (see no_docs_build::get_item_id()).
			$this->notification_manager->delete_notifications('notification.type.phpbbmodders_documentation_no_build', 1);
		}
	}

	/**
	 * @param \phpbb\db\migration\tool\permission $permission_tool
	 * @param string $permission
	 * @return bool True if the permission was newly added.
	 */
	protected function add_if_missing(\phpbb\db\migration\tool\permission $permission_tool, $permission)
	{
		// Global, not local/per-forum: these permissions have nothing to
		// do with individual forums, matching both the official skeleton's
		// own sample migration and banlist's u_viewban (both omit $global,
		// which defaults to true).
		if ($permission_tool->exists($permission))
		{
			return false;
		}

		$permission_tool->add($permission);

		foreach (self::DEFAULT_GRANTED_ROLES as $role_name)
		{
			$permission_tool->permission_set($role_name, $permission, 'role', true);
		}

		foreach (self::DEFAULT_GRANTED_GROUPS as $group_name)
		{
			$permission_tool->permission_set($group_name, $permission, 'group', true);
		}

		return true;
	}
}
