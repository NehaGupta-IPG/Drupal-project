<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel\Views;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;

/**
 * Defines the base class for all Language handler tests.
 */
abstract class LanguageTestBase extends ViewsKernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();
    $this->installConfig(['language']);

    // Create another language beside English.
    ConfigurableLanguage::create(['id' => 'xx-lolspeak', 'label' => 'Lolspeak'])->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function schemaDefinition(): Schema {
    $parentSchema = parent::schemaDefinition();
    $table = $parentSchema->getTableDefinition('views_test_data');
    $columns = $table->columns;
    $columns[] = Column::varchar(
      name: 'langcode',
      description: 'The {language}.langcode of this beatle.',
      length: 12,
      default: new StringValue(''),
    );

    return new Schema(
      type: $parentSchema->type,
      name: $parentSchema->name,
      tables: [
        new Table(
          name: $table->name,
          description: $table->description,
          columns: $columns,
          primaryKey: $table->primaryKey,
          uniqueKeys: $table->uniqueKeys,
          indexes: $table->indexes,
        ),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['langcode'] = [
      'title' => $this->t('Langcode'),
      'help' => $this->t('Langcode'),
      'field' => [
        'id' => 'language',
      ],
      'argument' => [
        'id' => 'language',
      ],
      'filter' => [
        'id' => 'language',
      ],
    ];

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function dataSet() {
    $data = parent::dataSet();
    $data[0]['langcode'] = 'en';
    $data[1]['langcode'] = 'xx-lolspeak';
    $data[2]['langcode'] = '';
    $data[3]['langcode'] = '';
    $data[4]['langcode'] = '';

    return $data;
  }

}
