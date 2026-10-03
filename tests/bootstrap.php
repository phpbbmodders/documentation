<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

$documentation_phpbb_root = getenv('PHPBB_ROOT_PATH');
if ($documentation_phpbb_root === false || $documentation_phpbb_root === '')
{
	$documentation_phpbb_root = dirname(__DIR__, 4);
}
$documentation_phpbb_root = rtrim($documentation_phpbb_root, '/\\') . '/';
if (!is_file($documentation_phpbb_root . 'vendor/autoload.php')
	|| !is_dir($documentation_phpbb_root . 'phpbb'))
{
	throw new RuntimeException('Set PHPBB_ROOT_PATH to a phpBB installation with Composer dependencies.');
}

require_once $documentation_phpbb_root . 'vendor/autoload.php';
spl_autoload_register(function ($class) use ($documentation_phpbb_root) {
	$prefixes = array(
		'phpbb\\' => $documentation_phpbb_root . 'phpbb/',
		'phpbbmodders\\documentation\\' => dirname(__DIR__) . '/',
	);
	foreach ($prefixes as $prefix => $root)
	{
		if (strpos($class, $prefix) === 0)
		{
			$file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file))
			{
				require_once $file;
			}
			return;
		}
	}
});
