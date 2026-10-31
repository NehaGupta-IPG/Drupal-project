<?php

namespace Drupal\taxonomy\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Hook implementations for taxonomy.
 */
class TaxonomyHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_help().
   *
   * @param string $route_name
   *   Current route name.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Current route match.
   *
   * @return string|\Stringable|array|null
   *   Resulting help text.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string|\Stringable|array|null {
    switch ($route_name) {
      case 'help.page.taxonomy':
        $field_ui_url = $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Taxonomy module allows users who have permission to create and edit content to categorize (tag) content of that type. Users who have the <em>Administer vocabularies and terms</em> <a href=":permissions" title="Taxonomy module permissions">permission</a> can add <em>vocabularies</em> that contain a set of related <em>terms</em>. The terms in a vocabulary can either be pre-set by an administrator or built gradually as content is added and edited. Terms may be organized hierarchically if desired.', [
          ':permissions' => Url::fromRoute('user.admin_permissions.module', [
            'modules' => 'taxonomy',
          ])->toString(),
        ]) . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":taxonomy">online documentation for the Taxonomy module</a>.', [':taxonomy' => 'https://www.drupal.org/docs/8/core/modules/taxonomy']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing vocabularies') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer vocabularies and terms</em> permission can add and edit vocabularies from the <a href=":taxonomy_admin">Taxonomy administration page</a>. Vocabularies can be deleted from their <em>Edit vocabulary</em> page. Users with the <em>Taxonomy term: Administer fields</em> permission may add additional fields for terms in that vocabulary using the <a href=":field_ui">Field UI module</a>.', [
          ':taxonomy_admin' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
          ':field_ui' => $field_ui_url,
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Managing terms') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer vocabularies and terms</em> permission or the <em>Edit terms</em> permission for a particular vocabulary can add, edit, and organize the terms in a vocabulary from a vocabulary\'s term listing page, which can be accessed by going to the <a href=":taxonomy_admin">Taxonomy administration page</a> and clicking <em>List terms</em> in the <em>Operations</em> column. Users must have the <em>Administer vocabularies and terms</em> permission or the <em>Delete terms</em> permission for a particular vocabulary to delete terms.', [
          ':taxonomy_admin' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
        ]) . ' </dd>';
        $output .= '<dt>' . $this->t('Classifying entity content') . '</dt>';
        $output .= '<dd>' . $this->t('A user with the <em>Administer fields</em> permission for a certain entity type may add <em>Taxonomy term</em> reference fields to the entity type, which will allow entities to be classified using taxonomy terms. See the <a href=":entity_reference">Entity Reference help</a> for more information about reference fields. See the <a href=":field">Field module help</a> and the <a href=":field_ui">Field UI help</a> pages for general information on fields and how to create and manage them.', [
          ':field_ui' => $field_ui_url,
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':entity_reference' => Url::fromRoute('help.page', [
            'name' => 'entity_reference',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Adding new terms during content creation') . '</dt>';
        $output .= '<dd>' . $this->t("Allowing users to add new terms gradually builds a vocabulary as content is added and edited. Users can add new terms if either of the two <em>Autocomplete</em> widgets is chosen for the Taxonomy term reference field in the <em>Manage form display</em> page for the field. You will also need to enable the <em>Create referenced entities if they don't already exist</em> option, and restrict the field to one vocabulary.") . '</dd>';
        $output .= '<dt>' . $this->t('Configuring displays and form displays') . '</dt>';
        $output .= '<dd>' . $this->t('See the <a href=":entity_reference">Entity Reference help</a> page for the field widgets and formatters that can be configured for any reference field on the <em>Manage display</em> and <em>Manage form display</em> pages. Taxonomy additionally provides an <em>RSS category</em> formatter that displays nothing when the entity item is displayed as HTML, but displays an RSS category instead of a list when the entity item is displayed in an RSS feed.', [
          ':entity_reference' => Url::fromRoute('help.page', [
            'name' => 'entity_reference',
          ])->toString(),
        ]) . '</li>';
        $output .= '</ul>';
        $output .= '</dd>';
        $output .= '</dl>';
        return $output;

      case 'entity.taxonomy_vocabulary.collection':
        $output = '<p>' . $this->t('Taxonomy is for categorizing content. Terms are grouped into vocabularies. For example, a vocabulary called "Fruit" would contain the terms "Apple" and "Banana".') . '</p>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_local_tasks_alter().
   *
   * @todo Evaluate removing as part of https://www.drupal.org/node/2358923.
   *
   * @param mixed $local_tasks
   *   Local tasks to be altered.
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks): void {
    $local_task_key = 'config_translation.local_tasks:entity.taxonomy_vocabulary.config_translation_overview';
    if (isset($local_tasks[$local_task_key])) {
      // The config_translation module expects the base route to be
      // entity.taxonomy_vocabulary.edit_form like it is for other configuration
      // entities. Taxonomy uses the overview_form as the base route.
      $local_tasks[$local_task_key]['base_route'] = 'entity.taxonomy_vocabulary.overview_form';
    }
  }

  /**
   * Implements hook_entity_operation().
   *
   * @return array|array<string,array<string,mixed>>
   *   Resulting operations array.
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $term): array {
    $operations = [];
    if ($term instanceof Term && $term->access('create')) {
      $operations['add-child'] = [
        'title' => $this->t('Add child'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.taxonomy_term.add_form', [
          'taxonomy_vocabulary' => $term->bundle(),
        ], [
          'query' => [
            'parent' => $term->id(),
          ],
        ]),
      ];
    }
    return $operations;
  }

  /**
   * @defgroup taxonomy_index Taxonomy indexing
   * @{
   * Functions to maintain taxonomy indexing.
   *
   * Taxonomy uses default field storage to store canonical relationships
   * between terms and fieldable entities. However its most common use case
   * requires listing all content associated with a term or group of terms
   * sorted by creation date. To avoid slow queries due to joining across
   * multiple node and field tables with various conditions and order by
   * criteria, we maintain a denormalized table with all relationships between
   * terms, published nodes and common sort criteria such as status, sticky and
   * created. When using other field storage engines or alternative methods of
   * denormalizing this data you should set the
   * taxonomy.settings:maintain_index_table to '0' to avoid unnecessary writes
   * in SQL.
   */

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $node): void {
    // Add taxonomy index entries for the node.
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(RevisionableInterface $node): void {
    // If we're not dealing with the default revision of the node, do not make
    // any change to the taxonomy index.
    if (!$node->isDefaultRevision()) {
      return;
    }
    $this->deleteNodeIndex($node);
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for node entities.
   */
  #[Hook('node_predelete')]
  public function nodePredelete(EntityInterface $node): void {
    // Clean up the {taxonomy_index} table when nodes are deleted.
    $this->deleteNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for taxonomy_term entities.
   */
  #[Hook('taxonomy_term_delete')]
  public function taxonomyTermDelete(Term $term): void {
    if ($this->configFactory->get('taxonomy.settings')->get('maintain_index_table')) {
      // Clean up the {taxonomy_index} table when terms are deleted.
      $this->database->delete('taxonomy_index')->condition('tid', $term->id())->execute();
    }
  }

  // phpcs:ignore Drupal.Commenting.InlineComment.DocBlock
  /**
   * @addtogroup taxonomy_index
   * @{
   */

  /**
   * Builds and inserts taxonomy index entries for a given node.
   *
   * The index lists all terms that are related to a given node entity, and is
   * therefore maintained at the entity level.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   */
  protected function buildNodeIndex(NodeInterface $node): void {
    // We maintain a denormalized table of term/node relationships, containing
    // only data for current, published nodes.
    if (!\Drupal::config('taxonomy.settings')->get('maintain_index_table') || !(\Drupal::entityTypeManager()->getStorage('node') instanceof SqlContentEntityStorage)) {
      return;
    }

    $status = $node->isPublished();
    $sticky = (int) $node->isSticky();
    // We only maintain the taxonomy index for published nodes.
    if ($status && $node->isDefaultRevision()) {
      // Collect a unique list of all the term IDs from all node fields.
      $tid_all = [];
      $entity_reference_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
      foreach ($node->getFieldDefinitions() as $field) {
        $field_name = $field->getName();
        $class = $field->getItemDefinition()->getClass();
        $is_entity_reference_class = ($class === $entity_reference_class) || is_subclass_of($class, $entity_reference_class);
        if ($is_entity_reference_class && $field->getSetting('target_type') == 'taxonomy_term') {
          foreach ($node->getTranslationLanguages() as $language) {
            foreach ($node->getTranslation($language->getId())->$field_name as $item) {
              if (!$item->isEmpty()) {
                $tid_all[$item->target_id] = $item->target_id;
              }
            }
          }
        }
      }
      // Insert index entries for all the node's terms.
      if (!empty($tid_all)) {
        $connection = \Drupal::database();
        foreach ($tid_all as $tid) {
          $connection->merge('taxonomy_index')
            ->keys(['nid' => $node->id(), 'tid' => $tid, 'status' => $node->isPublished()])
            ->fields(['sticky' => $sticky, 'created' => $node->getCreatedTime()])
            ->execute();
        }
      }
    }
  }

  /**
   * Deletes taxonomy index entries for a given node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node entity.
   */
  protected function deleteNodeIndex(EntityInterface $node): void {
    if (\Drupal::config('taxonomy.settings')->get('maintain_index_table')) {
      \Drupal::database()->delete('taxonomy_index')->condition('nid', $node->id())->execute();
    }
  }

  // phpcs:ignore Drupal.Commenting.InlineComment.DocBlock
  /**
   * @} End of "addtogroup taxonomy_index".
   */

  /**
  * Implements hook_form_BASE_FORM_ID_alter().
  *
  * @param array $form
  *   Nested array of form elements that comprise the form.
  * @param \Drupal\Core\Form\FormStateInterface $form_state
  *   Current state of the form.
  * @param string $form_id
  *   String representation of form name.
  */
  #[Hook('form_taxonomy_term_form_alter')]
  public function taxonomyTermFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\taxonomy\TermStorageInterface $taxonomy_storage */
    $taxonomy_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    if (isset($form['parent'])) {
      $vocabulary_id = $form['vid']['#value'];
      $term_id = $form['tid']['#value'];
      $term = '';
      $parent = '';

      if (isset($term_id) && !empty($term_id)) {
        $term = $taxonomy_storage->load($term_id);
        $parent = array_keys($taxonomy_storage->loadParents($term_id));
      }

      // \Drupal\taxonomy\TermStorageInterface::loadTree() and
      // \Drupal\taxonomy\TermStorageInterface::loadParents() may contain large
      // numbers of items so we check for taxonomy.settings:override_selector
      // before loading the full vocabulary. Contrib modules can then intercept
      // before hook_form_alter to provide scalable alternatives.
      if (!$this->configFactory->get('taxonomy.settings')->get('override_selector')) {
        $exclude = [];
        if ($term instanceof TermInterface) {
          $parent = array_keys($taxonomy_storage->loadParents($term->id()));
          $children = $taxonomy_storage->loadTree($vocabulary_id, $term->id());

          // A term can't be the child of itself, nor of its children.
          foreach ($children as $child) {
            $exclude[] = $child->tid;
          }
          $exclude[] = $term->id();
        }

        $tree = $taxonomy_storage->loadTree($vocabulary_id);
        $options = ['<' . $this->t('root') . '>'];
        if (empty($parent)) {
          $parent = [0];
        }

        foreach ($tree as $item) {
          if (!in_array($item->tid, $exclude)) {
            $options[$item->tid] = str_repeat('-', $item->depth) . $item->name;
          }
        }

        $form['parent']['widget']['#options'] = $options;
      }
    }

  }

}
