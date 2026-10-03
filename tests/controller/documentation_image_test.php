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

class documentation_image_test extends TestCase
{
	protected $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/documentation_image_' . uniqid();
		foreach (array('en', 'da') as $lang)
		{
			mkdir($this->root . '/' . $lang . '/quickstart/install', 0777, true);
			mkdir($this->root . '/' . $lang . '/images');
			file_put_contents($this->root . '/' . $lang . '/index.html', '<article class="docs-article">Home</article>');
			file_put_contents($this->root . '/' . $lang . '/quickstart/index.html', '<article class="docs-article">Section</article>');
			file_put_contents($this->root . '/' . $lang . '/quickstart/install/index.html', '<article class="docs-article"><img src="../../images/referenced.png?v=1#image"><img src="../../images/nonimage.png"></article>');
		}
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
		file_put_contents($this->root . '/en/images/referenced.png', $png);
		file_put_contents($this->root . '/en/images/private.png', $png);
		file_put_contents($this->root . '/en/images/nonimage.png', '<script>not an image</script>');
		file_put_contents($this->root . '/outside.png', $png);
		symlink($this->root . '/outside.png', $this->root . '/en/images/escape.png');
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

	protected function get_controller(array $params = array(), array $denied = array(), array $settings = array())
	{
		$config = new config(array_merge(array(
			'phpbbmodders_documentation_enabled' => 1,
			'phpbbmodders_documentation_docs_path' => $this->root,
			'phpbbmodders_documentation_fallback_lang' => 'en',
			'phpbbmodders_documentation_split_nav_links' => 0,
		), $settings));
		$params = array_merge(array('page' => 'quickstart/install', 'page_lang' => 'en'), $params);
		$request = $this->createMock(request_interface::class);
		$request->method('variable')->willReturnCallback(function ($name, $default) use ($params) {
			return isset($params[$name]) ? $params[$name] : $default;
		});
		$auth = $this->createMock(auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($permission) use ($denied) {
			return !in_array($permission, $denied, true);
		});
		$user = $this->createMock(user::class);
		$routes = $this->createMock(controller_helper::class);
		$helper = new documentation_helper($config, $this->createMock(language::class), $request, $user, '', $routes);
		return new documentation_controller($auth, $config, $routes, $this->createMock(template::class), $user, $helper, '', 'php', $request);
	}

	public function test_referenced_image_returns_binary_bytes_and_private_headers()
	{
		$response = $this->get_controller()->image('en', 'images/referenced.png');
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('image/png', $response->headers->get('Content-Type'));
		$this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
		$this->assertTrue($response->headers->hasCacheControlDirective('private'));
		$this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
		ob_start();
		$response->sendContent();
		$bytes = ob_get_clean();
		$this->assertSame(file_get_contents($this->root . '/en/images/referenced.png'), $bytes);
	}

	public function test_missing_translated_image_can_use_authorized_fallback_language()
	{
		$response = $this->get_controller(array('page_lang' => 'da'))->image('en', 'images/referenced.png');
		$this->assertSame(200, $response->getStatusCode());
	}

	/** @dataProvider rejected_request_provider */
	public function test_image_endpoint_rejects_unauthorized_or_invalid_requests($lang, $path, $params, $denied, $settings, $status)
	{
		try
		{
			$this->get_controller($params, $denied, $settings)->image($lang, $path);
			$this->fail('Image request should have been rejected');
		}
		catch (http_exception $exception)
		{
			$this->assertSame($status, $exception->getStatusCode());
		}
	}

	public static function rejected_request_provider()
	{
		return array(
			'asset language denied' => array('en', 'images/referenced.png', array(), array('u_phpbbmodders_documentation_lang_en'), array(), 403),
			'source language denied' => array('en', 'images/referenced.png', array('page_lang' => 'da'), array('u_phpbbmodders_documentation_lang_da'), array(), 403),
			'source section denied' => array('en', 'images/referenced.png', array(), array('u_phpbbmodders_documentation_quickstart'), array(), 403),
			'unreferenced image' => array('en', 'images/private.png', array(), array(), array(), 404),
			'missing image' => array('en', 'images/missing.png', array(), array(), array(), 404),
			'nonimage content' => array('en', 'images/nonimage.png', array(), array(), array(), 404),
			'unknown language' => array('fr', 'images/referenced.png', array(), array(), array(), 404),
			'image traversal' => array('en', 'images/../../outside.png', array(), array(), array(), 404),
			'page traversal' => array('en', 'images/referenced.png', array('page' => 'quickstart/../../da/quickstart/install'), array(), array(), 404),
			'symlink escape' => array('en', 'images/escape.png', array(), array(), array(), 404),
			'master disabled' => array('en', 'images/referenced.png', array(), array(), array('phpbbmodders_documentation_enabled' => 0), 503),
			'build missing' => array('en', 'images/referenced.png', array(), array(), array('phpbbmodders_documentation_docs_path' => '/nonexistent-documentation-build'), 503),
		);
	}
}
