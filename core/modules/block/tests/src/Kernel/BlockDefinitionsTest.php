<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Kernel;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Plugin\Discovery\AttributeDiscoveryWithAnnotations;
use Drupal\KernelTests\FileSystemModuleDiscoveryDataProviderTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests block plugin definitions.
 */
#[Group('block')]
class BlockDefinitionsTest extends KernelTestBase {

  use FileSystemModuleDiscoveryDataProviderTrait;

  /**
   * Tests that every block plugin has a category.
   */
  public function testBlockCategory(): void {
    // Get a list of all modules.
    $modules = $this->coreModuleListDataProvider();

    // Load all modules so we can scan them for block plugins.
    $this->enableModules(array_keys($modules));

    // We use the AttributeDiscoveryWithAnnotations instead of the BlockManager so we can
    // check for empty categories before they are fill with the module's name.
    $namespaces = $this->container->get('container.namespaces');
    $discovery = new AttributeDiscoveryWithAnnotations('Plugin/Block', $namespaces, Block::class, 'Drupal\Core\Block\Annotation\Block');
    $missing = array_filter($discovery->getDefinitions(), fn ($definition) => empty($definition['category']));
    $this->assertEmpty($missing, 'Block plugins missing categories: ' . implode(', ', array_map(fn ($definition) => $definition['id'], $missing)));
  }

}
