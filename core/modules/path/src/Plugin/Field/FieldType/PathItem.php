<?php

namespace Drupal\path\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\path_alias\PathAliasInterface;

/**
 * Defines the 'path' entity field type.
 */
#[FieldType(
  id: "path",
  label: new TranslatableMarkup("Path"),
  description: new TranslatableMarkup("An entity field containing a path alias and related data."),
  default_widget: "path",
  no_ui: TRUE,
  list_class: PathFieldItemList::class,
  constraints: ["PathAlias" => []],
)]
class PathItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['alias'] = DataDefinition::create('string')
      ->setLabel(t('Path alias'));
    $properties['pid'] = DataDefinition::create('integer')
      ->setLabel(t('Path id'));
    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(t('Language Code'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $alias = $this->get('alias')->getValue();
    $pid = $this->get('pid')->getValue();
    $langcode = $this->get('langcode')->getValue();

    return ($alias === NULL || $alias === '') && ($pid === NULL || $pid === '') && ($langcode === NULL || $langcode === '');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $alias = $this->get('alias')->getValue();
    if ($alias !== NULL) {
      $this->set('alias', trim($alias));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $entity = $this->getEntity();
    $alias = $this->get('alias')->getValue();
    $pid = $this->get('pid')->getValue();

    $properties = [
      'path' => '/' . $entity->toUrl()->getInternalPath(),
      'alias' => $alias,
      'langcode' => $this->getLangcode(),
    ];

    if ($alias && !$pid) {
      // Try to load path alias from storage. In some cases, the
      // path alias could be created before this function runs. For example,
      // \Drupal\workspaces\EntityOperations::entityTranslationInsert will
      // create a translation, and an associated path alias will be created
      // with it.
      $query = $path_alias_storage->getQuery()->accessCheck(FALSE);
      foreach ($properties as $field => $value) {
        $query->condition($field, $value);
      }
      $ids = $query->execute();
      $pid = $ids ? reset($ids) : $pid;
    }

    // Load the path alias entity if the entity is being updated and its ID is
    // known.
    $path_alias = NULL;
    if ($pid) {
      $path_alias = $path_alias_storage->load($pid);
      assert($path_alias instanceof PathAliasInterface);
    }

    // Stop at this point if the alias hasn't been changed, even if it's a
    // fallback alias that can't be managed by this item.
    $existing_alias = $path_alias?->getAlias();
    if ($alias == $existing_alias) {
      return;
    }

    $has_own_alias = $update && $this->doesEntityHasOwnAlias($entity, $path_alias);

    // If we have an alias, we need to update or create a path alias entity.
    if ($alias && $has_own_alias) {
      $path_alias->setAlias($alias);
      $path_alias->save();
    }
    elseif ($alias && !$has_own_alias) {
      $path_alias = $path_alias_storage->create($properties);
      $path_alias->save();
      $this->set('pid', $path_alias->id());
    }
    elseif ($has_own_alias) {
      // Otherwise, delete the old alias if the user erased it.
      $path_alias = $path_alias_storage->load($pid);
      if ($entity->isDefaultRevision()) {
        $path_alias_storage->delete([$path_alias]);
      }
      else {
        $path_alias_storage->deleteRevision($path_alias->getRevisionID());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['alias'] = '/' . str_replace(' ', '-', strtolower($random->sentences(3)));
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'alias';
  }

  /**
   * Return TRUE if the given entity has own path alias, otherwise - FALSE.
   */
  public function doesEntityHasOwnAlias(FieldableEntityInterface $entity, ?PathAliasInterface $path_alias): bool {
    if (empty($path_alias)) {
      return FALSE;
    }

    $is_multilingual = FALSE;
    if ($entity instanceof TranslatableInterface) {
      $is_multilingual = count($entity->getTranslationLanguages()) > 1;
    }

    if (!$is_multilingual) {
      return TRUE;
    }

    // On multilingual entities, the alias can only be managed if it has the
    // same language as the item. Otherwise, the editor would be able to
    // change the fallback alias by editing an entity translation.
    return $path_alias->language()->getId() === $this->getLangcode();
  }

}
