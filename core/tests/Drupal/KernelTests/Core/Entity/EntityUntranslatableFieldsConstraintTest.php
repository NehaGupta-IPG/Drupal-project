<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityUntranslatableFieldsConstraint;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the constraint used to enforce untranslatable fields as read-only.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class EntityUntranslatableFieldsConstraintTest extends EntityKernelTestBase {

  const LANGCODE = 'de';

  const ENTITY_TYPE = 'entity_test_mulrev';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A user account used to create entities.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema(self::ENTITY_TYPE);
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    ConfigurableLanguage::createFromLangcode(self::LANGCODE)->save();
    $this->state->set('entity_test.translation', 1);
    $this->state->set('entity_test.untranslatable_fields.default_translation_affected', 0);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->user = $this->createUser();
  }

  /**
   * Ensure empty fields with a default value are not changed when translating.
   */
  #[DataProvider('accessValues')]
  public function testEntityUntranslatableFieldsConstraint(bool $access): void {
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->create([
      'name' => $this->randomString(),
      'user_id' => $this->user->id(),
      'langcode' => LanguageInterface::LANGCODE_DEFAULT,
    ]);
    $entity->save();
    $field_name = strtolower($this->randomMachineName());
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => self::ENTITY_TYPE,
      'type' => 'boolean',
      'translatable' => FALSE,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => self::ENTITY_TYPE,
      'default_value' => [['value' => TRUE]],
    ])->save();
    // Necessary for picking up the new field.
    $entity = $storage->loadUnchanged($entity->id());
    $entity = $entity->addTranslation(self::LANGCODE);
    $entity->isDefaultRevision(FALSE);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
    // The entity form should only contain the field under testing.
    $display = EntityFormDisplay::create([
      'targetEntityType' => self::ENTITY_TYPE,
      'bundle' => self::ENTITY_TYPE,
    ])->setComponent($field_name, [
      'type' => 'options_buttons',
    ]);
    $form = [];
    $form_state = new FormState();
    $display->buildForm($entity, $form, $form_state);
    // This relates to the bug under test, see the comment below.
    $form[$field_name]['widget']['#access'] = $access;

    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    // Build the form.
    $form_object = $this->entityTypeManager
      ->getFormObject(self::ENTITY_TYPE, 'default')
      ->setEntity($entity);
    $form_state->setFormObject($form_object);

    $form_id = self::ENTITY_TYPE . '_entity_form';
    $form_builder = $this->container->get('form_builder');
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);
    // Attempt to change an untranslatable field in a translation of a
    // non-default revision. This should trigger a constraint violation.
    // However, if the widget has access denied, the change will not be
    // applied to the entity. In that case, no violation should occur.
    $form_state->setValueForElement($form[$field_name]['widget'], [['value' => 1]]);

    // Validate the field constraint.
    $violations = $form_object
      ->setFormDisplay($display, $form_state)
      ->buildEntity($form, $form_state)
      ->validate()
      ->getEntityViolations();
    // If access is allowed, we expect one violation, when denied, zero
    // violations are expected.
    $this->assertCount((int) $access, $violations);
    // If there is a violation, make sure it's the right one.
    foreach ($violations as $violation) {
      $this->assertInstanceOf(EntityUntranslatableFieldsConstraint::class, $violation->getConstraint());
    }
  }

  /**
   * Data provider for ::testEntityUntranslatableFieldsConstraint().
   */
  public static function accessValues(): array {
    return [
      [TRUE],
      [FALSE],
    ];
  }

}
