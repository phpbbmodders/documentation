<?php
/**
 *
 * Documentation extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\documentation\tests\event;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\documentation\acp\permission_sync;
use phpbbmodders\documentation\controller\documentation_helper;
use phpbbmodders\documentation\event\listener;
use PHPUnit\Framework\TestCase;

class listener_test extends TestCase
{
	/** @var string */
	protected $docs_root;

	protected function setUp(): void
	{
		parent::setUp();

		$this->docs_root = sys_get_temp_dir() . '/documentation_listener_test_' . uniqid();
		$this->make_page('en', '');
		$this->make_page('en', 'quickstart');
		$this->make_page('en', 'development');
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		$this->remove_directory($this->docs_root);
	}

	protected function make_page($lang, $path)
	{
		$dir = $this->docs_root . '/' . $lang . ($path !== '' ? '/' . $path : '');
		mkdir($dir, 0777, true);
		file_put_contents($dir . '/index.html', 'page');
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

	protected function make_listener(array $config_overrides, array $allowed_permissions, &$assigned)
	{
		$config = new config(array_merge(array(
			'phpbbmodders_documentation_enabled' => 1,
			'phpbbmodders_documentation_docs_path' => $this->docs_root,
			'phpbbmodders_documentation_fallback_lang' => 'en',
			'phpbbmodders_documentation_lang_map' => '',
			'phpbbmodders_documentation_nav_link' => 1,
			'phpbbmodders_documentation_devdocs_nav_link' => 1,
			'phpbbmodders_documentation_split_nav_links' => 0,
			'phpbbmodders_documentation_nav_label_map' => '',
			'phpbbmodders_documentation_devdocs_nav_label_map' => '',
			'phpbbmodders_documentation_nav_icon' => 'fa-book',
			'phpbbmodders_documentation_nav_icon_enabled' => 0,
			'phpbbmodders_documentation_devdocs_nav_icon' => 'fa-code',
			'phpbbmodders_documentation_devdocs_nav_icon_enabled' => 0,
		), $config_overrides));

		$auth = $this->createMock(auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($permission) use ($allowed_permissions) {
			return in_array($permission, $allowed_permissions, true);
		});

		$controller_helper = $this->createMock(controller_helper::class);
		$controller_helper->method('route')->willReturnCallback(function ($route, array $params = array()) {
			if ($route === 'phpbbmodders_documentation_page')
			{
				return '/app.php/documentation/' . $params['lang'] . '/' . $params['path'];
			}

			return '/app.php/documentation';
		});

		$language = $this->createMock(language::class);
		$language->method('lang')->willReturnCallback(function ($key) {
			return $key === 'DOCUMENTATION_DEV' ? 'Developer Documentation' : 'Documentation';
		});

		$request = $this->createMock(request_interface::class);
		$request->method('variable')->willReturn('');
		$request->method('header')->willReturn('');

		$user = $this->createMock(user::class);
		$user->data = array('is_registered' => true, 'user_lang' => 'en');

		$doc_helper = new documentation_helper($config, $language, $request, $user, '', $controller_helper);

		$template = $this->createMock(template::class);
		$template->method('assign_vars')->willReturnCallback(function (array $vars) use (&$assigned) {
			$assigned = $vars;
		});

		return new listener(
			$auth,
			$config,
			$controller_helper,
			$doc_helper,
			$template,
			$this->createMock(permission_sync::class)
		);
	}

	public function test_hidden_navigation_keeps_external_menu_urls()
	{
		foreach (array(0, 1) as $split)
		{
			$assigned = array();
			$listener = $this->make_listener(array(
				'phpbbmodders_documentation_split_nav_links' => $split,
				'phpbbmodders_documentation_nav_link' => 0,
				'phpbbmodders_documentation_devdocs_nav_link' => 0,
			), array(
				'u_phpbbmodders_documentation_lang_en',
				'u_phpbbmodders_documentation_quickstart',
				'u_phpbbmodders_documentation_development',
			), $assigned);
			$listener->add_navigation_link();
			$this->assertFalse($assigned['S_DOCUMENTATION_NAV_VISIBLE']);
			$this->assertFalse($assigned['S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE']);
			$this->assertSame('/app.php/documentation', $assigned['U_DOCUMENTATION']);
			$this->assertSame('/app.php/documentation/en/development', $assigned['U_DOCUMENTATION_DEVDOCS']);
			$this->assertSame('Documentation', $assigned['DOCUMENTATION_NAV_TEXT']);
			$this->assertSame('Developer Documentation', $assigned['DOCUMENTATION_DEVDOCS_NAV_TEXT']);
		}
	}

	public function test_external_menu_urls_still_require_permissions()
	{
		$assigned = array();
		$listener = $this->make_listener(array(
			'phpbbmodders_documentation_nav_link' => 0,
			'phpbbmodders_documentation_devdocs_nav_link' => 0,
		), array('u_phpbbmodders_documentation_lang_en'), $assigned);
		$listener->add_navigation_link();
		$this->assertSame('', $assigned['U_DOCUMENTATION']);
		$this->assertSame('', $assigned['U_DOCUMENTATION_DEVDOCS']);
	}

	public function test_combined_nav_uses_default_icon_only_when_enabled()
	{
		$assigned = array();
		$listener = $this->make_listener(array(), array(
			'u_phpbbmodders_documentation_lang_en',
			'u_phpbbmodders_documentation_quickstart',
		), $assigned);

		$listener->add_navigation_link();

		$this->assertTrue($assigned['S_DOCUMENTATION_NAV_VISIBLE']);
		$this->assertSame('', $assigned['DOCUMENTATION_NAV_ICON']);

		$listener = $this->make_listener(array(
			'phpbbmodders_documentation_nav_icon_enabled' => 1,
		), array(
			'u_phpbbmodders_documentation_lang_en',
			'u_phpbbmodders_documentation_quickstart',
		), $assigned);

		$listener->add_navigation_link();

		$this->assertSame('fa-book', $assigned['DOCUMENTATION_NAV_ICON']);
	}

	public function test_split_nav_icons_are_independent()
	{
		$assigned = array();
		$listener = $this->make_listener(array(
			'phpbbmodders_documentation_split_nav_links' => 1,
			'phpbbmodders_documentation_nav_icon_enabled' => 0,
			'phpbbmodders_documentation_devdocs_nav_icon_enabled' => 1,
		), array(
			'u_phpbbmodders_documentation_lang_en',
			'u_phpbbmodders_documentation_quickstart',
			'u_phpbbmodders_documentation_development',
		), $assigned);

		$listener->add_navigation_link();

		$this->assertTrue($assigned['S_DOCUMENTATION_NAV_VISIBLE']);
		$this->assertTrue($assigned['S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE']);
		$this->assertSame('', $assigned['DOCUMENTATION_NAV_ICON']);
		$this->assertSame('fa-code', $assigned['DOCUMENTATION_DEVDOCS_NAV_ICON']);
	}

	public function test_hidden_link_does_not_assign_its_icon()
	{
		$assigned = array();
		$listener = $this->make_listener(array(
			'phpbbmodders_documentation_split_nav_links' => 1,
			'phpbbmodders_documentation_nav_icon_enabled' => 1,
			'phpbbmodders_documentation_devdocs_nav_icon_enabled' => 1,
		), array(
			'u_phpbbmodders_documentation_lang_en',
			'u_phpbbmodders_documentation_development',
		), $assigned);

		$listener->add_navigation_link();

		$this->assertFalse($assigned['S_DOCUMENTATION_NAV_VISIBLE']);
		$this->assertTrue($assigned['S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE']);
		$this->assertSame('', $assigned['DOCUMENTATION_NAV_ICON']);
		$this->assertSame('fa-code', $assigned['DOCUMENTATION_DEVDOCS_NAV_ICON']);
	}

	public function test_navigation_template_renders_icons_only_when_assigned()
	{
		$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../styles/all/template/event');
		$twig = new \Twig\Environment($loader, array('autoescape' => false));
		$template = $twig->load('overall_header_navigation_append.html');

		$without_icon = $template->render(array(
			'S_DOCUMENTATION_NAV_VISIBLE' => true,
			'U_DOCUMENTATION' => '/app.php/documentation',
			'DOCUMENTATION_NAV_TEXT' => 'Documentation',
			'DOCUMENTATION_NAV_ICON' => '',
			'S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE' => false,
		));

		$this->assertStringNotContainsString('<i class="icon', $without_icon);
		$this->assertStringContainsString('<span>Documentation</span>', $without_icon);

		$with_icons = $template->render(array(
			'S_DOCUMENTATION_NAV_VISIBLE' => true,
			'U_DOCUMENTATION' => '/app.php/documentation',
			'DOCUMENTATION_NAV_TEXT' => 'Documentation',
			'DOCUMENTATION_NAV_ICON' => 'fa-book',
			'S_DOCUMENTATION_DEVDOCS_NAV_VISIBLE' => true,
			'U_DOCUMENTATION_DEVDOCS' => '/app.php/documentation/en/development',
			'DOCUMENTATION_DEVDOCS_NAV_TEXT' => 'Developer Documentation',
			'DOCUMENTATION_DEVDOCS_NAV_ICON' => 'fa-code',
		));

		$this->assertStringContainsString('<i class="icon fa-book fa-fw" aria-hidden="true"></i> <span>Documentation</span>', $with_icons);
		$this->assertStringContainsString('<i class="icon fa-code fa-fw" aria-hidden="true"></i> <span>Developer Documentation</span>', $with_icons);
	}
}
