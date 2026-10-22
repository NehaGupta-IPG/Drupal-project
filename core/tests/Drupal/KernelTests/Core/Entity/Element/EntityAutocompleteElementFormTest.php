<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity\Element;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the EntityAutocomplete Form API element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class EntityAutocompleteElementFormTest extends EntityKernelTestBase implements FormInterface {

  /**
   * User for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * User for autocreate testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testAutocreateUser;

  /**
   * An array of entities to be referenced in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $referencedEntities;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_string_id');

    // Create user 1 so that the user created later in the test has a different
    // user ID.
    // @todo Remove in https://www.drupal.org/node/540008.
    User::create(['uid' => 1, 'name' => 'user1'])->save();

    Role::create([
      'id' => 'test_role',
      'label' => 'Can view test entities',
      'permissions' => ['view test entity'],
    ])->save();

    $this->testUser = User::create([
      'name' => 'foobar1',
      'mail' => 'foobar1@example.com',
      'roles' => ['test_role'],
    ]);
    $this->testUser->save();
    \Drupal::service('current_user')->setAccount($this->testUser);

    $this->testAutocreateUser = User::create([
      'name' => 'foobar2',
      'mail' => 'foobar2@example.com',
    ]);
    $this->testAutocreateUser->save();

    for ($i = 1; $i < 3; $i++) {
      $entity = EntityTest::create([
        'name' => $this->randomMachineName(),
      ]);
      $entity->save();
      $this->referencedEntities[] = $entity;
    }

    // Use special characters in the ID of some of the test entities so we can
    // test if these are handled correctly.
    for ($i = 0; $i < 2; $i++) {
      $entity = EntityTestStringId::create([
        'name' => $this->randomMachineName(),
        'id' => $this->randomMachineName() . '&</\\:?',
      ]);
      $entity->save();
      $this->referencedEntities[] = $entity;
    }

    $entity = EntityTest::create([
      'name' => '0',
    ]);
    $entity->save();
    $this->referencedEntities[] = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'test_entity_autocomplete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['single'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
    ];
    $form['single_autocreate'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];
    $form['single_autocreate_specific_uid'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#autocreate' => [
        'bundle' => 'entity_test',
        'uid' => $this->testAutocreateUser->id(),
      ],
    ];

    $form['tags'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
    ];
    $form['tags_autocreate'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];
    $form['tags_autocreate_specific_uid'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#autocreate' => [
        'bundle' => 'entity_test',
        'uid' => $this->testAutocreateUser->id(),
      ],
    ];

    $form['single_no_validate'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#validate_reference' => FALSE,
    ];
    $form['single_autocreate_no_validate'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#validate_reference' => FALSE,
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];

    $form['single_access'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#default_value' => $this->referencedEntities[0],
    ];
    $form['tags_access'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#default_value' => [$this->referencedEntities[0], $this->referencedEntities[1]],
    ];

    $form['single_string_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test_string_id',
    ];
    $form['tags_string_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test_string_id',
      '#tags' => TRUE,
    ];

    $form['single_name_0'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'entity_test',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Tests valid entries in the EntityAutocomplete Form API element.
   */
  public function testValidEntityAutocompleteElement(): void {
    $form_state = (new FormState())
      ->setValues([
        'single' => $this->getAutocompleteInput($this->referencedEntities[0]),
        'single_autocreate' => 'single - autocreated entity label',
        'single_autocreate_specific_uid' => 'single - autocreated entity label with specific uid',
        'tags' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'tags_autocreate' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', tags - autocreated entity label, ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'tags_autocreate_specific_uid' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', tags - autocreated entity label with specific uid, ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'single_string_id' => $this->getAutocompleteInput($this->referencedEntities[2]),
        'tags_string_id' => $this->getAutocompleteInput($this->referencedEntities[2]) . ', ' . $this->getAutocompleteInput($this->referencedEntities[3]),
        'single_name_0' => $this->referencedEntities[4]->label(),
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    // Valid form state.
    $this->assertCount(0, $form_state->getErrors());

    // Test the 'single' element.
    $this->assertEquals($this->referencedEntities[0]->id(), $form_state->getValue('single'));

    // Test the 'single_autocreate' element.
    $value = $form_state->getValue('single_autocreate');
    $this->assertEquals('single - autocreated entity label', $value['entity']->label());
    $this->assertEquals('entity_test', $value['entity']->bundle());
    $this->assertEquals($this->testUser->id(), $value['entity']->getOwnerId());

    // Test the 'single_autocreate_specific_uid' element.
    $value = $form_state->getValue('single_autocreate_specific_uid');
    $this->assertEquals('single - autocreated entity label with specific uid', $value['entity']->label());
    $this->assertEquals('entity_test', $value['entity']->bundle());
    $this->assertEquals($this->testAutocreateUser->id(), $value['entity']->getOwnerId());

    // Test the 'tags' element.
    $expected = [
      ['target_id' => $this->referencedEntities[0]->id()],
      ['target_id' => $this->referencedEntities[1]->id()],
    ];
    $this->assertEquals($expected, $form_state->getValue('tags'));

    // Test the 'single_autocreate' element.
    $value = $form_state->getValue('tags_autocreate');
    // First value is an existing entity.
    $this->assertEquals($this->referencedEntities[0]->id(), $value[0]['target_id']);
    // Second value is an autocreated entity.
    $this->assertTrue(!isset($value[1]['target_id']));
    $this->assertEquals('tags - autocreated entity label', $value[1]['entity']->label());
    $this->assertEquals($this->testUser->id(), $value[1]['entity']->getOwnerId());
    // Third value is an existing entity.
    $this->assertEquals($this->referencedEntities[1]->id(), $value[2]['target_id']);

    // Test the 'tags_autocreate_specific_uid' element.
    $value = $form_state->getValue('tags_autocreate_specific_uid');
    // First value is an existing entity.
    $this->assertEquals($this->referencedEntities[0]->id(), $value[0]['target_id']);
    // Second value is an autocreated entity.
    $this->assertTrue(!isset($value[1]['target_id']));
    $this->assertEquals('tags - autocreated entity label with specific uid', $value[1]['entity']->label());
    $this->assertEquals($this->testAutocreateUser->id(), $value[1]['entity']->getOwnerId());
    // Third value is an existing entity.
    $this->assertEquals($this->referencedEntities[1]->id(), $value[2]['target_id']);

    // Test the 'single_string_id' element.
    $this->assertEquals($this->referencedEntities[2]->id(), $form_state->getValue('single_string_id'));

    // Test the 'tags_string_id' element.
    $expected = [
      ['target_id' => $this->referencedEntities[2]->id()],
      ['target_id' => $this->referencedEntities[3]->id()],
    ];
    $this->assertEquals($expected, $form_state->getValue('tags_string_id'));

    // Test the 'single_name_0' element.
    $this->assertEquals($this->referencedEntities[4]->id(), $form_state->getValue('single_name_0'));
  }

  /**
   * Tests invalid entries in the EntityAutocomplete Form API element.
   */
  public function testInvalidEntityAutocompleteElement(): void {
    $form_builder = $this->container->get('form_builder');

    // Test 'single' with an entity label that doesn't exist.
    $form_state = (new FormState())
      ->setValues([
        'single' => 'single - non-existent label',
      ]);
    $form_builder->submitForm($this, $form_state);
    $this->assertCount(1, $form_state->getErrors());
    $this->assertEquals('There are no test entity entities matching "single - non-existent label".', $form_state->getErrors()['single']);

    // Test 'single' with an entity ID that doesn't exist.
    $form_state = (new FormState())
      ->setValues([
        'single' => 'single - non-existent label (42)',
      ]);
    $form_builder->submitForm($this, $form_state);
    $this->assertCount(1, $form_state->getErrors());
    $this->assertEquals('The referenced entity (entity_test: 42) does not exist.', $form_state->getErrors()['single']);

    // Do the same tests as above but on an element with '#validate_reference'
    // set to FALSE.
    $form_state = (new FormState())
      ->setValues([
        'single_no_validate' => 'single - non-existent label',
        'single_autocreate_no_validate' => 'single - autocreate non-existent label',
      ]);
    $form_builder->submitForm($this, $form_state);

    // The element without 'autocreate' support still has to emit a warning when
    // the input doesn't end with an entity ID enclosed in parentheses.
    $this->assertCount(1, $form_state->getErrors());
    $this->assertEquals('There are no test entity entities matching "single - non-existent label".', $form_state->getErrors()['single_no_validate']);

    $form_state = (new FormState())
      ->setValues([
        'single_no_validate' => 'single - non-existent label (42)',
        'single_autocreate_no_validate' => 'single - autocreate non-existent label (43)',
      ]);
    $form_builder->submitForm($this, $form_state);

    // The input is complete (i.e. contains an entity ID at the end), no errors
    // are triggered.
    $this->assertCount(0, $form_state->getErrors());

    // Mixed input uses its configured handler without loading rejected targets.
    $selection_manager = $this->container->get('plugin.manager.entity_reference_selection');
    $entity_type_manager = $this->container->get('entity_type.manager');
    try {
      foreach ([FALSE, TRUE] as $new_first) {
        $target = EntityTest::create(['name' => 'First new target']);
        $other_target = EntityTest::create(['name' => 'Second new target']);
        $calls = [];
        $handler = $this->createMock(DefaultSelection::class);
        $handler->expects($this->once())->method('validateReferenceableEntities')
          ->with($new_first ? [1 => 17, 3 => 18] : [0 => 17, 2 => 18])
          ->willReturnCallback(static function (array $ids) use (&$calls): array {
            $calls[] = 'existing';
            return [];
          });
        $handler->expects($this->once())->method('validateReferenceableNewEntities')
          ->with($new_first ? [0 => $target, 2 => $other_target] : [1 => $target, 3 => $other_target])
          ->willReturnCallback(static function (array $entities) use (&$calls): array {
            $calls[] = 'new';
            return [];
          });
        $manager = $this->createMock(SelectionPluginManagerInterface::class);
        $manager->expects($this->once())->method('getInstance')->willReturn($handler);
        $manager->expects($this->never())->method('getSelectionHandler');
        $this->container->set('plugin.manager.entity_reference_selection', $manager);
        $storage_manager = $this->createMock(EntityTypeManagerInterface::class);
        $storage_manager->method('getDefinition')->willReturn($target->getEntityType());
        $storage_manager->expects($this->never())->method('getStorage');
        $this->container->set('entity_type.manager', $storage_manager);

        $values = $new_first
          ? [['entity' => $target], ['target_id' => 17], ['entity' => $other_target], ['target_id' => 18]]
          : [['target_id' => 17], ['entity' => $target], ['target_id' => 18], ['entity' => $other_target]];
        $element = [
          '#value' => $values,
          '#target_type' => 'entity_test',
          '#selection_handler' => 'default',
          '#selection_settings' => [],
          '#autocreate' => ['bundle' => 'entity_test'],
          '#validate_reference' => TRUE,
          '#tags' => TRUE,
          '#parents' => ['reference'],
        ];
        $state = new class extends FormState {

          /**
           * All reported errors, including those discarded by FormState.
           *
           * @var string[]
           */
          public array $reportedErrors = [];

          /**
           * {@inheritdoc}
           */
          public function setError(array &$element, $message = '') {
            $this->reportedErrors[] = (string) $message;
            return parent::setError($element, $message);
          }

        };
        $state->clearErrors();
        $form = [];
        EntityAutocomplete::validateEntityAutocomplete($element, $state, $form);
        $this->assertSame(['new', 'existing'], $calls);
        $expected = [
          'The referenced entity (<em class="placeholder">entity_test</em>: <em class="placeholder">17</em>) does not exist.',
          'The referenced entity (<em class="placeholder">entity_test</em>: <em class="placeholder">18</em>) does not exist.',
          'This entity (<em class="placeholder">entity_test</em>: <em class="placeholder">First new target</em>) cannot be referenced.',
          'This entity (<em class="placeholder">entity_test</em>: <em class="placeholder">Second new target</em>) cannot be referenced.',
        ];
        $this->assertSame($expected, $state->reportedErrors);
        $this->assertSame($expected[0], (string) $state->getError($element));
        $this->assertSame($values, $state->getValue('reference'));
        $this->container->set('entity_type.manager', $entity_type_manager);
      }
    }
    finally {
      $this->container->set('plugin.manager.entity_reference_selection', $selection_manager);
      $this->container->set('entity_type.manager', $entity_type_manager);
    }
  }

  /**
   * Tests that access is properly checked by the EntityAutocomplete element.
   */
  public function testEntityAutocompleteAccess(): void {
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm($this);

    // Check that the current user has proper access to view entity labels.
    $expected = $this->referencedEntities[0]->label() . ' (' . $this->referencedEntities[0]->id() . ')';
    $this->assertEquals($expected, $form['single_access']['#value']);

    $expected .= ', ' . $this->referencedEntities[1]->label() . ' (' . $this->referencedEntities[1]->id() . ')';
    $this->assertEquals($expected, $form['tags_access']['#value']);

    // Set up a non-admin user that is *not* allowed to view test entities.
    \Drupal::currentUser()->setAccount($this->createUser());

    // Rebuild the form.
    $form = $form_builder->getForm($this);

    $expected = '- Restricted access - (' . $this->referencedEntities[0]->id() . ')';
    $this->assertEquals($expected, $form['single_access']['#value']);

    $expected .= ', - Restricted access - (' . $this->referencedEntities[1]->id() . ')';
    $this->assertEquals($expected, $form['tags_access']['#value']);
  }

  /**
   * Tests ID input is handled correctly.
   *
   * E.g. This can happen with GET form parameters.
   */
  public function testEntityAutocompleteIdInput(): void {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    // $form = $form_builder->getForm($this);
    $form_state = (new FormState())
      ->setMethod('GET')
      ->setValues([
        'single' => [['target_id' => $this->referencedEntities[0]->id()]],
        'single_no_validate' => [['target_id' => $this->referencedEntities[0]->id()]],
      ]);

    $form_builder->submitForm($this, $form_state);

    $form = $form_state->getCompleteForm();

    $expected_label = $this->getAutocompleteInput($this->referencedEntities[0]);
    $this->assertSame($expected_label, $form['single']['#value']);
    $this->assertSame($expected_label, $form['single_no_validate']['#value']);
  }

  /**
   * Returns an entity label in format needed by the EntityAutocomplete element.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A Drupal entity.
   *
   * @return string
   *   A string that can be used as a value for EntityAutocomplete elements.
   */
  protected function getAutocompleteInput(EntityInterface $entity) {
    return EntityAutocomplete::getEntityLabels([$entity]);
  }

}
