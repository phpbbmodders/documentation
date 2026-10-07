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

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper as controller_helper;
use phpbb\exception\http_exception;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\documentation\controller\documentation_controller;
use phpbbmodders\documentation\controller\documentation_helper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class documentation_controller_test extends TestCase
{
	protected $root;
	protected $assigned;
	protected $blocks;
	protected $request_params;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/documentation_controller_' . uniqid();
		$this->assigned = array();
		$this->blocks = array();
		$this->request_params = array();
		foreach (array('en' => array('', 'quickstart', 'quickstart/install', 'englishonly'), 'da' => array('', 'quickstart', 'danishonly')) as $lang => $pages)
		{
			foreach ($pages as $page)
			{
				$dir = $this->root . '/' . $lang . ($page !== '' ? '/' . $page : '');
				mkdir($dir, 0777, true);
				file_put_contents($dir . '/index.html', '<title>' . $lang . '</title><nav class="docs-nav-panel">'
					. '<section class="docs-tree-section"><a href="/' . $lang . '/quickstart/">Quick Start</a></section>'
					. '<section class="docs-tree-section"><a href="/' . $lang . '/englishonly/">English only</a></section>'
					. '<section class="docs-tree-section"><a href="/' . $lang . '/danishonly/">Danish only</a></section>'
					. '</nav><article class="docs-article">' . $lang . ' article</article>');
			}
		}
	}

	protected function tearDown(): void
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file)
		{
			$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->root);
	}

	protected function get_controller(array $denied = array(), $query = '', array $overrides = array(), $scope = '', $is_registered = true)
	{
		$config = new config(array_merge(array(
			'phpbbmodders_documentation_enabled' => 1,
			'phpbbmodders_documentation_docs_path' => $this->root,
			'phpbbmodders_documentation_fallback_lang' => 'en',
			'phpbbmodders_documentation_split_nav_links' => 0,
			'phpbbmodders_documentation_manual_switch' => 1,
		), $overrides));
		$auth = $this->createMock(auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($permission) use ($denied) {
			return !in_array($permission, $denied, true);
		});
		$request = $this->createMock(request_interface::class);
		$request->method('variable')->willReturnCallback(function ($name, $default) use ($query, $scope) {
			if (array_key_exists($name, $this->request_params))
			{
				return $this->request_params[$name];
			}
			return $name === 'q' ? $query : ($name === 'scope' ? $scope : $default);
		});
		$user = $this->createMock(user::class);
		$user->data = array('is_registered' => $is_registered);
		$routes = $this->createMock(controller_helper::class);
		$routes->method('route')->willReturnCallback(function ($name, $params) {
			if ($name === 'phpbbmodders_documentation_search_bundle')
			{
				return '/app.php/documentation-search-bundle/' . $params['lang'] . '/' . $params['section'] . '/' . $params['path'] . '?sid=abc';
			}
			return '/app.php/documentation/' . $params['lang'] . '/' . (isset($params['path']) ? $params['path'] : '');
		});
		$routes->method('render')->willReturn(new Response('rendered'));
		$template = $this->createMock(template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) {
			$this->assigned = $vars;
		});
		$template->method('assign_block_vars')->willReturnCallback(function ($block, $vars) {
			$this->blocks[$block][] = $vars;
		});
		$helper = $this->getMockBuilder(documentation_helper::class)
			->setConstructorArgs(array($config, $this->createMock(language::class), $request, $user, '', $routes))
			->onlyMethods(array('set_language_cookie'))->getMock();
		$language = $this->createMock(language::class);
		$language->method('lang')->willReturnArgument(0);
		if (!$is_registered)
		{
			// login_box() renders the login page and ends the request.
			return new class($auth, $config, $routes, $template, $user, $helper, $request, $language) extends documentation_controller {
				protected function show_login_box()
				{
					throw new \RuntimeException('login_box');
				}
			};
		}
		return new documentation_controller($auth, $config, $routes, $template, $user, $helper, $request, $language);
	}

	protected function make_bundle($lang, $section)
	{
		mkdir($this->root . '/' . $lang . '/' . $section . '/pagefind/fragment', 0777, true);
		file_put_contents($this->root . '/' . $lang . '/' . $section . '/pagefind/pagefind.js', 'export {};');
		file_put_contents($this->root . '/' . $lang . '/' . $section . '/pagefind/fragment/en_1.pf_fragment', 'fragment');
	}

	public function test_search_passes_only_allowed_section_bundles_and_returns_private_response()
	{
		$this->make_bundle('en', 'quickstart');
		$this->make_bundle('en', 'englishonly');
		$response = $this->get_controller(array('u_phpbbmodders_documentation_englishonly'), 'database')->search('en');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertTrue($response->headers->hasCacheControlDirective('private'));
		$this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
		$this->assertSame(array('/app.php/documentation-search-bundle/en/quickstart/'),
			json_decode($this->assigned['DOCUMENTATION_SEARCH_BUNDLES'], true));
		$this->assertSame(array(
			'prefix' => '/en/',
			'template' => '/app.php/documentation/en/' . documentation_controller::SEARCH_PATH_PLACEHOLDER,
			'placeholder' => documentation_controller::SEARCH_PATH_PLACEHOLDER,
		), json_decode($this->assigned['DOCUMENTATION_SEARCH_LINKS'], true));
		$this->assertSame('database', $this->assigned['DOCUMENTATION_SEARCH_QUERY']);
	}

	public function test_search_respects_separate_documentation_sides()
	{
		mkdir($this->root . '/en/development');
		file_put_contents($this->root . '/en/development/index.html', 'developer page');
		$this->make_bundle('en', 'quickstart');
		$this->make_bundle('en', 'development');
		$this->get_controller(array(), 'database', array('phpbbmodders_documentation_split_nav_links' => 1), 'development')->search('en');
		$this->assertSame(array('/app.php/documentation-search-bundle/en/development/'),
			json_decode($this->assigned['DOCUMENTATION_SEARCH_BUNDLES'], true));
	}

	/** @dataProvider rejected_search_provider */
	public function test_search_rejects_inaccessible_language_or_disabled_system($denied, $overrides, $lang, $status)
	{
		$this->make_bundle('en', 'quickstart');
		try
		{
			$this->get_controller($denied, 'database', $overrides)->search($lang);
			$this->fail('Search must be rejected');
		}
		catch (http_exception $exception)
		{
			$this->assertSame($status, $exception->getStatusCode());
			$this->assertSame(array(), $this->assigned);
		}
	}

	public static function rejected_search_provider()
	{
		return array(
			array(array('u_phpbbmodders_documentation_lang_en'), array(), 'en', 403),
			array(array(), array('phpbbmodders_documentation_enabled' => 0), 'en', 503),
			array(array(), array(), '../en', 404),
		);
	}

	public function test_search_without_allowed_bundles_is_unavailable()
	{
		$this->make_bundle('en', 'englishonly');
		try
		{
			$this->get_controller(array('u_phpbbmodders_documentation_englishonly'), 'database')->search('en');
			$this->fail('Search without readable bundles must be unavailable');
		}
		catch (http_exception $exception)
		{
			$this->assertSame(503, $exception->getStatusCode());
			$this->assertSame('DOCUMENTATION_SEARCH_UNAVAILABLE', $exception->getMessage());
		}
	}

	public function test_search_bundle_serves_allowed_files_privately()
	{
		$this->make_bundle('en', 'quickstart');
		$response = $this->get_controller()->search_bundle('en', 'quickstart', 'fragment/en_1.pf_fragment');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
		$this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
		$this->assertTrue($response->headers->hasCacheControlDirective('private'));
		$this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
		$this->assertFalse($response->headers->hasCacheControlDirective('no-store'));
		$this->assertSame(realpath($this->root . '/en/quickstart/pagefind/fragment/en_1.pf_fragment'), $response->getFile()->getRealPath());
		$script = $this->get_controller()->search_bundle('en', 'quickstart', 'pagefind.js');
		$this->assertSame('text/javascript', $script->headers->get('Content-Type'));
		$this->assertTrue($script->headers->hasCacheControlDirective('private'));
		$this->assertTrue($script->headers->hasCacheControlDirective('no-store'));
	}

	/** @dataProvider rejected_search_bundle_provider */
	public function test_search_bundle_rejects_unreadable_or_unsafe_files($denied, $overrides, $lang, $section, $path, $status)
	{
		$this->make_bundle('en', 'quickstart');
		$this->make_bundle('en', 'englishonly');
		try
		{
			$this->get_controller($denied, '', $overrides)->search_bundle($lang, $section, $path);
			$this->fail('Bundle file must be rejected');
		}
		catch (http_exception $exception)
		{
			$this->assertSame($status, $exception->getStatusCode());
		}
	}

	public static function rejected_search_bundle_provider()
	{
		return array(
			'section denied' => array(array('u_phpbbmodders_documentation_englishonly'), array(), 'en', 'englishonly', 'pagefind.js', 403),
			'language denied' => array(array('u_phpbbmodders_documentation_lang_en'), array(), 'en', 'quickstart', 'pagefind.js', 403),
			'unknown section' => array(array(), array(), 'en', 'missing', 'pagefind.js', 403),
			'unknown language' => array(array(), array(), 'xx', 'quickstart', 'pagefind.js', 404),
			'traversal' => array(array(), array(), 'en', 'quickstart', '../index.html', 404),
			'missing file' => array(array(), array(), 'en', 'quickstart', 'missing.js', 404),
			'disabled' => array(array(), array('phpbbmodders_documentation_enabled' => 0), 'en', 'quickstart', 'pagefind.js', 503),
		);
	}

	public function test_denied_fallback_language_does_not_render_article()
	{
		try
		{
			$this->get_controller(array('u_phpbbmodders_documentation_lang_en'))->handle('da', 'quickstart/install');
			$this->fail('Denied fallback must not be served');
		}
		catch (http_exception $exception)
		{
			$this->assertSame(403, $exception->getStatusCode());
			$this->assertSame(array(), $this->assigned);
		}
	}

	public function test_authorized_fallback_filters_sidebar_against_actual_language()
	{
		$response = $this->get_controller()->handle('da', 'quickstart/install');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('en article', $this->assigned['DOCUMENTATION_ARTICLE']);
		$this->assertTrue($this->assigned['S_DOCUMENTATION_USED_FALLBACK']);
		$this->assertStringContainsString('English only', $this->assigned['DOCUMENTATION_SIDEBAR']);
		$this->assertStringNotContainsString('Danish only', $this->assigned['DOCUMENTATION_SIDEBAR']);
	}

	public function test_existing_translation_does_not_require_fallback_permission()
	{
		$response = $this->get_controller(array('u_phpbbmodders_documentation_lang_en'))->handle('da', 'quickstart');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('da article', $this->assigned['DOCUMENTATION_ARTICLE']);
		$this->assertFalse($this->assigned['S_DOCUMENTATION_USED_FALLBACK']);
	}

	public function test_fallback_requires_section_to_exist_in_actual_language()
	{
		unlink($this->root . '/en/quickstart/index.html');
		try
		{
			$this->get_controller()->handle('da', 'quickstart/install');
			$this->fail('Fallback section is not available in actual language');
		}
		catch (http_exception $exception)
		{
			$this->assertSame(403, $exception->getStatusCode());
			$this->assertSame(array(), $this->assigned);
		}
	}

	/** @dataProvider rejected_page_provider */
	public function test_rejected_pages_do_not_render($path, $denied, $status)
	{
		try
		{
			$this->get_controller($denied)->handle('da', $path);
			$this->fail('Page should have been rejected');
		}
		catch (http_exception $exception)
		{
			$this->assertSame($status, $exception->getStatusCode());
			$this->assertSame(array(), $this->assigned);
		}
	}

	public static function rejected_page_provider()
	{
		return array(
			'requested language denied' => array('quickstart/install', array('u_phpbbmodders_documentation_lang_da'), 403),
			'section denied' => array('quickstart/install', array('u_phpbbmodders_documentation_quickstart'), 403),
			'missing in both languages' => array('quickstart/missing', array(), 404),
			'cross-language traversal' => array('quickstart/../../en/quickstart/install', array('u_phpbbmodders_documentation_lang_en'), 404),
		);
	}

	public function test_guest_without_access_gets_the_login_box()
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('login_box');
		$this->get_controller(array('u_phpbbmodders_documentation_lang_en'), '', array(), '', false)->handle('en', 'quickstart');
	}

	public function test_registered_user_without_access_gets_403()
	{
		try
		{
			$this->get_controller(array('u_phpbbmodders_documentation_lang_en'))->handle('en', 'quickstart');
			$this->fail('A registered user without access must get a 403');
		}
		catch (http_exception $exception)
		{
			$this->assertSame(403, $exception->getStatusCode());
		}
	}

	public function test_search_messages_come_from_the_language_service()
	{
		$this->make_bundle('en', 'quickstart');
		$this->get_controller(array(), 'database')->search('en');
		$this->assertSame(array(
			'length' => 'DOCUMENTATION_SEARCH_LENGTH',
			'noResults' => 'DOCUMENTATION_SEARCH_NO_RESULTS',
			'unavailable' => 'DOCUMENTATION_SEARCH_UNAVAILABLE',
			'loading' => 'DOCUMENTATION_SEARCH_LOADING',
		), json_decode($this->assigned['DOCUMENTATION_SEARCH_MESSAGES'], true));
	}

	protected function make_search_index($lang, $section, array $titles)
	{
		$pages = array();
		foreach ($titles as $path => $title)
		{
			$pages[] = array('url' => '/' . $lang . '/' . $section . '/' . ($path !== '' ? $path . '/' : ''), 'title' => $title, 'text' => 'database setup');
		}
		file_put_contents($this->root . '/' . $lang . '/' . $section . '/search-index.json', json_encode($pages));
	}

	public function test_search_without_javascript_uses_allowed_server_indexes()
	{
		$this->make_bundle('en', 'quickstart');
		$this->make_search_index('en', 'quickstart', array('' => 'Quick Start', 'install' => 'Install'));
		$this->make_search_index('en', 'englishonly', array('' => 'Restricted'));

		$this->get_controller(array('u_phpbbmodders_documentation_englishonly'), 'database')->search('en');

		$this->assertTrue($this->assigned['S_DOCUMENTATION_SEARCH_SERVER']);
		$this->assertTrue($this->assigned['S_DOCUMENTATION_SEARCH_VALID']);
		$this->assertSame(array('Install', 'Quick Start'), array_column($this->blocks['documentation_search_result'], 'TITLE'));
		$this->assertSame('/app.php/documentation/en/quickstart/install', $this->blocks['documentation_search_result'][0]['U_PAGE']);
	}

	public function test_search_with_javascript_leaves_results_to_pagefind()
	{
		$this->make_bundle('en', 'quickstart');
		$this->make_search_index('en', 'quickstart', array('' => 'Quick Start'));
		$this->request_params = array('js' => 1);

		$this->get_controller(array(), 'database')->search('en');

		$this->assertFalse($this->assigned['S_DOCUMENTATION_SEARCH_SERVER']);
		$this->assertArrayNotHasKey('documentation_search_result', $this->blocks);
	}

	public function test_search_with_javascript_but_no_bundles_still_uses_the_server()
	{
		$this->make_search_index('en', 'quickstart', array('' => 'Quick Start'));
		$this->request_params = array('js' => 1);

		$this->get_controller(array(), 'database')->search('en');

		$this->assertTrue($this->assigned['S_DOCUMENTATION_SEARCH_SERVER']);
		$this->assertSame(array(), json_decode($this->assigned['DOCUMENTATION_SEARCH_BUNDLES'], true));
		$this->assertSame(array('Quick Start'), array_column($this->blocks['documentation_search_result'], 'TITLE'));
	}

	public function test_search_server_results_follow_the_configured_limit()
	{
		$titles = array();
		for ($i = 0; $i < 15; $i++)
		{
			mkdir($this->root . '/en/quickstart/page' . $i);
			file_put_contents($this->root . '/en/quickstart/page' . $i . '/index.html', 'page');
			$titles['page' . $i] = 'Page ' . $i;
		}
		$this->make_search_index('en', 'quickstart', $titles);

		$this->get_controller(array(), 'database', array('phpbbmodders_documentation_search_max_results' => 10))->search('en');

		$this->assertSame(10, $this->assigned['DOCUMENTATION_SEARCH_MAX_RESULTS']);
		$this->assertCount(10, $this->blocks['documentation_search_result']);
	}

	public function test_search_short_query_runs_no_server_search()
	{
		$this->make_search_index('en', 'quickstart', array('' => 'Quick Start'));

		$this->get_controller(array(), 'd')->search('en');

		$this->assertTrue($this->assigned['S_DOCUMENTATION_SEARCH_SERVER']);
		$this->assertFalse($this->assigned['S_DOCUMENTATION_SEARCH_VALID']);
		$this->assertArrayNotHasKey('documentation_search_result', $this->blocks);
	}
}
