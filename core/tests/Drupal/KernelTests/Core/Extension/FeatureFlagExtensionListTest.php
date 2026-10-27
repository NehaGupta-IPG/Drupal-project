<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Extension\FeatureFlagExtensionList;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\Core\Extension\FeatureFlagExtensionList.
 */
#[CoversClass(FeatureFlagExtensionList::class)]
#[Group('Extension')]
#[RunTestsInSeparateProcesses]
class FeatureFlagExtensionListTest extends KernelTestBase {

  /**
   * Tests get list.
   */
  public function testGetList(): void {
    \Drupal::configFactory()->getEditable('core.extension')
      ->set('feature_flag.test_flag', 0)
      ->set('profile', 'testing')
      ->save();

    // Saving the configuration doesn't automatically trigger invalidation.
    $this->container->get('kernel')->rebuildContainer();

    $flagExtensionList = \Drupal::service(FeatureFlagExtensionList::class);
    $extensions = $flagExtensionList->getList();

    $this->assertArrayHasKey('test_flag', $extensions);
  }

}
