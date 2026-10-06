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

class main_module
{
	/** @var string $page_title The page title */
	public $page_title;

	/** @var string $tpl_name The page template name */
	public $tpl_name;

	/** @var string $u_action Custom form action */
	public $u_action;

	/**
	 * @param string $id
	 * @param string $mode
	 * @throws \Exception
	 */
	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');

		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');

		/** @var \phpbbmodders\documentation\controller\documentation_helper $doc_helper */
		$doc_helper = $phpbb_container->get('phpbbmodders.documentation.helper');

		/** @var \phpbbmodders\documentation\acp\permission_sync $permission_sync */
		$permission_sync = $phpbb_container->get('phpbbmodders.documentation.permission_sync');

		$language->add_lang('info_acp_documentation', 'phpbbmodders/documentation');

		$this->tpl_name = 'acp_documentation';
		$this->page_title = 'ACP_DOCUMENTATION_SETTINGS';

		if ($mode !== 'settings')
		{
			return;
		}

		$form_key = 'acp_documentation';
		add_form_key($form_key);

		$error = array();
		$resync_feedback = false;

		$cfg_array = array(
			'phpbbmodders_documentation_docs_path'     => (string) $config['phpbbmodders_documentation_docs_path'],
			'phpbbmodders_documentation_fallback_lang' => (string) $config['phpbbmodders_documentation_fallback_lang'],
			'phpbbmodders_documentation_enabled'       => (bool) $config['phpbbmodders_documentation_enabled'],
			'phpbbmodders_documentation_split_nav_links' => (bool) $config['phpbbmodders_documentation_split_nav_links'],
			'phpbbmodders_documentation_manual_switch' => (bool) $config['phpbbmodders_documentation_manual_switch'],
			'phpbbmodders_documentation_nav_link'      => (bool) $config['phpbbmodders_documentation_nav_link'],
			'phpbbmodders_documentation_devdocs_nav_link' => (bool) $config['phpbbmodders_documentation_devdocs_nav_link'],
			'phpbbmodders_documentation_notify_recipients' => (string) $config['phpbbmodders_documentation_notify_recipients'],
			'phpbbmodders_documentation_lang_map'      => (string) $config['phpbbmodders_documentation_lang_map'],
			'phpbbmodders_documentation_nav_label_map' => (string) $config['phpbbmodders_documentation_nav_label_map'],
			'phpbbmodders_documentation_devdocs_nav_label_map' => (string) $config['phpbbmodders_documentation_devdocs_nav_label_map'],
			'phpbbmodders_documentation_nav_icon' => $doc_helper->normalize_nav_icon(isset($config['phpbbmodders_documentation_nav_icon']) ? $config['phpbbmodders_documentation_nav_icon'] : 'fa-file-text-o'),
			'phpbbmodders_documentation_devdocs_nav_icon' => $doc_helper->normalize_nav_icon(isset($config['phpbbmodders_documentation_devdocs_nav_icon']) ? $config['phpbbmodders_documentation_devdocs_nav_icon'] : 'fa-code'),
			'phpbbmodders_documentation_nav_icon_enabled' => !empty($config['phpbbmodders_documentation_nav_icon_enabled']),
			'phpbbmodders_documentation_devdocs_nav_icon_enabled' => !empty($config['phpbbmodders_documentation_devdocs_nav_icon_enabled']),
			'phpbbmodders_documentation_search_cache_minutes' => isset($config['phpbbmodders_documentation_search_cache_minutes']) ? (string) $config['phpbbmodders_documentation_search_cache_minutes'] : '60',
		);

		if ($cfg_array['phpbbmodders_documentation_fallback_lang'] === '')
		{
			$cfg_array['phpbbmodders_documentation_fallback_lang'] = (string) $config['default_lang'];
		}

		if ($request->is_set_post('submit') || $request->is_set_post('resync'))
		{
			if (!check_form_key($form_key))
			{
				$error[] = $language->lang('FORM_INVALID');
			}
		}

		if ($request->is_set_post('submit') && empty($error))
		{
			$cfg_array['phpbbmodders_documentation_docs_path'] = $request->variable('phpbbmodders_documentation_docs_path', '');
			$cfg_array['phpbbmodders_documentation_fallback_lang'] = $request->variable('phpbbmodders_documentation_fallback_lang', '');
			$cfg_array['phpbbmodders_documentation_enabled'] = $request->variable('phpbbmodders_documentation_enabled', false);
			$cfg_array['phpbbmodders_documentation_split_nav_links'] = $request->variable('phpbbmodders_documentation_split_nav_links', false);
			$cfg_array['phpbbmodders_documentation_manual_switch'] = $request->variable('phpbbmodders_documentation_manual_switch', false);
			$cfg_array['phpbbmodders_documentation_nav_link'] = $request->variable('phpbbmodders_documentation_nav_link', false);
			$cfg_array['phpbbmodders_documentation_devdocs_nav_link'] = $request->variable('phpbbmodders_documentation_devdocs_nav_link', false);
			$notify_recipients = $request->variable('phpbbmodders_documentation_notify_recipients', 'founders');
			$cfg_array['phpbbmodders_documentation_notify_recipients'] = in_array($notify_recipients, array('founders', 'founders_admins'), true) ? $notify_recipients : 'founders';
			$cfg_array['phpbbmodders_documentation_lang_map'] = $request->variable('phpbbmodders_documentation_lang_map', '', true);
			$cfg_array['phpbbmodders_documentation_nav_label_map'] = $request->variable('phpbbmodders_documentation_nav_label_map', '', true);
			$cfg_array['phpbbmodders_documentation_devdocs_nav_label_map'] = $request->variable('phpbbmodders_documentation_devdocs_nav_label_map', '', true);
			$cfg_array['phpbbmodders_documentation_nav_icon'] = $doc_helper->normalize_nav_icon($request->variable('phpbbmodders_documentation_nav_icon', ''));
			$cfg_array['phpbbmodders_documentation_devdocs_nav_icon'] = $doc_helper->normalize_nav_icon($request->variable('phpbbmodders_documentation_devdocs_nav_icon', ''));
			$cfg_array['phpbbmodders_documentation_nav_icon_enabled'] = $request->variable('phpbbmodders_documentation_nav_icon_enabled', false);
			$cfg_array['phpbbmodders_documentation_devdocs_nav_icon_enabled'] = $request->variable('phpbbmodders_documentation_devdocs_nav_icon_enabled', false);
			$cfg_array['phpbbmodders_documentation_search_cache_minutes'] = trim($request->variable('phpbbmodders_documentation_search_cache_minutes', ''));

			// Whole minutes from 0 (no caching) to 24 hours.
			if (!preg_match('/\A\d{1,4}\z/', $cfg_array['phpbbmodders_documentation_search_cache_minutes'])
				|| (int) $cfg_array['phpbbmodders_documentation_search_cache_minutes'] > $doc_helper::SEARCH_CACHE_MAX_MINUTES)
			{
				$error[] = $language->lang('ACP_DOCUMENTATION_SEARCH_CACHE_MINUTES_INVALID', $doc_helper::SEARCH_CACHE_MAX_MINUTES);
			}

			if (empty($error))
			{
				$cfg_array['phpbbmodders_documentation_search_cache_minutes'] = (int) $cfg_array['phpbbmodders_documentation_search_cache_minutes'];
				foreach ($cfg_array as $key => $value)
				{
					$config->set($key, $value);
				}

				trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
			}
		}

		if ($request->is_set_post('resync') && empty($error))
		{
			$added = $permission_sync->sync();
			$resync_feedback = empty($added)
				? $language->lang('ACP_DOCUMENTATION_RESYNC_NONE')
				: $language->lang('ACP_DOCUMENTATION_RESYNC_ADDED', implode(', ', $added));
		}
		else
		{
			// Keep the missing-build notification in sync even when the
			// admin only saved settings (e.g. just fixed the docs path).
			$permission_sync->sync_build_notification();
		}

		$docs_root_found = $doc_helper->get_docs_root() !== false;

		$template->assign_vars(array(
			'S_ERROR'   => (bool) count($error),
			'ERROR_MSG' => implode('<br>', $error),

			'S_DOCUMENTATION_BUILD_FOUND' => $docs_root_found,
			'RESYNC_FEEDBACK'             => $resync_feedback,

			'DOCUMENTATION_DOCS_PATH'     => $cfg_array['phpbbmodders_documentation_docs_path'],
			'DOCUMENTATION_FALLBACK_LANG' => $cfg_array['phpbbmodders_documentation_fallback_lang'],
			'S_DOCUMENTATION_ENABLED'     => $cfg_array['phpbbmodders_documentation_enabled'],
			'S_DOCUMENTATION_SPLIT_NAV_LINKS' => $cfg_array['phpbbmodders_documentation_split_nav_links'],
			'S_DOCUMENTATION_MANUAL_SWITCH' => $cfg_array['phpbbmodders_documentation_manual_switch'],
			'S_DOCUMENTATION_NAV_LINK'    => $cfg_array['phpbbmodders_documentation_nav_link'],
			'S_DOCUMENTATION_DEVDOCS_NAV_LINK' => $cfg_array['phpbbmodders_documentation_devdocs_nav_link'],
			'S_DOCUMENTATION_NOTIFY_FOUNDERS_ADMINS' => $cfg_array['phpbbmodders_documentation_notify_recipients'] === 'founders_admins',
			'DOCUMENTATION_LANG_MAP'      => $cfg_array['phpbbmodders_documentation_lang_map'],
			'DOCUMENTATION_NAV_LABEL_MAP' => $cfg_array['phpbbmodders_documentation_nav_label_map'],
			'DOCUMENTATION_DEVDOCS_NAV_LABEL_MAP' => $cfg_array['phpbbmodders_documentation_devdocs_nav_label_map'],
			'DOCUMENTATION_NAV_ICON' => $cfg_array['phpbbmodders_documentation_nav_icon'],
			'DOCUMENTATION_DEVDOCS_NAV_ICON' => $cfg_array['phpbbmodders_documentation_devdocs_nav_icon'],
			'S_DOCUMENTATION_NAV_ICON_ENABLED' => $cfg_array['phpbbmodders_documentation_nav_icon_enabled'],
			'S_DOCUMENTATION_DEVDOCS_NAV_ICON_ENABLED' => $cfg_array['phpbbmodders_documentation_devdocs_nav_icon_enabled'],
			'DOCUMENTATION_SEARCH_CACHE_MINUTES' => $cfg_array['phpbbmodders_documentation_search_cache_minutes'],
			'DOCUMENTATION_SEARCH_CACHE_MAX_MINUTES' => $doc_helper::SEARCH_CACHE_MAX_MINUTES,

			'U_ACTION' => $this->u_action,
		));

		if ($docs_root_found)
		{
			foreach ($doc_helper->get_available_languages() as $lang_code)
			{
				$template->assign_block_vars('documentation_lang', array(
					'CODE'     => $lang_code,
					'SECTIONS' => implode(', ', $doc_helper->get_available_sections($lang_code)),
				));
			}

			// Only complete now that every language's sections have been read.
			$ignored = $doc_helper->get_ignored_build_names();
			$template->assign_var('DOCUMENTATION_IGNORED_NAMES', empty($ignored) ? '' : $language->lang('ACP_DOCUMENTATION_IGNORED_NAMES', implode(', ', $ignored)));
		}
	}
}
