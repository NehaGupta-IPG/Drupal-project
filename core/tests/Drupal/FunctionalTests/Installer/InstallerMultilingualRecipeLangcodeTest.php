<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a recipe applied during a non-English install resolves langcode correctly.
 *
 * The profile declares no modules of its own - it only applies a recipe,
 * the same fixture used by \Drupal\KernelTests\Core\Recipe\RecipeRunnerTest
 * and \Drupal\FunctionalTests\Core\Recipe\RecipeCommandTest. Recipes listed
 * by a profile are applied in the same install batch as, and after, the
 * profile's own modules - including 'system', whose installation is what
 * writes system.site:default_langcode from the language chosen for this
 * installation. This is the one thing those other two tests can't cover:
 * that the write has already happened by the time a profile-declared
 * recipe's own config installs.
 *
 * @see \Drupal\Core\Config\ConfigInstaller::createConfiguration()
 * @see _install_module_batch()
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerMultilingualRecipeLangcodeTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_multilingual_recipe_install';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $langcode = 'fr';

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // Place a custom local translation in the translations directory, so the
    // installer finds it locally instead of trying to reach the (in tests,
    // unreachable) translation server.
    mkdir($this->root . '/' . $this->siteDirectory . '/files/translations', 0777, TRUE);
    file_put_contents($this->root . '/' . $this->siteDirectory . '/files/translations/drupal-' . \Drupal::VERSION . '.fr.po', <<<PO
      msgid ""
      msgstr ""

      msgid "Save and continue"
      msgstr "Save and continue"
      PO);

    parent::setUpLanguage();
  }

  /**
   * Tests the recipe's config resolves langcode against the install language.
   */
  public function testInstalled(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);

    // Unlabelled recipe config must match the language chosen for this
    // installation, not be hardcoded to English.
    $this->assertSame('fr', $this->config('config_test.dynamic.recipe_default')->get('langcode'));

    // The recipe's configuration shipped in german should still be german.
    $this->assertSame('de', $this->config('config_test.dynamic.recipe_german')->get('langcode'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('recipe_german'));
  }

}
