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
 * Renames the default docs build directory to store/docs_build. Boards
 * still set to an earlier default with no build there move to the new
 * default; boards with a build at their current path keep it.
 */
class m9_docs_build_path extends \phpbb\db\migration\migration
{
	/** Earlier defaults, relative to the phpBB root. */
	const PREVIOUS_DEFAULT_PATHS = array(
		'ext/phpbbmodders/documentation/docs-build',
		'store/phpbbmodders_documentation',
	);

	/** New default, relative to the phpBB root. */
	const NEW_DEFAULT_PATH = 'store/docs_build';

	/** What a revert restores: the default set by m7_store_docs_path. */
	const REVERT_PATH = 'store/phpbbmodders_documentation';

	public static function depends_on()
	{
		return array('\phpbbmodders\documentation\migrations\v10x\m8_search_max_results');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'use_docs_build_path'))),
		);
	}

	public function revert_data()
	{
		return array(
			array('custom', array(array($this, 'restore_previous_path'))),
		);
	}

	/**
	 * @return void
	 */
	public function use_docs_build_path()
	{
		$current = $this->config['phpbbmodders_documentation_docs_path'];
		if (in_array($current, self::PREVIOUS_DEFAULT_PATHS, true) && !$this->has_build($this->phpbb_root_path . $current))
		{
			$this->config->set('phpbbmodders_documentation_docs_path', self::NEW_DEFAULT_PATH);
		}
	}

	/**
	 * @return void
	 */
	public function restore_previous_path()
	{
		if ($this->config['phpbbmodders_documentation_docs_path'] === self::NEW_DEFAULT_PATH)
		{
			$this->config->set('phpbbmodders_documentation_docs_path', self::REVERT_PATH);
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
