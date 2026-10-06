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

class m8_search_max_results extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['phpbbmodders_documentation_search_max_results']);
	}

	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m7_store_docs_path');
	}

	public function update_data()
	{
		return array(
			// 50 was the fixed limit before this setting existed.
			array('config.add', array('phpbbmodders_documentation_search_max_results', 50)),
		);
	}
}
