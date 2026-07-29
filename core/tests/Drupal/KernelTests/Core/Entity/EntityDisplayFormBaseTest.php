<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Form\FormState;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\field_ui\Form\EntityDisplayFormBase.
 */
#[CoversClass(EntityDisplayFormBase::class)]
#[Group('Entity')]
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class EntityDisplayFormBaseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'user']);
  }

  /**
   * Tests tabledrag match-parent copies the parent region, not its machine name.
   */
  public function testTableDragRegionMatchParentSource(): void {
    // Build the Manage display overview form:
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test');
    $display->save();

    $form_object = EntityViewDisplayEditForm::create($this->container);
    $form_object->setEntity($display);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

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
    // not from the hidden machine name input (.field-name).
    $this->assertNotNull($region_match_rule, 'Field region match-parent tabledrag rule is present.');
    $this->assertSame('field-region', $region_match_rule['source']);
    $this->assertNotNull($parent_match_rule, 'Field parent match-parent tabledrag rule is present.');
    $this->assertSame('field-name', $parent_match_rule['source']);
  }

  /**
   * Tests copy form values to entity.
   */
  public function testCopyFormValuesToEntity(): void {
    $field_values = [];
    $entity = $this->prophesize(EntityDisplayInterface::class);
    $entity->getPluginCollections()->willReturn([]);
    $entity->getTargetEntityTypeId()->willReturn('entity_test_with_bundle');
    $entity->getTargetBundle()->willReturn('target_bundle');

    // An initially hidden field, with a submitted region change.
    $entity->getComponent('new_field_mismatch_type_visible')->willReturn([]);
    $field_values['new_field_mismatch_type_visible'] = [
      'weight' => 0,
      'type' => 'textfield',
      'region' => 'hidden',
    ];
    $entity->removeComponent('new_field_mismatch_type_visible')
      ->will(function (array $args) use ($entity): void {
        // On subsequent calls, getComponent() will return an empty array.
        $entity->getComponent($args[0])->willReturn([]);
      })
      ->shouldBeCalled();

    // An initially visible field, with identical submitted values.
    $entity->getComponent('field_visible_no_changes')
      ->willReturn([
        'weight' => 0,
        'type' => 'textfield',
        'region' => 'content',
      ]);
    $field_values['field_visible_no_changes'] = [
      'weight' => 0,
      'type' => 'textfield',
      'region' => 'content',
    ];
    $entity
      ->setComponent('field_visible_no_changes', [
        'weight' => 0,
        'type' => 'textfield',
        'region' => 'content',
      ])
      ->shouldBeCalled();

    // An initially visible field, with a submitted region change.
    $entity->getComponent('field_start_visible_change_region')
      ->willReturn([
        'weight' => 0,
        'type' => 'textfield',
        'region' => 'content',
      ]);
    $field_values['field_start_visible_change_region'] = [
      'weight' => 0,
      'type' => 'textfield',
      'region' => 'hidden',
    ];
    $entity->removeComponent('field_start_visible_change_region')
      ->will(function (array $args) use ($entity): void {
        // On subsequent calls, getComponent() will return an empty array.
        $entity->getComponent($args[0])->willReturn([]);
      })
      ->shouldBeCalled();

    // A field that is flagged for plugin settings update on the second build.
    $entity->getComponent('field_plugin_settings_update')
      ->willReturn([
        'weight' => 0,
        'type' => 'textfield',
        'region' => 'content',
      ]);
    $field_values['field_plugin_settings_update'] = [
      'weight' => 0,
      'type' => 'textfield',
      'region' => 'content',
      'settings_edit_form' => [
        'third_party_settings' => [
          'foo' => 'bar',
        ],
      ],
    ];
    $entity
      ->setComponent('field_plugin_settings_update', [
        'weight' => 0,
        'type' => 'textfield',
        'region' => 'content',
      ])
      ->will(function (array $args) use ($entity): void {
        // On subsequent calls, getComponent() will return the newly set values.
        $entity->getComponent($args[0])->willReturn($args[1]);
        $args[1] += [
          'settings' => [],
          'third_party_settings' => [
            'foo' => 'bar',
          ],
        ];
        $entity->setComponent($args[0], $args[1])->shouldBeCalled();
      })
      ->shouldBeCalled();

    $form_object = new EntityViewDisplayEditForm(
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('plugin.manager.field.formatter'),
      $this->container->get('entity_display.repository'),
      $this->container->get('entity_field.manager')
    );
    $form_object->setEntity($entity->reveal());

    $form = [
      '#fields' => array_keys($field_values),
      '#extra' => [],
    ];
    $form_state = new FormState();
    $form_state->setValues(['fields' => $field_values]);
    $form_state->setProcessInput();

    $form_object->buildEntity($form, $form_state);
    $form_state->setSubmitted();

    // Flag one field for updating plugin settings.
    $form_state->set('plugin_settings_update', 'field_plugin_settings_update');
    // During form submission, buildEntity() will be called twice. Simulate that
    // here to prove copyFormValuesToEntity() is idempotent.
    $form_object->buildEntity($form, $form_state);
  }

}
