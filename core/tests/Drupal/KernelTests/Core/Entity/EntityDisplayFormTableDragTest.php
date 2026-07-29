<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;
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
  protected function setUp(): void {
    parent::setUp();
    // Use a bundle with extra display fields so the overview table is built
    // without hitting the "no fields" early return (which needs field_ui routes).
    EntityTestHelper::createBundle('bundle_with_extra_fields');
  }

  /**
   * Tests tabledrag match-parent copies the parent region, not its machine name.
   */
  public function testTableDragRegionMatchParentSource(): void {
    // Build the Manage display overview form. form() is used instead of
    // buildForm() so field_ui does not need to be enabled as a module.
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'bundle_with_extra_fields');

    $form_object = EntityViewDisplayEditForm::create($this->container);
    $form_object->setEntity($display);
    $form_state = new FormState();
    $form = $form_object->form([], $form_state);

    $this->assertArrayHasKey('fields', $form);
    $this->assertArrayHasKey('#tabledrag', $form['fields']);

    // When a row is indented under a parent, tabledrag "match parent" rules
    // copy values from the parent row into the child. Two separate rules exist:
    // - field-parent: child should inherit the parent's machine name.
    // - field-region: child should inherit the parent's region (content/hidden).
    $region_match_rule = NULL;
    $parent_match_rule = NULL;
    foreach ($form['fields']['#tabledrag'] as $rule) {
      if (($rule['action'] ?? '') !== 'match' || ($rule['relationship'] ?? '') !== 'parent') {
        continue;
      }
      if (($rule['group'] ?? '') === 'field-region') {
        $region_match_rule = $rule;
      }
      if (($rule['group'] ?? '') === 'field-parent') {
        $parent_match_rule = $rule;
      }
    }

    // The region rule must copy from the parent's region select (.field-region),
    // not from the hidden machine name input (.field-name). Using field-name
    // here would submit an invalid region key and normalize the field to hidden.
    $this->assertNotNull($region_match_rule, 'Field region match-parent tabledrag rule is present.');
    $this->assertSame('field-region', $region_match_rule['source']);
    $this->assertNotNull($parent_match_rule, 'Field parent match-parent tabledrag rule is present.');
    $this->assertSame('field-name', $parent_match_rule['source']);
  }

}
