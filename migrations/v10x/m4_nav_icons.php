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

class m4_nav_icons extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m3_acp_category');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('phpbbmodders_documentation_nav_icon', 'fa-book')),
			array('config.add', array('phpbbmodders_documentation_nav_icon_enabled', 0)),
			array('config.add', array('phpbbmodders_documentation_devdocs_nav_icon', 'fa-code')),
			array('config.add', array('phpbbmodders_documentation_devdocs_nav_icon_enabled', 0)),
		);
	}
}
