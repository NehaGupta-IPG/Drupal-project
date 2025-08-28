<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\MailTheme;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\MailTheme\MailTemplateId;
use Drupal\Core\MailTheme\MailThemeManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mail theme manager functionality.
 *
 * @group MailTheme
 * @coversDefaultClass \Drupal\Core\MailTheme\MailThemeManager
 */
class MailThemeManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mail_theme_test',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Default mail theme negotiator relies on System module's system.theme
    // configuration.
    $this->installConfig(['system']);

    // Install themes required for tests and update the default theme.
    $this->container->get(ThemeInstallerInterface::class)->install([
      'stark',
      'test_theme',
    ]);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Tests that executeInMailTheme() uses the default theme.
   *
   * @covers ::executeInMailTheme
   */
  public function testDefaultConfig(): void {
    /** @var \Drupal\Core\Theme\ThemeManagerInterface $themeManager */
    $themeManager = $this->container->get(ThemeManagerInterface::class);
    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());

    /** @var \Drupal\Core\MailTheme\MailThemeManagerInterface $mailThemeManager */
    $mailThemeManager = $this->container->get(MailThemeManagerInterface::class);
    $templateId = new MailTemplateId('update_status', 'notify');
    $result = $mailThemeManager->executeInMailTheme($templateId, function () use ($themeManager) {
      $this->assertSame('stark', $themeManager->getActiveTheme()->getName());
      return 'There is a security update available for your version of Drupal.';
    });
    $this->assertSame($result, 'There is a security update available for your version of Drupal.');

    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());
  }

  /**
   * Tests that executeInMailTheme() switches to custom theme and back.
   *
   * @covers ::executeInMailTheme
   */
  public function testCustomMailTheme(): void {
    /** @var \Drupal\Core\Theme\ThemeManagerInterface $themeManager */
    $themeManager = $this->container->get(ThemeManagerInterface::class);
    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());

    /** @var \Drupal\Core\MailTheme\MailThemeManagerInterface $mailThemeManager */
    $mailThemeManager = $this->container->get(MailThemeManagerInterface::class);
    $templateId = new MailTemplateId('mail_theme_test', 'trigger');
    $result = $mailThemeManager->executeInMailTheme($templateId, function () use ($themeManager) {
      $this->assertSame('test_theme', $themeManager->getActiveTheme()->getName());
      return TRUE;
    });
    $this->assertTrue($result);

    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());
  }

  /**
   * Tests that executeInMailTheme() switches theme back when an exception is thrown.
   *
   * @covers ::executeInMailTheme
   */
  public function testExceptionInMailTheme(): void {
    /** @var \Drupal\Core\Theme\ThemeManagerInterface $themeManager */
    $themeManager = $this->container->get(ThemeManagerInterface::class);
    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());

    /** @var \Drupal\Core\MailTheme\MailThemeManagerInterface $mailThemeManager */
    $mailThemeManager = $this->container->get(MailThemeManagerInterface::class);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to render component');
    $templateId = new MailTemplateId('mail_theme_test', 'trigger');
    $mailThemeManager->executeInMailTheme($templateId, function () use ($themeManager) {
      $this->assertSame('test_theme', $themeManager->getActiveTheme()->getName());
      throw new \RuntimeException('Unable to render component');
    });

    $this->assertSame('stark', $themeManager->getActiveTheme()->getName());
  }

}
