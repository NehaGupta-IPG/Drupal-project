<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel\Views;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests views user argument default plugin.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class ArgumentDefaultTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'user_test_views',
    'views',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_plugin_argument_default_current_user'];

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    $this->installEntitySchema('user');
    ViewTestData::createTestViews(static::class, ['user_test_views']);
  }

  /**
   * Tests the current user with argument default.
   */
  public function testPluginArgumentDefaultCurrentUser(): void {
    // Create a user to test.
    $account = $this->createUser();

    // Switch the user.
    $this->container->get('account_switcher')->switchTo($account);

    $view = Views::getView('test_plugin_argument_default_current_user');
    $view->initHandlers();

    $this->assertEquals($account->id(), $view->argument['null']->getDefaultArgument(), 'Uid of the current user is used.');
  }

}
