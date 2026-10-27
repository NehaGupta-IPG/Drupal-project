<?php

namespace Drupal\Core\Config\Command;

use Drupal\Component\Diff\Diff;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait for methods used across config commands.
 */
trait ConfigCommandTrait {

  /**
   * Prints the change list of a storage comparer as a table.
   *
   * @param \Drupal\Core\Config\StorageComparer $comparer
   *   The comparer holding the change list to render.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The input and output style.
   */
  protected function renderTable(StorageComparer $comparer, SymfonyStyle $io): void {
    $translations = [
      'delete' => (string) $this->t('Delete'),
      'update' => (string) $this->t('Update'),
      'create' => (string) $this->t('Create'),
      'rename' => (string) $this->t('Rename'),
    ];
    $styles = [
      'delete' => 'fg=white;bg=red',
      'update' => 'fg=black;bg=yellow',
      'create' => 'fg=white;bg=green',
      'rename' => 'fg=black;bg=cyan',
    ];
    $header = [
      (string) $this->t('Collection'),
      (string) $this->t('Config'),
      (string) $this->t('Operation'),
    ];

    $rows = [];
    foreach ($comparer->getAllCollectionNames() as $collection) {
      foreach ($comparer->getChangelist(NULL, $collection) as $op => $names) {
        foreach ($names as $name) {
          $rows[] = [
            $collection,
            $op !== 'rename' ? $name : str_replace('::', ' -> ', $name),
            \sprintf('<%s>%s</>', $styles[$op], $translations[$op]),
          ];
        }
      }
    }

    $io->table($header, $rows);
  }

  /**
   * Prints the change list of a storage comparer as a unified diff.
   *
   * The diff shows how the target storage changes to match the source storage.
   *
   * @param \Drupal\Core\Config\StorageComparer $comparer
   *   The comparer holding the change list to render.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output to write the diff to.
   * @param string $source_label
   *   The label of the source storage, used in the diff headers.
   * @param string $target_label
   *   The label of the target storage, used in the diff headers.
   */
  protected function renderDiff(StorageComparer $comparer, OutputInterface $output, string $source_label, string $target_label): void {
    $formatter = new ConsoleDiffFormatter();

    foreach ($comparer->getAllCollectionNames() as $collection) {
      $prefix = $collection === StorageInterface::DEFAULT_COLLECTION ? '' : $collection . '/';
      $source = $comparer->getSourceStorage($collection);
      $target = $comparer->getTargetStorage($collection);

      foreach ($comparer->getChangelist(NULL, $collection) as $op => $names) {
        foreach ($names as $name) {
          $source_name = $target_name = $name;
          if ($op === 'rename') {
            // The target storage has the old name, the source the new one.
            $renamed = $comparer->extractRenameNames($name);
            $source_name = $renamed['new_name'];
            $target_name = $renamed['old_name'];
          }

          // @see \Drupal\Core\Config\ConfigManager::diff()
          $before = $this->readLines($target, $target_name);
          $after = $this->readLines($source, $source_name);

          $output->writeln('<fg=red>--- ' . OutputFormatter::escape($before === NULL ? '/dev/null' : $target_label . '/' . $prefix . $target_name) . '</>');
          $output->writeln('<fg=green>+++ ' . OutputFormatter::escape($after === NULL ? '/dev/null' : $source_label . '/' . $prefix . $source_name) . '</>');
          $output->write($formatter->format(new Diff($before ?? [], $after ?? [])));
        }
      }
    }
  }

  /**
   * Reads a configuration object as the lines of its YAML representation.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage to read from.
   * @param string $name
   *   The configuration object name.
   *
   * @return string[]|null
   *   The lines, or NULL when the storage does not have the object.
   */
  private function readLines(StorageInterface $storage, string $name): ?array {
    $data = $storage->read($name);
    return $data === FALSE ? NULL : explode("\n", rtrim(Yaml::encode($data), "\n"));
  }

}
