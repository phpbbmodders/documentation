<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\event;

use phpbb\language\language;
use phpbb\template\template;
use phpbbmodders\documentation\controller\documentation_helper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows a persistent notice on every ACP page when no docs build is found
 * at the configured path, so any admin browsing ACP sees it — not just
 * whoever happens to open this extension's own settings page.
 */
class acp_listener implements EventSubscriberInterface
{
	/** @var language */
	protected $language;

	/** @var documentation_helper */
	protected $doc_helper;

	/** @var template */
	protected $template;

	public function __construct(language $language, documentation_helper $doc_helper, template $template)
	{
		$this->language = $language;
		$this->doc_helper = $doc_helper;
		$this->template = $template;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.adm_page_header' => 'check_docs_build',
		);
	}

	/**
	 * @return void
	 */
	public function check_docs_build()
	{
		$missing = $this->doc_helper->get_docs_root() === false;

		if ($missing)
		{
			$this->language->add_lang('info_acp_documentation', 'phpbbmodders/documentation');
		}

		$this->template->assign_vars(array(
			'S_DOCUMENTATION_BUILD_MISSING'   => $missing,
			'DOCUMENTATION_BUILD_MISSING_MSG' => $missing ? $this->language->lang('ACP_DOCUMENTATION_BUILD_MISSING') : '',
		));
	}
}
