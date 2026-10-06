<?php

namespace phpbbmodders\documentation\tests\acp;

class template_test extends \PHPUnit\Framework\TestCase
{
	public function test_tooltips_render_with_escaped_descriptions_and_matching_assets()
	{
		$source = file_get_contents(__DIR__ . '/../../adm/style/acp_documentation.html');
		$twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(array(
			'acp_documentation.html' => $source,
			'overall_header.html' => '',
			'overall_footer.html' => '',
		)), array('autoescape' => false));
		$twig->addTokenParser(new \phpbb\template\twig\tokenparser\includeparser());
		$twig->addTokenParser(new \phpbb\template\twig\tokenparser\includecss());
		$twig->addTokenParser(new \phpbb\template\twig\tokenparser\includejs());
		// Asset registration needs a running board; test the actual form independently.
		$twig->addNodeVisitor(new class implements \Twig\NodeVisitor\NodeVisitorInterface {
			public function enterNode(\Twig\Node\Node $node, \Twig\Environment $env): \Twig\Node\Node
			{
				return $node;
			}
			public function leaveNode(\Twig\Node\Node $node, \Twig\Environment $env): ?\Twig\Node\Node
			{
				return $node instanceof \phpbb\template\twig\node\includeasset ? new \Twig\Node\Node() : $node;
			}
			public function getPriority(): int
			{
				return 0;
			}
		});
		$description = 'Example "quoted" <text> & explanation';
		$twig->addFunction(new \Twig\TwigFunction('lang', function ($key) use ($description) {
			return substr($key, -8) === '_EXPLAIN' ? $description : $key;
		}));
		$html = $twig->render('acp_documentation.html', array(
			'S_DOCUMENTATION_BUILD_FOUND' => true,
			'documentation_lang' => array(),
		));
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new \DOMXPath($dom);
		$controls = $xpath->query('//*[@data-doc-tooltip]');
		$this->assertCount(8, $controls);
		$cache_minutes = $xpath->query('//input[@id="phpbbmodders_documentation_search_cache_minutes"]')->item(0);
		$this->assertNotNull($cache_minutes);
		$this->assertSame('number', $cache_minutes->getAttribute('type'));
		$this->assertSame('0', $cache_minutes->getAttribute('min'));
		foreach ($controls as $control)
		{
			$this->assertSame($description, $control->getAttribute('data-doc-tooltip'));
		}
		$this->assertSame(0, $xpath->query('//*[@data-doc-tooltip]//text')->length);
		foreach (array('js' => 'template/js', 'css' => 'theme') as $extension => $directory)
		{
			$this->assertSame(
				file_get_contents(__DIR__ . '/../../styles/all/' . $directory . '/documentation-tooltips.' . $extension),
				file_get_contents(__DIR__ . '/../../adm/style/documentation-tooltips.' . $extension)
			);
		}
	}
}
