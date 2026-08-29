<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme\Style;

use Drupal\Core\Extension\ModuleWeight;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Core\Theme\Style\StylePluginManager;
use Drupal\Core\Theme\Style\StylePluginManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests values are from test module style_test.
 *
 * Any change of the definition will impact the tests.
 *
 * @see core/modules/system/tests/modules/style_test/style_test.styles.yml
 */
#[CoversClass(StylePluginManager::class)]
#[Group('style')]
#[RunTestsInSeparateProcesses]
class StylePluginManagerKernelTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'style_test',
  ];

  /**
   * The StylePluginManager service.
   */
  protected StylePluginManagerInterface $stylePluginManager;

  /**
   * The ModuleWeight service.
   */
  protected ModuleWeight $moduleWeight;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stylePluginManager = $this->container->get(StylePluginManagerInterface::class);
    $this->moduleWeight = $this->container->get(ModuleWeight::class);
  }

  /**
   * Tests that plugins can be provided by YAML files.
   */
  public function testDetectedPlugins(): void {
    $expectations = [
      'test_mode' => [
        'enabled' => TRUE,
        'id' => 'test_mode',
        'provider' => 'style_test',
        'label' => new TranslatableMarkup('Mode label'),
        'description' => new TranslatableMarkup('Test mode.'),
        'type' => 'mode',
        'options' => [
          'any' => [
            'label' => 'Any',
            'value' => 'any-tag-class',
          ],
          'meta' => [
            'label' => 'Meta',
            'value' => 'meta-style-value',
            'attribute' => 'meta-style-name',
            'target' => 'meta',
          ],
          'html' => [
            'label' => 'HTML',
            'value' => 'html-style-value',
            'target' => 'html',
          ],
          'body' => [
            'label' => 'Body',
            'value' => 'body-style-value',
            'target' => 'body',
          ],
        ],
      ],
      'test_utility' => [
        'enabled' => TRUE,
        'id' => 'test_utility',
        'provider' => 'style_test',
        'label' => new TranslatableMarkup('Utility label'),
        'description' => new TranslatableMarkup('Test utility.'),
        'type' => 'utility',
        'options' => [
          'small' => [
            'label' => 'Small',
            'value' => 'text-sm',
          ],
          'medium' => [
            'label' => 'Medium',
            'value' => 'font-size: 15px',
            'attribute' => 'style',
          ],
          'large' => [
            'label' => 'Large',
            'value' => 'large',
            'attribute' => 'cds-text',
          ],
          'experimental' => [
            'label' => 'Experimental',
            'value' => 'experimental',
            'status' => 'experimental',
          ],
          'deprecated' => [
            'label' => 'Deprecated',
            'value' => 'deprecated',
            'status' => 'deprecated',
          ],
          'obsolete' => [
            'label' => 'Obsolete',
            'value' => 'obsolete',
            'status' => 'obsolete',
          ],
        ],
      ],
      'test_utility_library' => [
        'enabled' => TRUE,
        'id' => 'test_utility_library',
        'provider' => 'style_test',
        'label' => new TranslatableMarkup('Utility with library'),
        'description' => new TranslatableMarkup('Test utility.'),
        'type' => 'utility',
        'options' => [
          'with_library' => [
            'label' => 'Library',
            'value' => 'my-class',
          ],
        ],
        'library' => 'style_test/style_test',
      ],
    ];

    $definitions = $this->stylePluginManager->getDefinitions();
    $this->assertEquals(3, \count($definitions), 'There should be three styles detected.');
    foreach ($expectations as $pluginId => $expectedDefinition) {
      $expectedDefinition = new StyleDefinition($expectedDefinition);
      $this->assertEquals($expectedDefinition, $definitions[$pluginId]);
    }
  }

  /**
   * Test that it is possible to override an already declared plugin.
   */
  public function testOverridingDefinition(): void {
    $this->enableModules(['style_override_test']);

    // Test when the module overriding the definition is executed before.
    $this->moduleWeight->set('style_override_test', -1);
    $this->stylePluginManager = $this->container->get(StylePluginManagerInterface::class);
    $this->assertArrayHasKey('test_mode', $this->stylePluginManager->getDefinitions());

    // Test when the module overriding the definition is executed after.
    $this->moduleWeight->set('style_override_test', 1);
    // Need to rebuild the container to get a new style plugin manager instance
    // without cached data.
    // $this->stylePluginManager->clearCachedDefinitions() does not work.
    /** @var \Drupal\Core\DrupalKernelInterface $kernel */
    $kernel = \Drupal::service('kernel');
    $kernel->rebuildContainer();

    $this->stylePluginManager = $this->container->get(StylePluginManagerInterface::class);
    $this->assertArrayNotHasKey('test_mode', $this->stylePluginManager->getDefinitions());
  }

}
