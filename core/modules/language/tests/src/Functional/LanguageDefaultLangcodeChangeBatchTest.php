<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuration language changes if the site default does.
 *
 * @see \Drupal\language\EventSubscriber\ConfigSubscriber::onConfigSave()
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class LanguageDefaultLangcodeChangeBatchTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that changing the default language via the form reconciles config.
   */
  public function testDefaultLanguageChangeViaForm(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->assertSame('en', $this->config('user.settings')->get('langcode'));

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');

    $this->assertSame('fr', $this->config('user.settings')->get('langcode'));
  }

}
