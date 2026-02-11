<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\RegisterForm;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AccountForm::buildEntity() with RegisterForm.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class AccountFormBuildEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
  }

  /**
   * Tests creating a user entity from form values.
   *
   * @legacy-covers ::buildEntity
   */
  public function testBuildEntity(): void {
    // Allow visitor registration.
    $this->config('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS)
      ->save();

    /** @var \Drupal\user\RegisterForm $form_object */
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(RegisterForm::class);

    // Create a user entity.
    $user = User::create();

    // Create the entity form display required by ContentEntityForm.
    $form_display = EntityFormDisplay::create([
      'targetEntityType' => 'user',
      'bundle' => 'user',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $form_display->save();

    // Attach an entity and form a display.
    $form_object->setEntity($user);

    $name = $this->randomMachineName();
    $mail = $name . '@example.com';

    $form_state = new FormState();
    $form_state->setValues([
      'name' => $name,
      'mail' => $mail,
      'status' => 1,
    // Roles are set to NULL to validate that the authenticated role is
    // assigned on entity save.
      'roles' => NULL,
    ]);

    $form_object->setFormDisplay($form_display, $form_state);
    $built_entity = $form_object->buildEntity([], $form_state);

    $built_entity->setValidationRequired(FALSE);

    // Save is required to trigger validation and assign roles.
    $built_entity->save();

    $this->assertInstanceOf(UserInterface::class, $built_entity);
    $this->assertSame($name, $built_entity->getAccountName());
    $this->assertSame($mail, $built_entity->getEmail());
    $this->assertTrue($built_entity->isActive());

    // Authenticated role is always assigned on save.
    $roles = $built_entity->getRoles();
    $this->assertContains('authenticated', $roles);
    $this->assertSame(['authenticated'], array_values($roles));
  }

}
