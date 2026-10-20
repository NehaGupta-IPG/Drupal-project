<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Unit;

use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Language\LanguageDefault;
use Drupal\locale\PluralFormulaInterface;
use Drupal\locale\TranslationManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\locale\TranslationManager.
 */
#[CoversClass(TranslationManager::class)]
#[Group('locale')]
class TranslationManagerTest extends UnitTestCase {

  /**
   * Provides plural formulae, counts, variants and the selected variants.
   *
   * Drupal.formatPlural() selects the same variants, see
   * \Drupal\Tests\locale\FunctionalJavascript\LocalePluralFormatTest.
   *
   * @return array
   *   A plural formula or NULL, a count, the variants and the selected variant.
   */
  public static function providerSelectPluralForm(): array {
    $polish = 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';
    return [
      'formula for 1' => ['nplurals=2; plural=(n != 0);', 1, ['a', 'b'], 'b'],
      'count above 199' => [$polish, 202, ['a', 'b', 'c'], 'b'],
      'missing variant' => [$polish, 5, ['a', 'b'], 'b'],
      'single variant' => ['nplurals=2; plural=(n != 1);', 2, ['a'], 'a'],
      'empty variant' => ['nplurals=2; plural=(n != 1);', 2, ['a', ''], 'a'],
      'fraction' => ['nplurals=2; plural=(n > 1);', 1.5, ['a', 'b'], 'a'],
      'no formula' => [NULL, 1, ['a', 'b'], 'a'],
      'no formula, fraction' => [NULL, 1.5, ['a', 'b'], 'b'],
    ];
  }

  /**
   * Tests the selected plural variant.
   */
  #[DataProvider('providerSelectPluralForm')]
  public function testSelectPluralForm(?string $formula, int|float $count, array $variants, string $expected): void {
    $plural_formula = $this->createStub(PluralFormulaInterface::class);
    $plural_formula->method('getFormula')->willReturn($formula ? (new PoHeader())->parsePluralForms($formula)[1] : FALSE);
    $translation_manager = new TranslationManager(new LanguageDefault(['id' => 'xx']), $plural_formula);
    $this->assertSame($expected, $translation_manager->selectPluralForm($count, implode(PoItem::DELIMITER, $variants), 'xx'));
  }

}
