<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\ViewsPluginManager;
use Drupal\views\ViewExecutableFactory;
use Drupal\views\Views;
use Drupal\views\ViewsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\views\Views.
 */
#[CoversClass(Views::class)]
#[Group('views')]
class ViewsTest extends UnitTestCase {

  /**
   * The test container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $user = $this->createStub(AccountInterface::class);
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $views_data = $this->createStub(ViewsData::class);
    $route_provider = $this->createStub(RouteProviderInterface::class);
    $display_plugin_manager = $this->createStub(ViewsPluginManager::class);
    $this->container->set('views.executable', new ViewExecutableFactory($user, $request_stack, $views_data, $route_provider, $display_plugin_manager));

    \Drupal::setContainer($this->container);
  }

  /**
   * Tests the getView() method.
   */
  public function testGetView(): void {
    $view = new View(['id' => 'test_view'], 'view');

    $view_storage = $this->getMockBuilder('Drupal\Core\Config\Entity\ConfigEntityStorage')
      ->disableOriginalConstructor()
      ->getMock();
    $view_storage->expects($this->once())
      ->method('load')
      ->with('test_view')
      ->willReturn($view);

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('view')
      ->willReturn($view_storage);
    $this->container->set('entity_type.manager', $entity_type_manager);

    $executable = Views::getView('test_view');
    $this->assertInstanceOf('Drupal\views\ViewExecutable', $executable);
    $this->assertEquals($view->id(), $executable->storage->id());
    $this->assertSame($view, $executable->storage);
  }

  /**
   * Tests the getView() method against a non-existent view.
   *
   * @legacy-covers ::getView
   */
  public function testGetNonExistentView(): void {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('test_view_non_existent')->willReturn(NULL);
    $entity_type_manager->getStorage('view')->willReturn($storage->reveal());
    $this->container->set('entity_type.manager', $entity_type_manager->reveal());
    $executable_does_not_exist = Views::getView('test_view_non_existent');
    $this->assertNull($executable_does_not_exist);
  }

  /**
   * Tests get applicable views.
   */
  #[DataProvider('providerTestGetApplicableViews')]
  public function testGetApplicableViews(string $applicable_type, array $expected): void {
    $view_1 = new View([
      'id' => 'test_view_1',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'display_options' => [],
        ],
        'type_a' => [
          'display_plugin' => 'type_a',
          'display_options' => [],
        ],
      ],
    ], 'view');
    $view_2 = new View([
      'id' => 'test_view_2',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'display_options' => [],
        ],
        'type_b' => [
          'display_plugin' => 'type_b',
          'display_options' => [
            'enabled' => TRUE,
          ],
        ],
        'type_b_2' => [
          'display_plugin' => 'type_b',
          'display_options' => [
            'enabled' => FALSE,
          ],
        ],
      ],
    ], 'view');
    $view_3 = new View([
      'id' => 'test_view_3',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'display_options' => [],
        ],
        // Test with Type A but a disabled display.
        'type_a' => [
          'display_plugin' => 'type_a',
          'display_options' => [
            'enabled' => FALSE,
          ],
        ],
        // Type D intentionally doesn't exist.
        'type_d' => [
          'display_plugin' => 'type_d',
          'display_options' => [],
        ],
      ],
    ], 'view');

    $query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $query->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn(['test_view_1', 'test_view_2', 'test_view_3']);

    $view_storage = $this->getMockBuilder('Drupal\Core\Config\Entity\ConfigEntityStorage')
      ->disableOriginalConstructor()
      ->getMock();
    $view_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $view_storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['test_view_1', 'test_view_2', 'test_view_3'])
      ->willReturn([
        'test_view_1' => $view_1,
        'test_view_2' => $view_2,
        'test_view_3' => $view_3,
      ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->exactly(2))
      ->method('getStorage')
      ->with('view')
      ->willReturn($view_storage);
    $this->container->set('entity_type.manager', $entity_type_manager);

    $definitions = [
      'type_a' => [
        'type_a' => TRUE,
        'type_b' => FALSE,
      ],
      'type_b' => [
        'type_a' => FALSE,
        'type_b' => TRUE,
      ],
    ];

    $display_manager = $this->createMock('Drupal\Component\Plugin\PluginManagerInterface');
    $display_manager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($definitions);
    $this->container->set('plugin.manager.views.display', $display_manager);

    $result = Views::getApplicableViews($applicable_type);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testGetApplicableViews.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerTestGetApplicableViews(): array {
    return [
      ['type_a', [['test_view_1', 'type_a']]],
      ['type_b', [['test_view_2', 'type_b']]],
      ['type_c', []],
    ];
  }

  /**
   * Tests the ::pluginManager() deprecation.
   */
  #[IgnoreDeprecations]
  public function testPluginManagerDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\views\Views::pluginManager() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal::service(\'plugin.manager.views.{type}\') for specific plugin types or \Drupal::service(\'views.plugin_managers\')->get($type) for dynamic types. See https://www.drupal.org/node/3566982');

    $plugin_manager = $this->createStub(ViewsPluginManager::class);

    $locator = $this->createMock(ServiceLocator::class);
    $locator->expects($this->once())
      ->method('get')
      ->with('display')
      ->willReturn($plugin_manager);
    $this->container->set('views.plugin_managers', $locator);

    $result = Views::pluginManager('display');
    $this->assertSame($plugin_manager, $result);
  }

  /**
   * Tests the ::handlerManager() deprecation.
   */
  #[IgnoreDeprecations]
  public function testHandlerManagerDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\views\Views::handlerManager() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal::service(\'plugin.manager.views.{type}\') for specific handler types or \Drupal::service(\'views.plugin_managers\')->get($type) for dynamic types. See https://www.drupal.org/node/3566982');

    $handler_manager = $this->createStub(ViewsHandlerManager::class);

    $locator = $this->createMock(ServiceLocator::class);
    $locator->expects($this->once())
      ->method('get')
      ->with('filter')
      ->willReturn($handler_manager);
    $this->container->set('views.plugin_managers', $locator);

    $result = Views::handlerManager('filter');
    $this->assertSame($handler_manager, $result);
  }

  /**
   * Tests the ::t() deprecation.
   */
  #[IgnoreDeprecations]
  public function testTranslateDeprecation(): void {
    $this->expectUserDeprecationMessage('Drupal\views\Views::t() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\Core\StringTranslation\TranslatableMarkup instead. See https://www.drupal.org/node/3626175');

    $this->container->set('string_translation', $this->createTranslationManager('ja', $this->createTranslator()));
    $result = (new \ReflectionMethod(Views::class, 't'))->invoke(NULL, '@count fields', ['@count' => 3], ['context' => 'views']);
    $this->assertInstanceOf(TranslatableMarkup::class, $result);
    $this->assertSame('@count fields', $result->getUntranslatedString());
    $this->assertSame(['@count' => 3], $result->getArguments());
    $this->assertSame('views', $result->getOption('context'));
    $this->assertSame('JA:3 fields', (string) $result);
  }

  /**
   * Tests that handler labels use the current container's translation service.
   */
  #[DataProvider('providerTestGetHandlerTypesUsesCurrentTranslation')]
  public function testGetHandlerTypesUsesCurrentTranslation(bool $replace_container): void {
    $translator = $this->createTranslator();
    $this->container->set('string_translation', $this->createTranslationManager('en', $translator));
    $english_types = Views::getHandlerTypes();
    $this->assertSame('EN:Fields', (string) $english_types['field']['title']);

    $japanese_manager = $this->createTranslationManager('ja', $translator);
    if ($replace_container) {
      $container = new ContainerBuilder();
      $container->set('string_translation', $japanese_manager);
      \Drupal::setContainer($container);
    }
    else {
      $this->container->set('string_translation', $japanese_manager);
    }

    $japanese_types = Views::getHandlerTypes();
    $this->assertSame('JA:Fields', (string) $japanese_types['field']['title']);
    $this->assertSame('JA:no results behavior', (string) $japanese_types['empty']['ltitle']);
    $this->assertNotSame($english_types['field']['title'], $japanese_types['field']['title']);
    $this->assertSame('EN:Fields', (string) $english_types['field']['title']);
  }

  /**
   * Data provider for testGetHandlerTypesUsesCurrentTranslation().
   *
   * @return array
   *   An array of test data.
   */
  public static function providerTestGetHandlerTypesUsesCurrentTranslation(): array {
    return [
      'same container' => [FALSE],
      'new container' => [TRUE],
    ];
  }

  /**
   * Tests that handler labels do not retain a replaced translation service.
   */
  public function testGetHandlerTypesReleasesTranslationManager(): void {
    $translator = $this->createTranslator();
    $english_manager = $this->createTranslationManager('en', $translator);
    $reference = \WeakReference::create($english_manager);
    $this->container->set('string_translation', $english_manager);
    $types = Views::getHandlerTypes();
    foreach ($types as $type) {
      foreach (['title', 'ltitle', 'stitle', 'lstitle'] as $key) {
        (string) $type[$key];
      }
    }

    $this->container->set('string_translation', $this->createTranslationManager('ja', $translator));
    unset($english_manager, $types, $type);
    gc_collect_cycles();
    $this->assertNull($reference->get());
  }

  /**
   * Tests that handler type labels are translated when rendered.
   */
  public function testGetHandlerTypesTranslatesLazily(): void {
    $translator = $this->createTranslator();
    $this->container->set('string_translation', $this->createTranslationManager('en', $translator));

    $types = Views::getHandlerTypes();
    $this->assertSame(0, $translator->calls);
    $this->assertInstanceOf(TranslatableMarkup::class, $types['sort']['stitle']);
    $this->assertSame('Sort criterion', $types['sort']['stitle']->getUntranslatedString());
    $this->assertSame('fields', $types['field']['plural']);
    $this->assertArrayNotHasKey('type', $types['field']);
    $this->assertSame('area', $types['header']['type']);

    $this->assertSame('EN:Sort criterion', (string) $types['sort']['stitle']);
    $this->assertSame(1, $translator->calls);
  }

  /**
   * Creates a translation manager with a fixed default language.
   *
   * @param string $langcode
   *   The default language code.
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   *   The translator to add.
   *
   * @return \Drupal\Core\StringTranslation\TranslationManager
   *   The translation manager.
   */
  protected function createTranslationManager(string $langcode, TranslatorInterface $translator): TranslationManager {
    $manager = new TranslationManager(new LanguageDefault(['id' => $langcode]));
    $manager->addTranslator($translator);
    return $manager;
  }

  /**
   * Creates a translator that prefixes strings with the language code.
   *
   * @return \Drupal\Core\StringTranslation\Translator\TranslatorInterface
   *   The translator. Its public $calls property counts translations.
   */
  protected function createTranslator(): TranslatorInterface {
    return new class() implements TranslatorInterface {

      /**
       * The number of translated strings.
       */
      public int $calls = 0;

      /**
       * {@inheritdoc}
       */
      public function getStringTranslation($langcode, $string, $context) {
        $this->calls++;
        return strtoupper($langcode) . ':' . $string;
      }

      /**
       * {@inheritdoc}
       */
      public function reset() {
      }

    };
  }

}
