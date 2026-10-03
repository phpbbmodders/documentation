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
	protected $search_results;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/documentation_controller_' . uniqid();
		$this->assigned = array();
		$this->search_results = array();
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

	protected function get_controller(array $denied = array(), $query = '', array $overrides = array(), $scope = '')
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
			return $name === 'q' ? $query : ($name === 'scope' ? $scope : $default);
		});
		$user = $this->createMock(user::class);
		$user->data = array('is_registered' => true);
		$routes = $this->createMock(controller_helper::class);
		$routes->method('route')->willReturnCallback(function ($name, $params) {
			return '/app.php/documentation/' . $params['lang'] . '/' . (isset($params['path']) ? $params['path'] : '');
		});
		$routes->method('render')->willReturn(new Response('rendered'));
		$template = $this->createMock(template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) {
			$this->assigned = $vars;
		});
		$template->method('assign_block_vars')->willReturnCallback(function ($name, $vars) {
			if ($name === 'documentation_search_result')
			{
				$this->search_results[] = $vars;
			}
		});
		$helper = $this->getMockBuilder(documentation_helper::class)
			->setConstructorArgs(array($config, $this->createMock(language::class), $request, $user, '', $routes))
			->onlyMethods(array('set_language_cookie'))->getMock();
		return new documentation_controller($auth, $config, $routes, $template, $user, $helper, '', 'php', $request);
	}

	public function test_search_filters_private_pages_and_returns_private_response()
	{
		file_put_contents($this->root . '/en/search-index.json', json_encode(array(
			array('path' => 'quickstart/install', 'title' => 'Installation', 'text' => 'Searchable database configuration'),
			array('path' => 'englishonly', 'title' => 'Secret title', 'text' => 'Secret database configuration'),
		)));
		$response = $this->get_controller(array('u_phpbbmodders_documentation_englishonly'), 'database')->search('en');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertTrue($response->headers->hasCacheControlDirective('private'));
		$this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
		$this->assertTrue($this->assigned['S_DOCUMENTATION_SEARCH_RESULTS']);
		$this->assertCount(1, $this->search_results);
		$this->assertSame('Installation', $this->search_results[0]['TITLE']);
	}

	public function test_search_respects_separate_documentation_sides()
	{
		mkdir($this->root . '/en/development');
		file_put_contents($this->root . '/en/development/index.html', 'developer page');
		file_put_contents($this->root . '/en/search-index.json', json_encode(array(
			array('path' => 'quickstart/install', 'title' => 'Installation', 'text' => 'database'),
			array('path' => 'development', 'title' => 'Developer API', 'text' => 'database'),
		)));
		$this->get_controller(array(), 'database', array('phpbbmodders_documentation_split_nav_links' => 1), 'development')->search('en');
		$this->assertCount(1, $this->search_results);
		$this->assertSame('Developer API', $this->search_results[0]['TITLE']);
	}

	/** @dataProvider rejected_search_provider */
	public function test_search_rejects_inaccessible_language_or_disabled_system($denied, $overrides, $lang, $status)
	{
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

	public function test_search_invalid_query_does_not_require_an_index()
	{
		$this->get_controller(array(), 'a')->search('en');
		$this->assertFalse($this->assigned['S_DOCUMENTATION_SEARCH_VALID']);
		$this->assertFalse($this->assigned['S_DOCUMENTATION_SEARCH_RESULTS']);
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
}
