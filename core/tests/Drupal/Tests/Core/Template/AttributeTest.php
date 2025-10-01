<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeArray;
use Drupal\Core\Template\AttributeString;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;
use Drupal\Core\Theme\Style\StyleDefinitionInterface;
use Drupal\Core\Theme\Style\StylePluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Markup as TwigMarkup;

/**
 * Tests Drupal\Core\Template\Attribute.
 */
#[CoversClass(Attribute::class)]
#[Group('Template')]
class AttributeTest extends UnitTestCase {

  /**
   * Tests AttributeValueBase copy.
   */
  public function testAttributeValueBaseCopy(): void {
    $original_attributes = new Attribute([
      'checked' => TRUE,
      'class' => ['who', 'is', 'on'],
      'id' => 'first',
    ]);
    $attributes['selected'] = $original_attributes['checked'];
    $attributes['id'] = $original_attributes['id'];
    $attributes = new Attribute($attributes);
    $this->assertSame(' checked class="who is on" id="first"', (string) $original_attributes, 'Original boolean value used with original name.');
    $this->assertSame(' selected id="first"', (string) $attributes, 'Original boolean value used with new name.');
  }

  /**
   * Tests the constructor of the attribute class.
   */
  public function testConstructor(): void {
    $attribute = new Attribute(['class' => ['example-class']]);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);

    // Test adding boolean attributes through the constructor.
    $attribute = new Attribute(['selected' => TRUE, 'checked' => FALSE]);
    $this->assertTrue($attribute['selected']->value());
    $this->assertFalse($attribute['checked']->value());

    // Test that non-array values with name "class" are cast to array.
    $attribute = new Attribute(['class' => 'example-class']);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);

    // Test that safe string objects work correctly.
    $safe_string = $this->prophesize(MarkupInterface::class);
    $safe_string->__toString()->willReturn('example-class');
    $attribute = new Attribute(['class' => $safe_string->reveal()]);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);
  }

  /**
   * Tests set of values.
   */
  public function testSet(): void {
    $attribute = new Attribute();
    $attribute['class'] = ['example-class'];

    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);
  }

  /**
   * Tests adding new values to an existing part of the attribute.
   */
  public function testAdd(): void {
    $attribute = new Attribute(['class' => ['example-class']]);

    $attribute['class'][] = 'other-class';
    $this->assertEquals(new AttributeArray('class', ['example-class', 'other-class']), $attribute['class']);
  }

  /**
   * Tests removing of values.
   */
  public function testRemove(): void {
    $attribute = new Attribute(['class' => ['example-class']]);
    unset($attribute['class']);
    $this->assertFalse(isset($attribute['class']));
  }

  /**
   * Tests setting attributes.
   */
  public function testSetAttribute(): void {
    $attribute = new Attribute();

    // Test adding various attributes.
    $attributes = ['alt', 'id', 'src', 'title', 'value'];
    foreach ($attributes as $key) {
      foreach (['kitten', ''] as $value) {
        $attribute = new Attribute();
        $attribute->setAttribute($key, $value);
        $this->assertEquals($value, $attribute[$key]);
      }
    }

    // Test adding array to class.
    $attribute = new Attribute();
    $attribute->setAttribute('class', ['kitten', 'cat']);
    $this->assertEquals(['kitten', 'cat'], $attribute['class']->value());

    // Test adding boolean attributes.
    $attribute = new Attribute();
    $attribute['checked'] = TRUE;
    $this->assertTrue($attribute['checked']->value());
  }

  /**
   * Tests removing attributes.
   */
  public function testRemoveAttribute(): void {
    $attributes = [
      'alt' => 'Alternative text',
      'id' => 'bunny',
      'src' => 'zebra',
      'style' => 'color: pink;',
      'title' => 'kitten',
      'value' => 'ostrich',
      'checked' => TRUE,
    ];
    $attribute = new Attribute($attributes);

    // Single value.
    $attribute->removeAttribute('alt');
    $this->assertEmpty($attribute['alt']);

    // Multiple values.
    $attribute->removeAttribute('id', 'src');
    $this->assertEmpty($attribute['id']);
    $this->assertEmpty($attribute['src']);

    // Single value in array.
    $attribute->removeAttribute(['style']);
    $this->assertEmpty($attribute['style']);

    // Boolean value.
    $attribute->removeAttribute('checked');
    $this->assertEmpty($attribute['checked']);

    // Multiple values in array.
    $attribute->removeAttribute(['title', 'value']);
    $this->assertEmpty((string) $attribute);

  }

  /**
   * Tests adding class attributes with the AttributeArray helper method.
   */
  public function testAddClasses(): void {
    // Add a class with the array syntax without first initializing the 'class'
    // attribute.
    $attribute = new Attribute();
    $attribute['class'][] = 'test-class';
    $this->assertEquals(new AttributeArray('class', ['test-class']), $attribute['class']);

    $attribute = new Attribute();
    // Add no class on empty attribute.
    $attribute->addClass();
    $this->assertEmpty($attribute['class']->value());

    // Test various permutations of adding values to empty Attribute objects.
    foreach ([NULL, FALSE, '', []] as $value) {
      // Single value.
      $attribute->addClass($value);
      $this->assertEmpty((string) $attribute);

      // Multiple values.
      $attribute->addClass($value, $value);
      $this->assertEmpty((string) $attribute);

      // Single value in array.
      $attribute->addClass([$value]);
      $this->assertEmpty((string) $attribute);

      // Single value in arrays.
      $attribute->addClass([$value], [$value]);
      $this->assertEmpty((string) $attribute);
    }

    // Add one class on empty attribute.
    $attribute->addClass('banana');
    $this->assertEquals(['banana'], $attribute['class']->value());

    // Add one class.
    $attribute->addClass('aa');
    $this->assertEquals(['banana', 'aa'], $attribute['class']->value());

    // Add multiple classes.
    $attribute->addClass('xx', 'yy');
    $this->assertEquals(['banana', 'aa', 'xx', 'yy'], $attribute['class']->value());

    // Add an array of classes.
    $attribute->addClass(['red', 'green', 'blue']);
    $this->assertEquals(['banana', 'aa', 'xx', 'yy', 'red', 'green', 'blue'], $attribute['class']->value());

    // Add an array of duplicate classes.
    $attribute->addClass(['red', 'green', 'blue'], ['aa', 'aa', 'banana'], 'yy');
    $this->assertEquals('banana aa xx yy red green blue', (string) $attribute['class']);
  }

  /**
   * Tests adding style.
   *
   * @legacy-covers ::addStyle
   */
  public function testAddStyle(): void {
    $attribute = new Attribute();

    $definition = $this->createMock(StyleDefinitionInterface::class);
    $definition->expects($this->once())
      ->method('applyOrBubbleOnAttribute')
      ->with('option_1', $attribute);

    $stylePluginManager = $this->createMock(StylePluginManagerInterface::class);
    $stylePluginManager->expects($this->once())
      ->method('getDefinition')
      ->with('id_1')
      ->willReturn($definition);

    $container = new ContainerBuilder();
    $container->set(StylePluginManagerInterface::class, $stylePluginManager);
    \Drupal::setContainer($container);

    $attribute->addStyle('id_1', 'option_1');
  }

  /**
   * Tests adding style: exception missing plugin.
   *
   * @legacy-covers ::addStyle
   */
  public function testAddStyleMissingPlugin(): void {
    $exception = new PluginNotFoundException('id_1');

    $stylePluginManager = $this->createMock(StylePluginManagerInterface::class);
    $stylePluginManager->expects($this->once())
      ->method('getDefinition')
      ->with('id_1')
      ->willThrowException($exception);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with("Plugin ID 'id_1' was not found.");

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('twig')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set(StylePluginManagerInterface::class, $stylePluginManager);
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $attribute = new Attribute();
    $attribute->addStyle('id_1', 'option_1');
  }

  /**
   * Tests adding style: exception style definition error.
   *
   * @legacy-covers ::addStyle
   */
  public function testAddStyleStyleDefinitionError(): void {
    $exception = new StyleDefinitionErrorException('message');

    $stylePluginManager = $this->createMock(StylePluginManagerInterface::class);
    $stylePluginManager->expects($this->once())
      ->method('getDefinition')
      ->with('id_1')
      ->willThrowException($exception);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('message');

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('twig')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set(StylePluginManagerInterface::class, $stylePluginManager);
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $attribute = new Attribute();
    $attribute->addStyle('id_1', 'option_1');
  }

  /**
   * Tests removing class attributes with the AttributeArray helper method.
   */
  public function testRemoveClasses(): void {
    // Add duplicate class to ensure that both duplicates are removed.
    $classes = ['example-class', 'aa', 'xx', 'yy', 'red', 'green', 'blue', 'red'];
    $attribute = new Attribute(['class' => $classes]);

    // Remove one class.
    $attribute->removeClass('example-class');
    $this->assertNotContains('example-class', $attribute['class']->value());

    // Remove multiple classes.
    $attribute->removeClass('xx', 'yy');
    $this->assertNotContains(['xx', 'yy'], $attribute['class']->value());

    // Remove an array of classes.
    $attribute->removeClass(['red', 'green', 'blue']);
    $this->assertNotContains(['red', 'green', 'blue'], $attribute['class']->value());

    // Remove a class that does not exist.
    $attribute->removeClass('gg');
    $this->assertNotContains(['gg'], $attribute['class']->value());
    // Test that the array index remains sequential.
    $this->assertEquals(['aa'], $attribute['class']->value());

    $attribute->removeClass('aa');
    $this->assertEmpty((string) $attribute);
  }

  /**
   * Tests checking for class names with the Attribute method.
   */
  public function testHasClass(): void {
    // Test an attribute without any classes.
    $attribute = new Attribute();
    $this->assertFalse($attribute->hasClass('a-class-nowhere-to-be-found'));

    // Add a class to check for.
    $attribute->addClass('we-totally-have-this-class');
    // Check that this class exists.
    $this->assertTrue($attribute->hasClass('we-totally-have-this-class'));
  }

  /**
   * Tests removing class attributes with the Attribute helper methods.
   *
   * @legacy-covers ::removeClass
   * @legacy-covers ::addClass
   */
  public function testChainAddRemoveClasses(): void {
    $attribute = new Attribute(
      ['class' => ['example-class', 'red', 'green', 'blue']]
    );

    $attribute
      ->removeClass(['red', 'green', 'pink'])
      ->addClass(['apple', 'lime', 'grapefruit'])
      ->addClass(['banana']);
    $expected = ['example-class', 'blue', 'apple', 'lime', 'grapefruit', 'banana'];
    $this->assertEquals($expected, $attribute['class']->value(), 'Attributes chained');
  }

  /**
   * Tests the twig calls to the Attribute.
   *
   * @legacy-covers ::removeClass
   * @legacy-covers ::addClass
   */
  #[DataProvider('providerTestAttributeClassHelpers')]
  public function testTwigAddRemoveClasses(string $template, string $expected, array $seed_attributes = []): void {
    $loader = new StringLoader();
    $twig = new Environment($loader);
    $data = ['attributes' => new Attribute($seed_attributes)];
    $result = $twig->createTemplate($template)->render($data);
    $this->assertEquals($expected, $result);
  }

  /**
   * Provides tests data for testEscaping.
   *
   * @return array
   *   An array of test data each containing of a twig template string,
   *   a resulting string of classes and an optional array of attributes.
   */
  public static function providerTestAttributeClassHelpers(): array {
    // cSpell:disable
    return [
      ["{{ attributes.class }}", ''],
      ["{{ attributes.addClass('everest').class }}", 'everest'],
      ["{{ attributes.addClass(['k2', 'kangchenjunga']).class }}", 'k2 kangchenjunga'],
      ["{{ attributes.addClass('lhotse', 'makalu', 'cho-oyu').class }}", 'lhotse makalu cho-oyu'],
      [
        "{{ attributes.addClass('nanga-parbat').class }}",
        'dhaulagiri manaslu nanga-parbat',
        ['class' => ['dhaulagiri', 'manaslu']],
      ],
      [
        "{{ attributes.removeClass('annapurna').class }}",
        'gasherbrum-i',
        ['class' => ['annapurna', 'gasherbrum-i']],
      ],
      [
        "{{ attributes.removeClass(['broad peak']).class }}",
        'gasherbrum-ii',
        ['class' => ['broad peak', 'gasherbrum-ii']],
      ],
      [
        "{{ attributes.removeClass('gyachung-kang', 'shishapangma').class }}",
        '',
        ['class' => ['shishapangma', 'gyachung-kang']],
      ],
      [
        "{{ attributes.removeClass('nuptse').addClass('annapurna-ii').class }}",
        'himalchuli annapurna-ii',
        ['class' => ['himalchuli', 'nuptse']],
      ],
      // Test for the removal of an empty class name.
      ["{{ attributes.addClass('rakaposhi', '').class }}", 'rakaposhi'],
    ];
    // cSpell:enable
  }

  /**
   * Tests iterating on the values of the attribute.
   */
  public function testIterate(): void {
    $attribute = new Attribute(['class' => ['example-class'], 'id' => 'example-id']);

    $counter = 0;
    foreach ($attribute as $key => $value) {
      if ($counter == 0) {
        $this->assertEquals('class', $key);
        $this->assertEquals(new AttributeArray('class', ['example-class']), $value);
      }
      if ($counter == 1) {
        $this->assertEquals('id', $key);
        $this->assertEquals(new AttributeString('id', 'example-id'), $value);
      }
      $counter++;
    }
  }

  /**
   * Tests printing of an attribute.
   */
  public function testPrint(): void {
    $attribute = new Attribute(['class' => ['example-class'], 'id' => 'example-id', 'enabled' => TRUE]);

    $content = $this->randomMachineName();
    $html = '<div' . $attribute . '>' . $content . '</div>';
    $this->assertClass('example-class', $html);
    $this->assertNoClass('example-class2', $html);

    $this->assertID('example-id', $html);
    $this->assertNoID('example-id2', $html);

    $this->assertStringContainsString('enabled', $html);
  }

  /**
   * Tests printing of an attribute with attachments.
   */
  public function testPrintWithAttachments(): void {
    $inlineTemplate = [
      '#attached' => [
        'library' => ['core/drupal.ajax'],
      ],
    ];

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->once())
      ->method('render')
      ->with($inlineTemplate);

    $container = new ContainerBuilder();
    $container->set(RendererInterface::class, $renderer);
    \Drupal::setContainer($container);

    $attribute = new Attribute(['class' => ['example-class']]);
    $attribute->setAttachments(['library' => ['core/drupal.ajax']]);

    $content = $this->randomMachineName();
    $html = '<div' . (string) $attribute . '>' . $content . '</div>';
    $this->assertClass('example-class', $html);
    $this->assertNoClass('example-class2', $html);
  }

  /**
   * Tests attribute values.
   *
   * @legacy-covers ::createAttributeValue
   */
  #[DataProvider('providerTestAttributeValues')]
  public function testAttributeValues(array $attributes, $expected): void {
    $this->assertEquals($expected, (new Attribute($attributes))->__toString());
  }

  public static function providerTestAttributeValues(): array {
    $data = [
      'HTML-encoded attribute name' => [['&"\'<>' => 'value'], ' &amp;&quot;&#039;&lt;&gt;="value"'],
      'HTML-encoded attribute value' => [['title' => '&"\'<>'], ' title="&amp;&quot;&#039;&lt;&gt;"'],
      'multi-value attribute' => [['class' => ['first', 'last']], ' class="first last"'],
      'true boolean attribute' => [['disabled' => TRUE], ' disabled'],
      'false boolean attribute' => [['disabled' => FALSE], ''],
      'empty attribute value' => [['alt' => ''], ' alt=""'],
      'null attribute value' => [['alt' => NULL], ''],
      'multiple attributes' => [
        [
          'id' => 'id-test',
          'class' => ['first', 'last'],
          'alt' => 'Alternate',
        ],
        ' id="id-test" class="first last" alt="Alternate"',
      ],
      'empty attributes array' => [[], ''],
    ];

    $string = '"> <script>alert(123)</script>"';
    $data['safe-object-xss1'] = [['title' => Markup::create($string)], ' title="&quot;&gt; alert(123)&quot;"'];
    $data['non-safe-object-xss1'] = [['title' => $string], ' title="' . Html::escape($string) . '"'];
    $string = '&quot;><script>alert(123)</script>';
    $data['safe-object-xss2'] = [['title' => Markup::create($string)], ' title="&quot;&gt;alert(123)"'];
    $data['non-safe-object-xss2'] = [['title' => $string], ' title="' . Html::escape($string) . '"'];

    // \Twig\Markup objects are generated when using twig defined variables
    // like `{% set xxx %}Foo{% endset %}`.
    $data['twig-markup'] = [['title' => new TwigMarkup('foo', 'UTF-8')], ' title="foo"'];

    return $data;
  }

  /**
   * Checks that the given CSS class is present in the given HTML snippet.
   *
   * @param string $class
   *   The CSS class to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertClass(string $class, string $html): void {
    $xpath = "//*[@class='$class']";
    self::assertTrue((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS class is not present in the given HTML snippet.
   *
   * @param string $class
   *   The CSS class to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertNoClass(string $class, string $html): void {
    $xpath = "//*[@class='$class']";
    self::assertFalse((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS ID is present in the given HTML snippet.
   *
   * @param string $id
   *   The CSS ID to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertID(string $id, string $html): void {
    $xpath = "//*[@id='$id']";
    self::assertTrue((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS ID is not present in the given HTML snippet.
   *
   * @param string $id
   *   The CSS ID to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertNoID(string $id, string $html): void {
    $xpath = "//*[@id='$id']";
    self::assertFalse((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Counts the occurrences of the given XPath query in a given HTML snippet.
   *
   * @param string $query
   *   The XPath query to execute.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @return int
   *   The number of results that are found.
   */
  protected function getXPathResultCount($query, $html): int {
    $document = Html::load($html);
    $xpath = new \DOMXPath($document);

    return $xpath->query($query)->length;
  }

  /**
   * Tests the storage method.
   */
  public function testStorage(): void {
    $attribute = new Attribute(['class' => ['example-class']]);

    $this->assertEquals(['class' => new AttributeArray('class', ['example-class'])], $attribute->storage());
  }

  /**
   * Provides tests data for testHasAttribute.
   *
   * @return array
   *   An array of test data each containing an array of attributes, the name
   *   of the attribute to check existence of, and the expected result.
   */
  public static function providerTestHasAttribute(): array {
    return [
      [['class' => ['example-class']], 'class', TRUE],
      [[], 'class', FALSE],
      [['class' => ['example-class']], 'id', FALSE],
      [['class' => ['example-class'], 'id' => 'foo'], 'id', TRUE],
      [['id' => 'foo'], 'class', FALSE],
    ];
  }

  /**
   * Tests has attribute.
   */
  #[DataProvider('providerTestHasAttribute')]
  public function testHasAttribute(array $test_data, string $test_attribute, bool $expected): void {
    $attributes = new Attribute($test_data);
    $this->assertSame($expected, $attributes->hasAttribute($test_attribute));
  }

  /**
   * Provides tests data for testMerge.
   *
   * @return array
   *   An array of test data each containing an initial Attribute object, an
   *   Attribute object or array to be merged, and the expected result.
   */
  public static function providerTestMerge(): array {
    return [
      [
        new Attribute([]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['example-class', 'class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['id' => 'foo', 'href' => 'bar']),
        new Attribute([
          'class' => ['example-class'],
          'id' => 'foo',
          'href' => 'bar',
        ]),
      ],
      [
        new Attribute([]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['class' => ['class1']]),
        new Attribute(['class' => ['example-class', 'class1']]),
      ],
      [
        new Attribute(['class' => ['example-class']]),
        new Attribute(['id' => 'foo', 'href' => 'bar']),
        new Attribute([
          'class' => ['example-class'],
          'id' => 'foo',
          'href' => 'bar',
        ]),
      ],
      [
        new Attribute(['style' => 'color: pink;']),
        new Attribute(['style' => 'color: red;']),
        new Attribute(['style' => 'color: red;']),
      ],
      [
        new Attribute(['style' => ['color: pink;']]),
        new Attribute(['style' => 'color: red;']),
        new Attribute(['style' => ['color: pink;', 'color: red;']]),
      ],
      [
        new Attribute(['style' => 'color: pink;']),
        new Attribute(['style' => ['color: red;']]),
        new Attribute(['style' => ['color: pink;', 'color: red;']]),
      ],
      [
        new Attribute(['style' => ['color: pink;']]),
        new Attribute(['style' => ['color: red;']]),
        new Attribute(['style' => ['color: pink;', 'color: red;']]),
      ],
    ];
  }

  /**
   * Tests merge.
   */
  #[DataProvider('providerTestMerge')]
  public function testMerge(Attribute $original, Attribute $merge, Attribute $expected): void {
    $this->assertEquals($expected, $original->merge($merge));
  }

  /**
   * Tests merge argument exception.
   */
  public function testMergeArgumentException(): void {
    $attributes = new Attribute(['class' => ['example-class']]);
    $this->expectException(\TypeError::class);
    $attributes->merge('not an array');
  }

  /**
   * Tests merge with attachments.
   *
   * @legacy-covers ::merge
   */
  public function testMergeWithAttachments(): void {
    $attribute1 = new Attribute();
    $attribute1->addAttachments([
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ]);

    $attribute2 = new Attribute();
    $attribute2->addAttachments([
      'library' => [
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option_2',
        'my_style_2' => 'my_option',
      ],
    ]);

    $expected = [
      'library' => [
        'my_module/my_library',
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
        'my_style_2' => 'my_option',
      ],
    ];

    $attribute1->merge($attribute2);
    $this->assertEquals($expected, $attribute1->getAttachments());
  }

  /**
   * Tests setting attachments.
   *
   * @legacy-covers ::setAttachments
   */
  public function testSetAttachments(): void {
    $attribute = new Attribute();

    $this->assertEmpty($attribute->getAttachments());

    $attribute->setAttachments(['foo' => 'bar']);
    $this->assertEquals(['foo' => 'bar'], $attribute->getAttachments());

    $attribute->setAttachments(['foo2' => 'bar']);
    $this->assertEquals(['foo2' => 'bar'], $attribute->getAttachments());
  }

  /**
   * Tests adding attachments.
   *
   * @legacy-covers ::addAttachments
   */
  public function testAddAttachments(): void {
    $attribute = new Attribute();
    $this->assertEmpty($attribute->getAttachments());

    // First addition.
    $attribute->addAttachments([
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ]);
    $expected = [
      'library' => [
        'my_module/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
      ],
    ];
    $this->assertEquals($expected, $attribute->getAttachments());

    // Second addition, test merging.
    $attribute->addAttachments([
      'library' => [
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option_2',
        'my_style_2' => 'my_option',
      ],
    ]);
    $expected = [
      'library' => [
        'my_module/my_library',
        'my_module/my_library',
        'my_module_2/my_library',
      ],
      'styles' => [
        'my_style' => 'my_option',
        'my_style_2' => 'my_option',
      ],
    ];
    $this->assertEquals($expected, $attribute->getAttachments());
  }

}
