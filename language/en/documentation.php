<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'DOCUMENTATION'                        => 'Documentation',
	'DOCUMENTATION_DEV'                    => 'Developer Documentation',
	'DOCUMENTATION_IMAGE_MISSING'          => 'This image isn’t available.',
	'DOCUMENTATION_PAGE_FALLBACK'          => 'This page isn’t translated yet — showing it in the fallback language instead.',
	'DOCUMENTATION_ACCESS_DENIED'          => 'You don’t have permission to view this part of the documentation.',
	'DOCUMENTATION_PAGE_NOT_FOUND'         => 'That documentation page couldn’t be found.',
	'DOCUMENTATION_NOT_BUILT'              => 'The documentation hasn’t been built yet. Please check back later.',
	'DOCUMENTATION_DISABLED'               => 'Documentation is currently disabled.',

	'NOTIFICATION_DOCUMENTATION_NO_BUILD'      => 'No documentation build was found at the configured path.',
	'NOTIFICATION_TYPE_DOCUMENTATION_NO_BUILD' => 'Documentation build missing',
));
