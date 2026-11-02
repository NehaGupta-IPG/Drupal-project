<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'advanced' element of content entity forms.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class ContentEntityFormAdvancedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'user', 'system'];

  /**
   * Tests that a non-revisionable entity form has the 'advanced' element.
   */
  public function testAdvancedElementWithoutRevisionUi(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $entity = EntityTest::create(['name' => $this->randomString()]);
    $this->assertFalse($entity->getEntityType()->isRevisionable());

    $form = $this->container->get('entity.form_builder')->getForm($entity);

    $this->assertSame('vertical_tabs', $form['advanced']['#type']);
  }

}
