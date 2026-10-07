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

/**
 * Moves the default docs build location out of the extension's own
 * directory, which is deleted whenever the extension is updated, into
 * phpBB's store/ directory. Boards that changed the path, or already have
 * a build at the old default, keep their current setting.
 */
class m7_store_docs_path extends \phpbb\db\migration\migration
{
	/** Default path set by m1_initial_data. */
	const OLD_DEFAULT_PATH = 'ext/phpbbmodders/documentation/docs-build';

	/** New default, relative to the phpBB root. */
	const NEW_DEFAULT_PATH = 'store/phpbbmodders_documentation';

	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m6_search_cache_time');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'use_store_path'))),
		);
	}

	public function revert_data()
	{
		return array(
			array('custom', array(array($this, 'restore_old_path'))),
		);
	}

	/**
	 * @return void
	 */
	public function use_store_path()
	{
		if ($this->config['phpbbmodders_documentation_docs_path'] === self::OLD_DEFAULT_PATH
			&& !$this->has_build($this->phpbb_root_path . self::OLD_DEFAULT_PATH))
		{
			$this->config->set('phpbbmodders_documentation_docs_path', self::NEW_DEFAULT_PATH);
		}
	}

	/**
	 * @return void
	 */
	public function restore_old_path()
	{
		if ($this->config['phpbbmodders_documentation_docs_path'] === self::NEW_DEFAULT_PATH)
		{
			$this->config->set('phpbbmodders_documentation_docs_path', self::OLD_DEFAULT_PATH);
		}
	}

	/**
	 * Same test as documentation_helper::get_docs_root(): at least one
	 * language directory with its own index.html. The extension's
	 * services can't be used here because they may not be loaded yet.
	 *
	 * @param string $dir
	 * @return bool
	 */
	protected function has_build($dir)
	{
		if (!is_dir($dir))
		{
			return false;
		}

		foreach (new \DirectoryIterator($dir) as $entry)
		{
			if (!$entry->isDot() && $entry->isDir() && is_file($entry->getPathname() . '/index.html'))
			{
				return true;
			}
		}

		return false;
	}
}
