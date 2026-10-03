<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\acp;

class main_info
{
	public function module()
	{
		return array(
			'filename' => '\phpbbmodders\documentation\acp\main_module',
			'title'    => 'ACP_DOCUMENTATION_TITLE',
			'modes'    => array(
				'settings' => array(
					'title' => 'ACP_DOCUMENTATION_SETTINGS',
					'auth'  => 'ext_phpbbmodders/documentation && acl_a_board',
					'cat'   => array('ACP_DOCUMENTATION_TITLE'),
				),
			),
		);
	}
}
