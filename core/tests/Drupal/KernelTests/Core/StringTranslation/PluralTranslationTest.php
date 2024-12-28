<?php

namespace Drupal\KernelTests\Core\StringTranslation;

use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoItem;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the PluralTranslatableMarkup translation.
 *
 * @group StringTranslation
 */
class PluralTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', [
      'locales_location',
      'locales_source',
      'locales_target',
    ]);
    ConfigurableLanguage::createFromLangcode('ru')->save();
    $formula = 'nplurals=3; plural=((((n%10)==1)&&((n%100)!=11))?(0):(((((n%10)>=2)&&((n%10)<=4))&&(((n%100)<10)||((n%100)>=20)))?(1):2));';
    $header = new PoHeader();
    [$nplurals, $formula] = $header->parsePluralForms($formula);
    \Drupal::service('locale.plural.formula')->setPluralFormula('ru', $nplurals, $formula);
  }

  /**
   * Tests that PluralTranslatableMarkup objects translates properly.
   */
  public function testPluralWithCount() {
    /** @var \Drupal\locale\StringStorageInterface $storage */
    $storage = \Drupal::service('locale.storage');
    $source = $storage->createString([
      'source' => implode(PoItem::DELIMITER, ['1 day', '@count days']),
    ])->save();
    $translation = $storage->createTranslation([
      'lid' => $source->lid,
      'language' => 'ru',
      'translation' => implode(PoItem::DELIMITER, [
        '@count день',
        '@count дня',
        '@count[2] дней',
      ]),
    ])->save();
    $string = new PluralTranslatableMarkup(21, '1 day', '@count days', [], ['langcode' => 'ru']);
    $this->assertEquals('21 день', $string);
    $translation->setString(implode(PoItem::DELIMITER, [
      '@count день',
      '@count[2] дней',
    ]))->save();
    \Drupal::translation()->reset();
    $string = new PluralTranslatableMarkup(21, '1 day', '@count days', [], ['langcode' => 'ru']);
    $this->assertEquals('21 день', $string);
  }

}
