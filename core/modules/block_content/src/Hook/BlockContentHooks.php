<?php

namespace Drupal\block_content\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Hook implementations for block_content.
 */
class BlockContentHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.block_content':
        /** @var \Drupal\user\PermissionHandlerInterface $permissionHandler */
        $permissionHandler = \Drupal::service(PermissionHandlerInterface::class);
        $permissions = $permissionHandler->getPermissions();

        $field_ui = \Drupal::moduleHandler()->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Block Content module allows you to create and manage custom <em>block types</em> and <em>content-containing blocks</em>. For more information, see the <a href=":online-help">online documentation for the Block Content module</a>.', [':online-help' => 'https://www.drupal.org/documentation/modules/block_content']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Creating and managing block types') . '</dt>';
        $output .= '<dd>' . $this->t('Users with the <em>@permission</em> permission can create and edit block types with fields and display settings, from the <a href=":types">Block types</a> page under the Structure menu. For more information about managing fields and display settings, see the <a href=":field-ui">Field UI module help</a> and <a href=":field">Field module help</a>.', [
          '@permission' => $permissions['administer block types']['title'],
          ':types' => Url::fromRoute('entity.block_content_type.collection')->toString(),
          ':field-ui' => $field_ui,
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Creating content blocks') . '</dt>';
        $output .= '<dd>' . $this->t('Users with both <em>@permission1</em> and <em>@permission2</em> permission can create, edit, and delete content blocks of each defined block type, from the <a href=":block-library">Content blocks page</a>.', [
          '@permission1' => $permissions['access block library']['title'],
          '@permission2' => $permissions['administer block content']['title'],
          ':block-library' => Url::fromRoute('entity.block_content.collection')->toString(),
        ]) . '</dd>';
        try {
          $blockAdminDisplay = Url::fromRoute('block.admin_display')->toString();
          $output .= '<dd>' . $this->t('After creating a block, place it in a region from the <a href=":blocks">Block layout page</a>, just like blocks provided by other modules.', [
            ':blocks' => $blockAdminDisplay,
          ]) . '</dd>';
        }
        catch (RouteNotFoundException) {
          $output .= '<dd>' . $this->t('When <em>Block layout</em> from <em>Block</em> module is available, Content Blocks may be placed in regions of a theme.') . '</dd>';
        }
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) : array {
    return [
      'block_content_add_list' => [
        'variables' => [
          'content' => NULL,
        ],
        'file' => 'block_content.pages.inc',
      ],
    ];
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) : void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    // Add a translation handler for fields if the language module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('language')) {
      $translation = $entity_types['block_content']->get('translation');
      $translation['block_content'] = TRUE;
      $entity_types['block_content']->set('translation', $translation);
    }
    // Swap out the default EntityChanged constraint with a custom one with
    // different logic for inline blocks.
    $constraints = $entity_types['block_content']->getConstraints();
    unset($constraints['EntityChanged']);
    $constraints['BlockContentEntityChanged'] = NULL;
    $entity_types['block_content']->setConstraints($constraints);
  }

  /**
   * Implements hook_query_TAG_alter().
   *
   * Alters any 'entity_reference' query where the entity type is
   * 'block_content' and the query has the tag 'block_content_access'.
   *
   * These queries should only return reusable blocks unless a condition on
   * 'reusable' is explicitly set.
   *
   * Block_content entities that are not reusable should by default not be
   * selectable as entity reference values. A module can still create an
   * instance of \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   * that will allow selection of non-reusable blocks by explicitly setting a
   * condition on the 'reusable' field.
   *
   * @see \Drupal\block_content\BlockContentAccessControlHandler
   */
  #[Hook('query_entity_reference_alter')]
  public function queryEntityReferenceAlter(AlterableInterface $query): void {
    if ($query instanceof SelectInterface && $query->getMetaData('entity_type') === 'block_content' && $query->hasTag('block_content_access')) {
      $data_table = \Drupal::entityTypeManager()->getDefinition('block_content')->getDataTable();
      if (array_key_exists($data_table, $query->getTables()) && !_block_content_has_reusable_condition($query->conditions(), $query->getTables())) {
        $query->condition("{$data_table}.reusable", TRUE);
      }
    }
  }

}
