<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests English configuration on a non-English default language site.
 *
 * This is the scenario that made \Drupal\Core\Config\Config::save()'s old
 * default-langcode logic wrong: it could not tell a deliberately English
 * config entity apart from one that merely had not had a langcode resolved
 * yet, so it silently rewrote 'en' to the site default on every save.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigEntityLangcodePreservationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language', 'config_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('system.site')->set('default_langcode', 'fr')->save();
  }

  /**
   * Tests an explicitly English entity stays English on save and resave.
   */
  public function testExplicitEnglishConfigEntitySurvives(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('config_test');
    $entity = $storage->create([
      'id' => 'english_only',
      'label' => 'English only',
      'langcode' => 'en',
    ]);
    $entity->save();
    $this->assertSame('en', $entity->language()->getId());

    // Reload from storage and save again with no changes: an ordinary save
    // must never relabel this to the site's configuration default.
    $storage->resetCache();
    /** @var \Drupal\config_test\Entity\ConfigTest $reloaded */
    $reloaded = $storage->load('english_only');
    $this->assertSame('en', $reloaded->language()->getId());
    $reloaded->set('label', 'English only, edited');
    $reloaded->save();
    $this->assertSame('en', $reloaded->language()->getId());
  }

  /**
   * Tests a French entity resolves to the site default when created freely.
   */
  public function testConfigEntityWithoutExplicitLangcodeGetsSiteDefault(): void {
    $entity = \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'no_explicit_langcode',
      'label' => 'No explicit langcode',
    ]);
    $entity->save();
    $this->assertSame('fr', $entity->language()->getId());
  }

}
