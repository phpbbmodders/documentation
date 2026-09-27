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

class m1_initial_data extends \phpbb\db\migration\container_aware_migration
{
	public function effectively_installed()
	{
		return isset($this->config['phpbbmodders_documentation_docs_path']);
	}

	public static function depends_on()
	{
		return array('\phpbb\db\migration\data\v330\v330');
	}

	public function update_data()
	{
		return array(
			// Defaults to the extension's own docs-build/ directory, so
			// pointing proteus_hugo.sh's build output there (or copying
			// it in) works with zero ACP configuration. Still overridable
			// for a docs build kept elsewhere.
			array('config.add', array('phpbbmodders_documentation_docs_path', 'ext/phpbbmodders/documentation/docs-build')),
			array('config.add', array('phpbbmodders_documentation_fallback_lang', '')),
			array('config.add', array('phpbbmodders_documentation_manual_switch', 1)),
			array('config.add', array('phpbbmodders_documentation_nav_link', 1)),
			array('config.add', array('phpbbmodders_documentation_notify_recipients', 'founders')),
			array('config.add', array('phpbbmodders_documentation_lang_map', '')),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				array(
					'module_basename' => '\phpbbmodders\documentation\acp\main_module',
					'modes'           => array('settings'),
				),
			)),

			array('custom', array(array($this, 'sync_permissions'))),
		);
	}

	/**
	 * Registers the initial u_phpbbmodders_documentation_* permissions
	 * for whatever sections/languages are found in the docs build at
	 * install time, and seeds the missing-build notification if none is
	 * found yet. Re-run later via the ACP "resync permissions" action
	 * when the docs build grows a new section or language.
	 *
	 * @return void
	 */
	public function sync_permissions()
	{
		$this->container->get('phpbbmodders.documentation.permission_sync')->sync();
	}
}
