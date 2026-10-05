<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation;

class ext extends \phpbb\extension\base
{
	/**
	 * Check whether the extension can be enabled.
	 * The current phpBB version should meet or exceed
	 * the minimum version required by this extension.
	 *
	 * @return bool|array
	 * @access public
	 */
	public function is_enableable()
	{
		$enableable = $this->check_phpbb_version() && $this->check_php_version();

		if (!$enableable)
		{
			$language = $this->container->get('language');
			$language->add_lang('install_documentation', 'phpbbmodders/documentation');

			return $language->lang('DOCUMENTATION_NOT_ENABLEABLE');
		}

		return $enableable;
	}

	/**
	 * Require phpBB 3.3.19
	 *
	 * @return bool
	 */
	public function check_phpbb_version()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=');
	}

	/**
	 * Require PHP 8.0
	 *
	 * @return bool
	 */
	public function check_php_version()
	{
		return PHP_VERSION_ID >= 80000;
	}

	/**
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function enable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')
				->enable_notifications('notification.type.phpbbmodders_documentation_no_build');

			return 'notification';
		}

		return parent::enable_step($old_state);
	}

	/**
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function disable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')
				->disable_notifications('notification.type.phpbbmodders_documentation_no_build');

			return 'notification';
		}

		return parent::disable_step($old_state);
	}

	/**
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function purge_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')
				->purge_notifications('notification.type.phpbbmodders_documentation_no_build');

			$this->purge_permissions();
			$this->container->get('config')->delete('phpbbmodders_documentation_sync_pending');

			return 'notification';
		}

		return parent::purge_step($old_state);
	}

	/** Remove owned ACL options even when their build sections no longer exist. */
	protected function purge_permissions()
	{
		$db = $this->container->get('dbal.conn');
		$result = $db->sql_query('SELECT auth_option FROM ' . ACL_OPTIONS_TABLE . '
			WHERE auth_option ' . $db->sql_like_expression('u_phpbbmodders_documentation_' . $db->get_any_char()));
		$permissions = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$permissions[] = $row['auth_option'];
		}
		$db->sql_freeresult($result);

		$tool = new \phpbb\db\migration\tool\permission(
			$db,
			$this->container->get('cache'),
			$this->container->get('auth'),
			$this->container->getParameter('core.root_path'),
			$this->container->getParameter('core.php_ext')
		);
		foreach ($permissions as $permission)
		{
			$tool->remove($permission);
			$tool->remove($permission, false);
		}
	}
}
