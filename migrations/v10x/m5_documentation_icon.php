<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\migrations\v10x;

class m5_documentation_icon extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m4_nav_icons');
	}

	public function update_data()
	{
		if ($this->config['phpbbmodders_documentation_nav_icon'] === 'fa-book')
		{
			return array(
				array('config.update', array('phpbbmodders_documentation_nav_icon', 'fa-file-text-o')),
			);
		}

		return array();
	}
}
