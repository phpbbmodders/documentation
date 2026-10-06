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

class m6_search_cache_time extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['phpbbmodders_documentation_search_cache_minutes']);
	}

	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m5_documentation_icon');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('phpbbmodders_documentation_search_cache_minutes', 60)),
		);
	}
}
