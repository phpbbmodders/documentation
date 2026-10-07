<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\tests\migrations;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;
use phpbbmodders\documentation\migrations\v10x\m9_docs_build_path;
use PHPUnit\Framework\TestCase;

class m9_docs_build_path_test extends TestCase
{
	/** @var string Temporary phpBB root, with a trailing slash. */
	protected $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/documentation_m9_' . uniqid() . '/';
		foreach (m9_docs_build_path::PREVIOUS_DEFAULT_PATHS as $path)
		{
			mkdir($this->root . $path, 0777, true);
		}
	}

	protected function tearDown(): void
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file)
		{
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->root);
	}

	/**
	 * @param string $docs_path
	 * @return array [migration, config]
	 */
	protected function get_migration($docs_path)
	{
		$config = $this->getMockBuilder(config::class)
			->setConstructorArgs(array(array('phpbbmodders_documentation_docs_path' => $docs_path)))
			->onlyMethods(array('set'))
			->getMock();
		$config->method('set')->willReturnCallback(function ($key, $value) use ($config) {
			$config->offsetSet($key, $value);
		});

		$migration = new m9_docs_build_path(
			$config,
			$this->createMock(driver_interface::class),
			$this->createMock(tools_interface::class),
			$this->root,
			'php',
			'phpbb_'
		);

		return array($migration, $config);
	}

	public static function previous_default_provider()
	{
		return array_map(function ($path) {
			return array($path);
		}, m9_docs_build_path::PREVIOUS_DEFAULT_PATHS);
	}

	/** @dataProvider previous_default_provider */
	public function test_empty_previous_default_moves_to_docs_build($path)
	{
		list($migration, $config) = $this->get_migration($path);

		$migration->use_docs_build_path();

		$this->assertSame(m9_docs_build_path::NEW_DEFAULT_PATH, $config['phpbbmodders_documentation_docs_path']);
	}

	/** @dataProvider previous_default_provider */
	public function test_previous_default_with_a_build_is_kept($path)
	{
		mkdir($this->root . $path . '/en');
		file_put_contents($this->root . $path . '/en/index.html', 'page');
		list($migration, $config) = $this->get_migration($path);

		$migration->use_docs_build_path();

		$this->assertSame($path, $config['phpbbmodders_documentation_docs_path']);
	}

	public function test_custom_path_is_kept()
	{
		list($migration, $config) = $this->get_migration('/srv/docs');

		$migration->use_docs_build_path();

		$this->assertSame('/srv/docs', $config['phpbbmodders_documentation_docs_path']);
	}

	public function test_revert_restores_only_the_new_default()
	{
		list($migration, $config) = $this->get_migration(m9_docs_build_path::NEW_DEFAULT_PATH);
		$migration->restore_previous_path();
		$this->assertSame(m9_docs_build_path::REVERT_PATH, $config['phpbbmodders_documentation_docs_path']);

		list($migration, $config) = $this->get_migration('/srv/docs');
		$migration->restore_previous_path();
		$this->assertSame('/srv/docs', $config['phpbbmodders_documentation_docs_path']);
	}
}
