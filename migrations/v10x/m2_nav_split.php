<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\migrations\v10x;

class m2_nav_split extends \phpbb\db\migration\container_aware_migration
{
	public function effectively_installed()
	{
		return isset($this->config['phpbbmodders_documentation_enabled']);
	}

	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m1_initial_data');
	}

	public function update_data()
	{
		return array(
			// All default to reproducing the extension's original,
			// pre-split behavior exactly: enabled, one combined
			// "Documentation" link, no label overrides.
			array('config.add', array('phpbbmodders_documentation_enabled', 1)),
			array('config.add', array('phpbbmodders_documentation_split_nav_links', 0)),
			array('config.add', array('phpbbmodders_documentation_devdocs_nav_link', 1)),
			array('config.add', array('phpbbmodders_documentation_nav_label_map', '')),
			array('config.add', array('phpbbmodders_documentation_devdocs_nav_label_map', '')),
		);
	}
}
