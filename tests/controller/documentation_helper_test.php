<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Modders
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\tests\controller;

use phpbb\config\config;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\user;
use phpbbmodders\documentation\controller\documentation_helper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for documentation_helper: language-priority resolution and
 * path/lang validation. Deliberately doesn't boot a full phpBB app —
 * config is a real lightweight \phpbb\config\config instance (its
 * constructor just takes an array), request/user/language are plain
 * PHPUnit mocks, and the docs "build" is a real temporary directory tree
 * so the realpath()-based containment checks are exercised for real
 * rather than assumed to work.
 */
class documentation_helper_test extends TestCase
{
	/** @var string */
	protected $docs_root;

	protected function setUp(): void
	{
		parent::setUp();

		$this->docs_root = sys_get_temp_dir() . '/documentation_helper_test_' . uniqid();

		$this->make_page('en', '');
		$this->make_page('en', 'quickstart');
		$this->make_page('en', 'quickstart/quick_installation');
		$this->make_page('da', '');
		$this->make_page('da', 'quickstart');
		// 'da' deliberately has no quickstart/quick_installation page,
		// to exercise per-page fallback.

		// A decoy page outside the docs root, positioned so that
		// path=../../escaped from inside root/en/ genuinely resolves to
		// it: root/en/../../escaped/index.html -> root/en -> root ->
		// (parent of root) -> /escaped/index.html. This proves the
		// containment check in get_content_file() rejects a real,
		// existing file, not just a nonexistent one.
		mkdir(dirname($this->docs_root) . '/escaped', 0777, true);
		file_put_contents(dirname($this->docs_root) . '/escaped/index.html', 'decoy');
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		$this->remove_directory($this->docs_root);
		$this->remove_directory(dirname($this->docs_root) . '/escaped');
	}

	protected function make_page($lang, $path)
	{
		$dir = $this->docs_root . '/' . $lang . ($path !== '' ? '/' . $path : '');
		mkdir($dir, 0777, true);
		file_put_contents($dir . '/index.html', '<html><body><article class="docs-article">' . $lang . '/' . $path . '</article></body></html>');
	}

	protected function remove_directory($dir)
	{
		if (!is_dir($dir))
		{
			return;
		}

		foreach (array_diff(scandir($dir), array('.', '..')) as $item)
		{
			$item_path = $dir . '/' . $item;
			is_dir($item_path) ? $this->remove_directory($item_path) : unlink($item_path);
		}

		rmdir($dir);
	}

	/**
	 * @param array $config_data
	 * @return config
	 */
	protected function get_config(array $config_data = array())
	{
		return new config(array_merge(array(
			'phpbbmodders_documentation_docs_path'     => $this->docs_root,
			'phpbbmodders_documentation_fallback_lang' => 'en',
			'phpbbmodders_documentation_lang_map'      => '',
		), $config_data));
	}

	/**
	 * @param string $cookie_lang
	 * @param string $accept_language
	 * @return request_interface&\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function get_request($cookie_lang = '', $accept_language = '')
	{
		$request = $this->createMock(request_interface::class);
		$request->method('variable')->willReturn($cookie_lang);
		$request->method('header')->willReturn($accept_language);

		return $request;
	}

	/**
	 * @param bool $is_registered
	 * @param string $user_lang
	 * @return user&\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function get_user($is_registered = false, $user_lang = '')
	{
		$user = $this->createMock(user::class);
		$user->data = array(
			'is_registered' => $is_registered,
			'user_lang'     => $user_lang,
		);

		return $user;
	}

	/**
	 * @param array $config_data
	 * @param string $cookie_lang
	 * @param string $accept_language
	 * @param bool $is_registered
	 * @param string $user_lang
	 * @return documentation_helper
	 */
	protected function get_helper(array $config_data = array(), $cookie_lang = '', $accept_language = '', $is_registered = false, $user_lang = '')
	{
		return new documentation_helper(
			$this->get_config($config_data),
			$this->createMock(language::class),
			$this->get_request($cookie_lang, $accept_language),
			$this->get_user($is_registered, $user_lang),
			''
		);
	}

	public function test_get_available_languages_reflects_the_build()
	{
		$helper = $this->get_helper();

		$this->assertSame(array('da', 'en'), $helper->get_available_languages());
	}

	public function test_get_docs_root_treats_an_existing_but_empty_directory_as_not_found()
	{
		// docs-build/ exists (proteus_hugo.sh has never been run yet, or
		// the extension was just installed) but has no language
		// subdirectories at all. get_docs_root() must report this the
		// same as a missing path — an existing-but-empty directory
		// resolving as "found" would make resolve_default_language() and
		// get_fallback_language() both hand back a language ('en') that
		// is_known_language() then rejects, redirecting forever.
		$empty_root = sys_get_temp_dir() . '/documentation_helper_test_empty_' . uniqid();
		mkdir($empty_root, 0777, true);

		try
		{
			$helper = new documentation_helper(
				$this->get_config(array('phpbbmodders_documentation_docs_path' => $empty_root)),
				$this->createMock(language::class),
				$this->get_request(),
				$this->get_user(),
				''
			);

			$this->assertFalse($helper->get_docs_root());
			$this->assertSame(array(), $helper->get_available_languages());
		}
		finally
		{
			$this->remove_directory($empty_root);
		}
	}

	public function test_is_known_language()
	{
		$helper = $this->get_helper();

		$this->assertTrue($helper->is_known_language('en'));
		$this->assertTrue($helper->is_known_language('da'));
		$this->assertFalse($helper->is_known_language('fr'));
		$this->assertFalse($helper->is_known_language(''));
	}

	public function test_get_section_takes_the_first_path_segment()
	{
		$helper = $this->get_helper();

		$this->assertSame('', $helper->get_section(''));
		$this->assertSame('quickstart', $helper->get_section('quickstart'));
		$this->assertSame('quickstart', $helper->get_section('quickstart/quick_installation'));
		$this->assertSame('quickstart', $helper->get_section('/quickstart/quick_installation/'));
	}

	public function test_get_content_file_resolves_a_real_page()
	{
		$helper = $this->get_helper();

		$file = $helper->get_content_file('en', 'quickstart');

		$this->assertNotFalse($file);
		$this->assertSame(
			realpath($this->docs_root . '/en/quickstart/index.html'),
			$file
		);
	}

	public function test_get_content_file_rejects_an_unknown_language()
	{
		$helper = $this->get_helper();

		$this->assertFalse($helper->get_content_file('fr', 'quickstart'));
	}

	public function test_get_content_file_rejects_a_nonexistent_page()
	{
		$helper = $this->get_helper();

		$this->assertFalse($helper->get_content_file('en', 'does-not-exist'));
	}

	/**
	 * The decoy file in setUp() genuinely exists on disk, one directory
	 * above the docs root — this proves the realpath()-based containment
	 * check itself rejects it, not merely that the file is missing.
	 */
	public function test_get_content_file_rejects_a_path_traversal_escape()
	{
		$helper = $this->get_helper();

		// Sanity check: this exact traversal really does reach a file
		// that exists (the decoy from setUp()), so a false result here
		// can only come from the containment check, not a missing file.
		$this->assertNotFalse(realpath(dirname($this->docs_root) . '/escaped/index.html'));

		$this->assertFalse($helper->get_content_file('en', '../../escaped'));
		$this->assertFalse($helper->get_content_file('en', '../../../../../../etc/passwd'));
	}

	public function test_resolve_and_load_falls_back_to_the_fallback_language_per_page()
	{
		$helper = $this->get_helper();

		// 'da' has no quickstart/quick_installation page (see setUp()),
		// so this should silently serve the 'en' version of just that page.
		$result = $helper->resolve_and_load('da', 'quickstart/quick_installation');

		$this->assertNotFalse($result);
		$this->assertSame('en', $result['lang']);
		$this->assertTrue($result['used_fallback']);
	}

	public function test_resolve_and_load_returns_false_when_neither_language_has_the_page()
	{
		$helper = $this->get_helper();

		$this->assertFalse($helper->resolve_and_load('da', 'nowhere'));
	}

	public function test_resolve_default_language_prefers_the_cookie()
	{
		$helper = $this->get_helper(array(), 'da', 'en-US,en;q=0.9', true, 'en');

		$this->assertSame('da', $helper->resolve_default_language());
	}

	public function test_resolve_default_language_falls_through_to_the_registered_users_board_language()
	{
		$helper = $this->get_helper(array(), '', 'en-US,en;q=0.9', true, 'da');

		$this->assertSame('da', $helper->resolve_default_language());
	}

	public function test_resolve_default_language_falls_through_to_accept_language_for_guests()
	{
		$helper = $this->get_helper(array(), '', 'da-DK,da;q=0.9,en;q=0.5', false, '');

		$this->assertSame('da', $helper->resolve_default_language());
	}

	public function test_resolve_default_language_falls_back_to_the_configured_fallback()
	{
		$helper = $this->get_helper(
			array('phpbbmodders_documentation_fallback_lang' => 'en'),
			'',
			'fr-FR,fr;q=0.9',
			false,
			''
		);

		$this->assertSame('en', $helper->resolve_default_language());
	}

	/**
	 * phpBB's own install fixture routinely diverges from a docs build's
	 * language codes for regional variants (pt_br vs pt-BR, etc.) — the
	 * broad-language fallback should still match without an override.
	 */
	public function test_resolve_default_language_matches_a_regional_variant_to_its_broad_language()
	{
		$helper = $this->get_helper(array(), '', '', true, 'en_us');

		$this->assertSame('en', $helper->resolve_default_language());
	}

	public function test_is_devdocs_section()
	{
		$helper = $this->get_helper();

		$this->assertTrue($helper->is_devdocs_section('development'));
		$this->assertFalse($helper->is_devdocs_section('adminguide'));
		$this->assertFalse($helper->is_devdocs_section(''));
	}

	public function test_get_nav_label_uses_an_override_for_the_viewers_own_language()
	{
		$language = $this->createMock(language::class);
		$language->expects($this->never())->method('lang');

		$helper = new documentation_helper(
			$this->get_config(array('phpbbmodders_documentation_nav_label_map' => "en=Docs\nda=Dokumentation")),
			$language,
			$this->get_request(),
			$this->get_user(true, 'da'),
			''
		);

		$this->assertSame('Dokumentation', $helper->get_nav_label('phpbbmodders_documentation_nav_label_map', 'DOCUMENTATION'));
	}

	public function test_get_nav_label_falls_back_to_the_language_string_when_no_override_matches()
	{
		$language = $this->createMock(language::class);
		$language->method('lang')->with('DOCUMENTATION_DEV')->willReturn('Developer Documentation');

		// 'fr' isn't in the override map below, so this must fall
		// through to the default language string rather than, say,
		// silently returning an empty string.
		$helper = new documentation_helper(
			$this->get_config(array('phpbbmodders_documentation_devdocs_nav_label_map' => 'en=Dev Docs')),
			$language,
			$this->get_request(),
			$this->get_user(true, 'fr'),
			''
		);

		$this->assertSame('Developer Documentation', $helper->get_nav_label('phpbbmodders_documentation_devdocs_nav_label_map', 'DOCUMENTATION_DEV'));
	}

	public function test_get_nav_label_falls_back_when_the_override_map_is_empty()
	{
		$language = $this->createMock(language::class);
		$language->method('lang')->with('DOCUMENTATION')->willReturn('Documentation');

		$helper = new documentation_helper(
			$this->get_config(),
			$language,
			$this->get_request(),
			$this->get_user(true, 'en'),
			''
		);

		$this->assertSame('Documentation', $helper->get_nav_label('phpbbmodders_documentation_nav_label_map', 'DOCUMENTATION'));
	}

	public function test_resolve_default_language_uses_a_configured_override_when_no_broad_match_exists()
	{
		// 'no' (Norwegian, phpBB's code) has no broad-language match
		// against our 'en'/'da' build at all, so without an override it
		// must fall through to the configured fallback...
		$helper_without_override = $this->get_helper(array(), '', '', true, 'no');
		$this->assertSame('en', $helper_without_override->resolve_default_language());

		// ...but with an explicit override mapping it to 'da', that wins.
		$helper_with_override = $this->get_helper(
			array('phpbbmodders_documentation_lang_map' => 'no=da'),
			'',
			'',
			true,
			'no'
		);
		$this->assertSame('da', $helper_with_override->resolve_default_language());
	}
}
