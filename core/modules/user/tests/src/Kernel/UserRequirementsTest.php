<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests user_requirements().
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserRequirementsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user'];

  /**
   * Module handler for invoking user requirements.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleHandler = $this->container->get('module_handler');
    $this->installEntitySchema('user');
    include_once $this->root . '/core/includes/install.inc';
  }

  /**
   * Tests that the requirements check can detect conflicting user emails.
   *
   * @see \Drupal\Tests\user\Kernel\UserValidationTest::testValidation
   */
  public function testConflictingUserEmails(): void {

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('conflicting emails', $output);

    $this->createUser([], 'User A', FALSE, ['mail' => 'unique@example.com']);
    $this->createUser([], 'User B', FALSE, ['mail' => 'UNIQUE@example.com']);

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayHasKey('conflicting emails', $output);
  }

  /**
   * Tests that the requirements check does not incorrectly flag blank emails.
   */
  public function testBlankUserEmails(): void {

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('conflicting emails', $output);

    $this->createUser([], 'User A', FALSE, ['mail' => '']);
    $this->createUser([], 'User B', FALSE, ['mail' => '']);

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('conflicting emails', $output);
  }

  /**
   * Tests that the requirements check does not flag user translations.
   */
  public function testTranslatedUserEmail(): void {
    \Drupal::service('module_installer')->install(['language']);
    ConfigurableLanguage::createFromLangcode('is')->save();

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('conflicting emails', $output);

    $user = $this->createUser([], 'User A', FALSE, ['mail' => 'unique@example.com']);
    $user->addTranslation('is')->save();

    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('conflicting emails', $output);
  }

  /**
   * Tests that displays using the deprecated 'member_for' field are flagged.
   */
  public function testMemberForExtraField(): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'user',
      'bundle' => 'user',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->save();

    // The field is not on the display by default, assert we don't have the
    // warning.
    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayNotHasKey('user_member_for_extra_field', $output);

    // Add the field, assert the warning comes through.
    $display->setComponent('member_for', ['weight' => 5])->save();
    $output = $this->moduleHandler->invoke('user', 'runtime_requirements');
    $this->assertArrayHasKey('user_member_for_extra_field', $output);
    $this->assertSame('1 user display', (string) $output['user_member_for_extra_field']['value']);
    $items = $output['user_member_for_extra_field']['description']['displays']['#items'];

    // Display is listed in plain text if field_ui is not enabled.
    $this->assertCount(1, $items);
    $this->assertSame('Default', (string) $items[0]['#plain_text']);

    // Display is linked if field_ui is enabled.
    \Drupal::service('module_installer')->install(['field_ui']);
    $output = \Drupal::moduleHandler()->invoke('user', 'runtime_requirements');
    $item = $output['user_member_for_extra_field']['description']['displays']['#items'][0];
    $this->assertSame('Default', (string) $item['#title']);
    $this->assertSame('/admin/config/people/accounts/display/default', $item['#url']->toString());
  }

}
