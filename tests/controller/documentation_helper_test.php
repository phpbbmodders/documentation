<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\tests\controller;

use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
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
			'',
			$this->get_controller_helper()
		);
	}

	protected function make_bundle($lang, $section, array $files = array('pagefind.js', 'fragment/en_1.pf_fragment'))
	{
		foreach ($files as $file)
		{
			$path = $this->docs_root . '/' . $lang . '/' . $section . '/pagefind/' . $file;
			if (!is_dir(dirname($path)))
			{
				mkdir(dirname($path), 0777, true);
			}
			file_put_contents($path, 'bundle file');
		}
	}

	public function test_search_bundle_file_resolves_contained_bundle_files()
	{
		$this->make_bundle('en', 'quickstart');
		$helper = $this->get_helper();
		$this->assertSame(realpath($this->docs_root . '/en/quickstart/pagefind/pagefind.js'),
			$helper->get_search_bundle_file('en', 'quickstart', 'pagefind.js'));
		$this->assertSame(realpath($this->docs_root . '/en/quickstart/pagefind/fragment/en_1.pf_fragment'),
			$helper->get_search_bundle_file('en', 'quickstart', 'fragment/en_1.pf_fragment'));
		$this->assertSame('text/javascript', $helper->get_search_bundle_type('pagefind.js'));
		$this->assertSame('application/octet-stream', $helper->get_search_bundle_type('fragment/en_1.pf_fragment'));
	}

	/** @dataProvider rejected_search_bundle_provider */
	public function test_search_bundle_file_rejects_unsafe_or_unknown_files($lang, $section, $path)
	{
		$this->make_bundle('en', 'quickstart', array('pagefind.js', 'pagefind-ui.css', '.hidden.js', 'fragment/en_1.pf_fragment'));
		$this->make_bundle('en', 'notasection');
		$this->make_bundle('en', 'images');
		$this->assertFalse($this->get_helper()->get_search_bundle_file($lang, $section, $path));
	}

	public static function rejected_search_bundle_provider()
	{
		return array(
			'missing file' => array('en', 'quickstart', 'missing.js'),
			'unserved type' => array('en', 'quickstart', 'pagefind-ui.css'),
			'dot file' => array('en', 'quickstart', '.hidden.js'),
			'parent traversal' => array('en', 'quickstart', '../index.html'),
			'nested traversal' => array('en', 'quickstart', 'fragment/../pagefind.js'),
			'backslash' => array('en', 'quickstart', 'fragment\\en_1.pf_fragment'),
			'section without index.html' => array('en', 'notasection', 'pagefind.js'),
			'images directory' => array('en', 'images', 'pagefind.js'),
			'unknown language' => array('xx', 'quickstart', 'pagefind.js'),
			'section in other language' => array('da', 'quickstart', 'pagefind.js'),
		);
	}

	public function test_search_bundle_file_rejects_symlinks_out_of_the_bundle()
	{
		$this->make_bundle('en', 'quickstart');
		file_put_contents($this->docs_root . '/outside.js', 'outside');
		symlink($this->docs_root . '/outside.js', $this->docs_root . '/en/quickstart/pagefind/linked.js');
		$this->make_page('da', 'linked');
		symlink($this->docs_root . '/en/quickstart/pagefind', $this->docs_root . '/da/linked/pagefind');
		$helper = $this->get_helper();
		$this->assertFalse($helper->get_search_bundle_file('en', 'quickstart', 'linked.js'));
		$this->assertFalse($helper->get_search_bundle_file('da', 'linked', 'pagefind.js'));
		// remove_directory() does not remove symlinked directories.
		unlink($this->docs_root . '/da/linked/pagefind');
	}

	/** @dataProvider search_cache_control_provider */
	public function test_search_bundle_cache_control_follows_the_configured_minutes($config_data, $path, $expected)
	{
		$this->assertSame($expected, $this->get_helper($config_data)->get_search_bundle_cache_control($path));
	}

	public static function search_cache_control_provider()
	{
		return array(
			'default when unset' => array(array(), 'fragment/en_1.pf_fragment', 'private, max-age=3600'),
			'configured minutes' => array(array('phpbbmodders_documentation_search_cache_minutes' => 30), 'index/en_1.pf_index', 'private, max-age=1800'),
			'zero turns caching off' => array(array('phpbbmodders_documentation_search_cache_minutes' => 0), 'pagefind.en_1.pf_meta', 'private, no-store'),
			'stored value over the limit is capped' => array(array('phpbbmodders_documentation_search_cache_minutes' => 99999), 'fragment/en_1.pf_fragment', 'private, max-age=86400'),
			'entry file is never cached' => array(array('phpbbmodders_documentation_search_cache_minutes' => 30), 'pagefind-entry.json', 'private, no-store'),
			'scripts are never cached' => array(array('phpbbmodders_documentation_search_cache_minutes' => 30), 'pagefind.js', 'private, no-store'),
		);
	}

	public function test_search_bundle_sections_keeps_only_allowed_sections_with_bundles()
	{
		$this->make_page('en', 'userguide');
		$this->make_page('en', 'private');
		$this->make_bundle('en', 'quickstart');
		$this->make_bundle('en', 'private');
		$helper = $this->get_helper();
		$this->assertSame(array('quickstart'), $helper->get_search_bundle_sections('en', array('quickstart', 'userguide')));
		$this->assertSame(array(), $helper->get_search_bundle_sections('en', array()));
	}

	protected function get_controller_helper($base = '/forum/app.php')
	{
		$helper = $this->createMock(controller_helper::class);
		$helper->method('route')->willReturnCallback(function ($route, array $params) use ($base) {
			$url = $base . ($route === 'phpbbmodders_documentation_image' ? '/documentation-image/' : '/documentation/') . $params['lang'];
			if ($route === 'phpbbmodders_documentation_page' || $route === 'phpbbmodders_documentation_image')
			{
				$url .= '/' . $params['path'];
			}
			unset($params['lang'], $params['path']);
			return $url . '?' . http_build_query(array_merge(array('sid' => 'test-session'), $params));
		});
		return $helper;
	}

	/** @dataProvider route_base_provider */
	public function test_imported_links_use_routes_and_keep_sidebar_permissions($base)
	{
		file_put_contents($this->docs_root . '/en/quickstart/index.html', '<html><body>
			<div class="docs-nav-panel">
				<section class="docs-tree-section"><a href="/en/quickstart/">Allowed</a></section>
				<section class="docs-tree-section"><a href="/en/development/">Denied</a></section>
			</div>
			<div class="utility-bar"><a href="/da/quickstart/">Danish</a><a href="/en">Home</a></div>
			<article class="docs-article">
				<a href="/en/quickstart/?q=one&amp;page=2#install">Article</a>
				<a href="https://example.org/en/quickstart/">External</a>
				<a href="../other/">Relative</a>
			</article></body></html>');
		$helper = new documentation_helper($this->get_config(), $this->createMock(language::class),
			$this->get_request(), $this->get_user(), '', $this->get_controller_helper($base));
		$result = $helper->resolve_and_load('en', 'quickstart', array('quickstart'));
		$this->assertStringContainsString($base . '/documentation/en/quickstart/?sid=test-session', $result['sidebar_html']);
		$this->assertStringNotContainsString('Denied', $result['sidebar_html']);
		$this->assertStringContainsString($base . '/documentation/da/quickstart/?sid=test-session', $result['breadcrumb_html']);
		$this->assertStringContainsString($base . '/documentation/en?sid=test-session', $result['breadcrumb_html']);
		$this->assertStringContainsString($base . '/documentation/en/quickstart/?sid=test-session&amp;q=one&amp;page=2#install', $result['article_html']);
		$this->assertStringContainsString('href="https://example.org/en/quickstart/"', $result['article_html']);
		$this->assertStringContainsString('href="../other/"', $result['article_html']);
	}

	public static function route_base_provider()
	{
		return array(array('/app.php'), array('/forum/app.php'), array('/forum'), array(''));
	}

	public function test_absolute_image_fallback_is_resolved_before_route_rewriting()
	{
		mkdir($this->docs_root . '/en/images');
		file_put_contents($this->docs_root . '/en/images/fallback.png', 'image fixture');
		file_put_contents($this->docs_root . '/da/quickstart/index.html', '<html><body>
			<article class="docs-article"><img src="/da/images/fallback.png"></article>
			</body></html>');
		$result = $this->get_helper()->resolve_and_load('da', 'quickstart');
		$this->assertStringContainsString('src="/forum/app.php/documentation-image/en/images/fallback.png?sid=test-session&amp;page_lang=da&amp;page=quickstart"', $result['article_html']);
		$this->assertStringNotContainsString('documentation-image-missing', $result['article_html']);
	}

	public function test_missing_image_preserves_its_alt_description()
	{
		file_put_contents($this->docs_root . '/en/quickstart/index.html', '<html><body>
			<article class="docs-article"><img src="/en/images/missing.png" alt="A &quot;quoted&quot; &lt;diagram&gt;">
			<img src="/en/images/decorative.png" alt=""></article></body></html>');
		$language = $this->createMock(language::class);
		$language->method('lang')->willReturnArgument(0);
		$helper = new documentation_helper($this->get_config(), $language, $this->get_request(),
			$this->get_user(), '', $this->get_controller_helper());
		$result = $helper->resolve_and_load('en', 'quickstart');
		$dom = new \DOMDocument();
		@$dom->loadHTML($result['article_html']);
		$notices = (new \DOMXPath($dom))->query('//span[@class="documentation-image-missing"]');
		$this->assertCount(2, $notices);
		$this->assertSame('DOCUMENTATION_IMAGE_MISSING', $notices->item(0)->textContent);
		$this->assertSame('A "quoted" <diagram>', $notices->item(0)->getAttribute('data-doc-tooltip'));
		$this->assertSame('0', $notices->item(0)->getAttribute('tabindex'));
		$this->assertFalse($notices->item(1)->hasAttribute('data-doc-tooltip'));
		$this->assertFalse($notices->item(1)->hasAttribute('tabindex'));
	}

	public function test_get_available_languages_reflects_the_build()
	{
		$helper = $this->get_helper();

		$this->assertSame(array('da', 'en'), $helper->get_available_languages());
	}

	public function test_imported_language_switcher_is_removed_but_labels_remain_available()
	{
		file_put_contents($this->docs_root . '/da/index.html', '<html><body>
			<div class="utility-bar"><a href="/da/">Documentation home</a>
			<div class="language-switcher"><a href="/da/" lang="da">Dansk</a><a href="/en/" lang="en">English</a></div></div>
			<article class="docs-article">Article</article></body></html>');
		$helper = $this->get_helper();
		$result = $helper->resolve_and_load('da', '');
		$this->assertStringContainsString('Documentation home', $result['breadcrumb_html']);
		$this->assertStringNotContainsString('language-switcher', $result['breadcrumb_html']);
		$this->assertStringNotContainsString('English', $result['breadcrumb_html']);
		$this->assertSame(array('da' => 'Dansk', 'en' => 'English'), $helper->get_language_labels());
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
				'',
				$this->get_controller_helper()
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

	public function test_loader_can_disable_implicit_fallback_after_authorization()
	{
		$this->assertFalse($this->get_helper()->resolve_and_load('da', 'quickstart/quick_installation', array('quickstart'), false));
	}

	public function test_page_containment_rejects_cross_language_paths_and_symlinks()
	{
		$helper = $this->get_helper();
		$this->assertFalse($helper->get_content_file('da', '../en/quickstart'));
		symlink($this->docs_root . '/en/quickstart/quick_installation', $this->docs_root . '/da/quickstart/linked');
		$this->assertFalse($helper->get_content_file('da', 'quickstart/linked'));
		unlink($this->docs_root . '/da/quickstart/linked');
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
			'',
			$this->get_controller_helper()
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
			'',
			$this->get_controller_helper()
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
			'',
			$this->get_controller_helper()
		);

		$this->assertSame('Documentation', $helper->get_nav_label('phpbbmodders_documentation_nav_label_map', 'DOCUMENTATION'));
	}

	/**
	 * @dataProvider nav_icon_provider
	 */
	public function test_normalize_nav_icon_returns_one_safe_font_awesome_class($raw_icon, $expected)
	{
		$this->assertSame($expected, $this->get_helper()->normalize_nav_icon($raw_icon));
	}

	public static function nav_icon_provider()
	{
		return array(
			array('fa-book', 'fa-book'),
			array(' fa-code ', 'fa-code'),
			array('fa-file-text-o', 'fa-file-text-o'),
			array('', ''),
			array('book', ''),
			array('fa-book fa-fw', ''),
			array('fa-" onclick="alert(1)', ''),
			array("fa-book\nfa-code", ''),
			array('fa-' . str_repeat('a', 62), ''),
		);
	}

	public function test_get_nav_icon_is_off_by_default_even_when_default_icon_is_configured()
	{
		$helper = $this->get_helper(array(
			'phpbbmodders_documentation_nav_icon' => 'fa-book',
			'phpbbmodders_documentation_nav_icon_enabled' => 0,
		));

		$this->assertSame('', $helper->get_nav_icon('phpbbmodders_documentation_nav_icon'));
	}

	public function test_get_nav_icon_returns_configured_icon_only_when_enabled()
	{
		$helper = $this->get_helper(array(
			'phpbbmodders_documentation_nav_icon' => 'fa-book',
			'phpbbmodders_documentation_nav_icon_enabled' => 1,
			'phpbbmodders_documentation_devdocs_nav_icon' => 'fa-code',
			'phpbbmodders_documentation_devdocs_nav_icon_enabled' => 1,
		));

		$this->assertSame('fa-book', $helper->get_nav_icon('phpbbmodders_documentation_nav_icon'));
		$this->assertSame('fa-code', $helper->get_nav_icon('phpbbmodders_documentation_devdocs_nav_icon'));
	}

	public function test_get_nav_icon_hides_invalid_enabled_config_values()
	{
		$helper = $this->get_helper(array(
			'phpbbmodders_documentation_nav_icon' => 'fa-book fa-fw',
			'phpbbmodders_documentation_nav_icon_enabled' => 1,
		));

		$this->assertSame('', $helper->get_nav_icon('phpbbmodders_documentation_nav_icon'));
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
