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

class m3_acp_category extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m2_nav_split');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'add_category'))),
		);
	}

	public function revert_data()
	{
		return array(
			array('custom', array(array($this, 'remove_category'))),
		);
	}

	public function add_category()
	{
		$module_tool = $this->container->get('migrator.tool.module');
		if (!$module_tool->exists('acp', 'ACP_CAT_DOT_MODS', 'ACP_DOCUMENTATION_TITLE'))
		{
			$module_tool->add('acp', 'ACP_CAT_DOT_MODS', 'ACP_DOCUMENTATION_TITLE');
		}

		$this->move_settings('ACP_CAT_DOT_MODS', 'ACP_DOCUMENTATION_TITLE');
	}

	public function remove_category()
	{
		$this->move_settings('ACP_DOCUMENTATION_TITLE', 'ACP_CAT_DOT_MODS');
		$this->container->get('migrator.tool.module')->remove('acp', 'ACP_CAT_DOT_MODS', 'ACP_DOCUMENTATION_TITLE');
	}

	protected function move_settings($from, $to)
	{
		$module_tool = $this->container->get('migrator.tool.module');
		$from_id = $module_tool->get_parent_module_id($from, '', false);
		if ($from_id === false)
		{
			return;
		}
		$to_id = $module_tool->get_parent_module_id($to);
		$sql = 'SELECT module_id, module_basename, module_class
			FROM ' . MODULES_TABLE . "
			WHERE module_class = 'acp'
				AND module_basename = '" . $this->db->sql_escape('\phpbbmodders\documentation\acp\main_module') . "'
				AND module_mode = 'settings'
				AND parent_id = " . (int) $from_id;
		$result = $this->db->sql_query($sql);
		$modules = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$module_manager = $this->container->get('module.manager');
		foreach ($modules as $module)
		{
			$module['parent_id'] = (int) $to_id;
			$module_manager->update_module_data($module);
		}
		$module_manager->remove_cache_file('acp');
	}
}
