<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\FunctionalJavascript;

use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoItem;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\locale\Unit\TranslationManagerTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that Drupal.formatPlural() selects the same variants as PHP.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocalePluralFormatTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['locale'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the selected plural variants.
   */
  public function testFormatPlural(): void {
    ConfigurableLanguage::createFromLangcode('hu')->save();
    // A string translation for a JavaScript translation file on the page.
    $this->writeSettings([
      'settings' => [
        'locale_custom_strings_hu' => (object) ['value' => ['' => ['a' => 'a']], 'required' => TRUE],
      ],
    ]);
    $this->drupalLogin($this->drupalCreateUser(['translate interface']));
    $this->drupalGet('hu/admin/config/regional/translate');
    foreach (TranslationManagerTest::providerSelectPluralForm() as $case => [$formula, $count, $variants, $expected]) {
      $table = $formula ? (new PoHeader())->parsePluralForms($formula)[1] : NULL;
      $result = $this->getSession()->evaluateScript('(() => {
        drupalTranslations.pluralFormula = ' . json_encode($table) . ';
        drupalTranslations.strings[""]["a\u0003b"] = ' . json_encode(implode(PoItem::DELIMITER, $variants)) . ';
        return [Drupal.formatPlural(' . $count . ', "a", "b"), Drupal.formatPlural("' . $count . '", "a", "b")];
      })()');
      $this->assertSame([$expected, $expected], $result, $case);
    }
  }

}
