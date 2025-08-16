<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * @coversDefaultClass \Drupal\layout_builder\SectionStorage\SectionStorageManager
 *
 * @group layout_builder
 */
class SectionStorageManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_test',
  ];

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manager = $this->container->get('plugin.manager.layout_builder.section_storage');
  }

  /**
   * @covers ::load
   */
  public function testLoad(): void {
    // Provide a context value that does not meet the constraints.
    $contexts['value'] = new Context(new ContextDefinition('string'), 'Grit');
    $result = $this->manager->load('layout_builder_test_constraints', $contexts);
    $this->assertNull($result);

    // Providing a new context value that meets the constraints ensures the
    // plugin is returned.
    $contexts['value'] = new Context(new ContextDefinition('string'), 'Gritty');
    $result = $this->manager->load('layout_builder_test_constraints', $contexts);
    $this->assertInstanceOf(SectionStorageInterface::class, $result);
    $this->assertSame('layout_builder_test_constraints', $result->getPluginId());
  }

  /**
   * @covers ::findByContext
   *
   * @see \Drupal\layout_builder_test\Plugin\SectionStorage\TestConstraintsSectionStorage
   */
  public function testFindByContext(): void {
    // Provide a context value that does not meet the constraints.
    $contexts['value'] = new Context(new ContextDefinition('string'), 'Grit');
    $result = $this->manager->findByContext($contexts, new CacheableMetadata());
    $this->assertNull($result);

    // Providing a new context value that meets the constraints ensures the
    // plugin is returned.
    $contexts['value'] = new Context(new ContextDefinition('string'), 'Gritty');
    $result = $this->manager->findByContext($contexts, new CacheableMetadata());
    $this->assertInstanceOf(SectionStorageInterface::class, $result);
    $this->assertSame('layout_builder_test_constraints', $result->getPluginId());
  }

}
