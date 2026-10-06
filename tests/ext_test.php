<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\tests;

use PHPUnit\Framework\TestCase;
use phpbbmodders\documentation\ext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ext_test extends TestCase
{
	public function test_purge_removes_owned_options_and_all_grants_but_disable_preserves_them()
	{
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		foreach (array('ACL_OPTIONS', 'ACL_GROUPS', 'ACL_ROLES_DATA', 'ACL_USERS') as $name)
		{
			if (!defined($name . '_TABLE'))
			{
				define($name . '_TABLE', strtolower($name));
			}
		}
		$old_db = isset($GLOBALS['db']) ? $GLOBALS['db'] : null;
		$old_cache = isset($GLOBALS['cache']) ? $GLOBALS['cache'] : null;
		$db = new \phpbb\db\driver\sqlite3();
		$db->sql_connect(':memory:', '', '', '');
		$GLOBALS['db'] = $db;
		$GLOBALS['cache'] = null;
		try
		{
			$db->sql_query('CREATE TABLE ' . ACL_OPTIONS_TABLE . ' (auth_option_id INTEGER PRIMARY KEY, auth_option TEXT, is_global INTEGER, is_local INTEGER)');
			$tables = array(ACL_GROUPS_TABLE, ACL_ROLES_DATA_TABLE, ACL_USERS_TABLE);
			foreach ($tables as $table)
			{
				$db->sql_query('CREATE TABLE ' . $table . ' (auth_option_id INTEGER, auth_setting INTEGER)');
			}
			$options = array(
				1 => 'u_phpbbmodders_documentation_lang_removed',
				2 => 'u_phpbbmodders_documentation_old_section',
				3 => 'u_phpbbmodders_documentation_dual_scope',
				4 => 'u_other_extension',
				5 => 'uXphpbbmoddersXdocumentationXdecoy',
			);
			foreach ($options as $id => $name)
			{
				$db->sql_query('INSERT INTO ' . ACL_OPTIONS_TABLE . " VALUES ($id, '$name', 1, " . ($id === 3 ? 1 : 0) . ')');
				foreach ($tables as $table)
				{
					$db->sql_query('INSERT INTO ' . $table . " VALUES ($id, " . ($id === 2 ? -1 : 1) . ')');
				}
			}
			$config = new \phpbb\config\config(array('phpbbmodders_documentation_sync_pending' => 1));
			$cache_driver = $this->createMock(\phpbb\cache\driver\driver_interface::class);
			$cache_driver->method('get')->willReturn(false);
			$cache_driver->method('sql_exists')->willReturn(false);
			$cache_driver->expects($this->atLeastOnce())->method('destroy')->with('_acl_options');
			$root = dirname((new \ReflectionClass(\phpbb\auth\auth::class))->getFileName(), 3) . '/';
			$cache = new \phpbb\cache\service($cache_driver, $config, $db,
				$this->createMock(\phpbb\event\dispatcher::class), $root, 'php');
			$GLOBALS['cache'] = $cache;
			$auth = $this->createMock(\phpbb\auth\auth::class);
			$auth->expects($this->atLeastOnce())->method('acl_clear_prefetch');
			$notifications = $this->createMock(\phpbb\notification\manager::class);
			$notifications->expects($this->once())->method('disable_notifications');
			$notifications->expects($this->exactly(2))->method('purge_notifications');
			$container = new ContainerBuilder();
			foreach (array('dbal.conn' => $db, 'cache' => $cache, 'auth' => $auth,
				'config' => $config, 'notification_manager' => $notifications) as $id => $service)
			{
				$container->set($id, $service);
			}
			$container->setParameter('core.root_path', $root);
			$container->setParameter('core.php_ext', 'php');
			// phpBB 4.0 moved the extension finder from \phpbb\finder to \phpbb\finder\finder.
			$finder_class = class_exists(\phpbb\finder\finder::class) ? \phpbb\finder\finder::class : \phpbb\finder::class;
			$extension = new ext($container, $this->createMock($finder_class),
				$this->createMock(\phpbb\db\migrator::class), 'phpbbmodders/documentation', '');
			$extension->disable_step(false);
			$this->assertCount(5, $this->rows($db, ACL_OPTIONS_TABLE));
			$this->assertTrue(isset($config['phpbbmodders_documentation_sync_pending']));
			$extension->purge_step(false);
			$this->assertFalse(isset($config['phpbbmodders_documentation_sync_pending']));
			foreach (array_merge(array(ACL_OPTIONS_TABLE), $tables) as $table)
			{
				$this->assertSame(array(4, 5), array_map('intval', array_column($this->rows($db, $table), 'auth_option_id')));
			}
			$extension->purge_step(false);
			$this->assertCount(2, $this->rows($db, ACL_OPTIONS_TABLE));
		}
		finally
		{
			$GLOBALS['db'] = $old_db;
			$GLOBALS['cache'] = $old_cache;
			$db->sql_close();
		}
	}

	protected function rows($db, $table)
	{
		$result = $db->sql_query('SELECT * FROM ' . $table . ' ORDER BY auth_option_id');
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);
		return $rows;
	}
}
