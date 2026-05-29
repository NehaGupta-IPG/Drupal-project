<?php

declare(strict_types=1);

namespace Drupal\taxonomy;

use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\PermissionProviderInterface;

/**
 * Provides dynamic permissions of the taxonomy module.
 *
 * @see taxonomy.permissions.yml
 */
class TaxonomyPermissions implements PermissionProviderInterface {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Get taxonomy permissions.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Call the getPermissions() method instead. See https://www.drupal.org/node/3421580', E_USER_DEPRECATED);
    return $this->getPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return $this->generatePermissions($this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple(), $this->buildPermissions(...));
  }

  /**
   * Builds a standard list of taxonomy term permissions for a given vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary.
   *
   * @return array
   *   An array of permission names and descriptions.
   */
  protected function buildPermissions(VocabularyInterface $vocabulary): array {
    $id = $vocabulary->id();
    $args = ['%vocabulary' => $vocabulary->label()];

    return [
      "create terms in $id" => ['title' => $this->t('%vocabulary: Create terms', $args)],
      "delete terms in $id" => ['title' => $this->t('%vocabulary: Delete terms', $args)],
      "edit terms in $id" => ['title' => $this->t('%vocabulary: Edit terms', $args)],
      "view term revisions in $id" => ['title' => $this->t('%vocabulary: View term revisions', $args)],
      "revert term revisions in $id" => [
        'title' => $this->t('%vocabulary: Revert term revisions', $args),
        'description' => $this->t('To revert a revision you also need permission to edit the taxonomy term.'),
      ],
      "delete term revisions in $id" => [
        'title' => $this->t('%vocabulary: Delete term revisions', $args),
        'description' => $this->t('To delete a revision you also need permission to delete the taxonomy term.'),
      ],
    ];
  }

}
