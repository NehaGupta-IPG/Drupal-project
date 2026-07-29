<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests tabledrag configuration on the Manage display overview form.
 */
#[CoversClass(EntityDisplayFormBase::class)]
#[Group('Entity')]
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class EntityDisplayFormTableDragTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'entity_test',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    EntityTestHelper::createBundle('bundle_with_extra_fields');
  }

  /**
   * Tests tabledrag match-parent copies correct parent source.
   * 
   * When a row is indented under a parent, tabledrag "match parent" rules
   * copy values from the parent row into the child. Two separate rules exist:
   * - field-parent: child should inherit the parent's machine name.
   * - field-region: child should inherit the parent's region.
   */
  public function testTableDragRegionMatchParentSource(): void {
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'bundle_with_extra_fields');
    $display->save();

    $form_object = $this->entityTypeManager->getFormObject('entity_view_display', 'edit');
    $form_object->setEntity($display);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

    $this->assertArrayHasKey('fields', $form);
    $this->assertArrayHasKey('#tabledrag', $form['fields']);

    $region_match_rule = NULL;
    $parent_match_rule = NULL;
    foreach ($form['fields']['#tabledrag'] as $rule) {
      // Continue, if it's not a match parent rule:
      if ($rule['action'] !== 'match' || $rule['relationship'] !== 'parent') {
        continue;
      }
      if ($rule['group'] === 'field-region') {
        $region_match_rule = $rule;
      }
      if ($rule['group'] === 'field-parent') {
        $parent_match_rule = $rule;
      }
    }

    $this->assertNotNull($region_match_rule, 'Field region match-parent tabledrag rule is present.');
    $this->assertSame('field-region', $region_match_rule['source']);
    $this->assertNotNull($parent_match_rule, 'Field parent match-parent tabledrag rule is present.');
    $this->assertSame('field-name', $parent_match_rule['source']);
  }

}
