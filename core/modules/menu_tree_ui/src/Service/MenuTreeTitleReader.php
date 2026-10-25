<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Reads the titles of content menu links that decide their sort order.
 *
 * On a multilingual site a content link's title depends on the current content
 * language. The titles are read from the entity data table in one query per
 * 500 links, without loading entities.
 *
 * @internal
 */
final class MenuTreeTitleReader {

  private const CHUNK_SIZE = 500;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Returns the sort titles of content menu links.
   *
   * @param array<string, array<string, mixed>> $definitions
   *   Content link definitions, keyed by plugin ID.
   *
   * @return array<string, string>
   *   Plugin ID => the title the link sorts by.
   */
  public function contentLinkTitles(array $definitions): array {
    $titles = [];
    foreach ($definitions as $plugin_id => $definition) {
      $titles[(string) $plugin_id] = (string) ($definition['title'] ?? '');
    }
    if ($definitions === [] || !$this->languageManager->isMultilingual()) {
      return $titles;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    // Without a SQL storage there are no tables to read, so the titles stay
    // those of the definitions.
    if (!$storage instanceof SqlEntityStorageInterface) {
      return $titles;
    }
    $mapping = $storage->getTableMapping();
    $table = $mapping->getDataTable();
    if ($table === NULL) {
      return $titles;
    }
    $id_column = $mapping->getColumnNames('id')['value'];
    $langcode_column = $mapping->getColumnNames('langcode')['value'];
    $default_column = $mapping->getColumnNames('default_langcode')['value'];
    $title_column = $mapping->getColumnNames('title')['value'];

    $plugin_ids = [];
    foreach ($definitions as $plugin_id => $definition) {
      $entity_id = $definition['metadata']['entity_id'] ?? NULL;
      if ($entity_id !== NULL && $entity_id !== '') {
        $plugin_ids[(string) $entity_id] = (string) $plugin_id;
      }
    }

    $current = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    foreach (array_chunk(array_keys($plugin_ids), self::CHUNK_SIZE) as $chunk) {
      $rows = $this->database->select($table, 't')
        ->fields('t', [$id_column, $langcode_column, $default_column, $title_column])
        ->condition('t.' . $id_column, $chunk, 'IN')
        ->execute();
      $translations = [];
      foreach ($rows as $row) {
        $entity_id = (string) $row->{$id_column};
        $translations[$entity_id]['titles'][$row->{$langcode_column}] = (string) $row->{$title_column};
        if ((int) $row->{$default_column} === 1) {
          $translations[$entity_id]['default'] = $row->{$langcode_column};
        }
      }
      foreach ($translations as $entity_id => $link) {
        if (isset($link['default'])) {
          $titles[$plugin_ids[$entity_id]] = $this->pickTitle($link['titles'], $link['default'], $current);
        }
      }
    }
    return $titles;
  }

  /**
   * Picks the title the way the entity repository picks a translation.
   *
   * @param array<string, string> $titles
   *   The link's titles, keyed by language code.
   * @param string $default_langcode
   *   The language code of the default translation.
   * @param string $current
   *   The current content language code.
   */
  private function pickTitle(array $titles, string $default_langcode, string $current): string {
    if (count($titles) === 1 || $default_langcode === $current) {
      return $titles[$default_langcode];
    }
    $candidates = $this->languageManager->getFallbackCandidates([
      'langcode' => $current,
      'operation' => 'menu_tree_ui_sort',
    ]);
    $candidates[$default_langcode] = LanguageInterface::LANGCODE_DEFAULT;
    foreach ($candidates as $candidate) {
      if ($candidate === LanguageInterface::LANGCODE_DEFAULT) {
        return $titles[$default_langcode];
      }
      if (isset($titles[$candidate])) {
        return $titles[$candidate];
      }
    }
    return $titles[$default_langcode];
  }

}
