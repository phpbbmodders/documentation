<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation;

class ext extends \phpbb\extension\base
{
	/**
	 * Requires phpBB 3.3.0 or greater
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.0', '>=');
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

			return 'notification';
		}

		return parent::purge_step($old_state);
	}
}
