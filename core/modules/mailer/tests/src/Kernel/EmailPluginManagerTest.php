<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\Mailer\EmailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mailer_email_test\EmailTestController;

/**
 * Tests the email plugin manager.
 *
 * @group mailer
 * @coversDefaultClass \Drupal\Core\Mailer\EmailPluginManager
 */
class EmailPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * @covers ::getEmailDefinition
   */
  public function testGetEmailDefinition(): void {
    $pluginManager = \Drupal::service(EmailManagerInterface::class);
    assert($pluginManager instanceof EmailManagerInterface);

    $definition = $pluginManager->getEmailDefinition('mailer_email_test.minimal');
    $this->assertSame('mailer_email_test.minimal', $definition->id());
    $this->assertInstanceOf(TranslatableMarkup::class, $definition->getLabel());
    $this->assertSame('Email without parameters', (string) $definition->getLabel());
    $this->assertSame('mailer_email_test', $definition->getModule());
    $this->assertSame('minimal', $definition->getKey());
    $this->assertSame(EmailTestController::class . '::minimal', $definition->getDefaults()[EmailDefinition::CONTROLLER_NAME]);

    $definition = $pluginManager->getEmailDefinition('mailer_email_test.scalars');
    $this->assertSame('mailer_email_test.scalars', $definition->id());
    $this->assertInstanceOf(TranslatableMarkup::class, $definition->getLabel());
    $this->assertSame('Email with scalars', (string) $definition->getLabel());
    $this->assertSame('mailer_email_test', $definition->getModule());
    $this->assertSame('scalars', $definition->getKey());
    $this->assertSame(EmailTestController::class . '::scalars', $definition->getDefaults()[EmailDefinition::CONTROLLER_NAME]);
  }

  /**
   * @covers ::getEmailDefinition
   */
  public function testHookEmailInfoAlter(): void {
    $state = \Drupal::service(StateInterface::class);
    assert($state instanceof StateInterface);
    $state->set('email_info_alter.remove_controller', TRUE);

    $pluginManager = \Drupal::service(EmailManagerInterface::class);
    assert($pluginManager instanceof EmailManagerInterface);
    $definition = $pluginManager->getEmailDefinition('mailer_email_test.scalars');
    $this->assertEquals([], $definition->getDefaults());
  }

}
