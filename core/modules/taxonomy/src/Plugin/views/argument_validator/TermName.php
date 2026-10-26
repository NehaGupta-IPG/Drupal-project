<?php

namespace Drupal\taxonomy\Plugin\views\argument_validator;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentValidator;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Validates whether a term name is a valid term argument.
 */
#[ViewsArgumentValidator(
  id: 'taxonomy_term_name',
  title: new TranslatableMarkup('Taxonomy term name'),
  entity_type: 'taxonomy_term'
)]
class TermName extends Entity {

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info);
    // Not handling exploding term names.
    $this->multipleCapable = FALSE;
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['transform'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['transform'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transform dashes in URL to spaces in term name filter values'),
      '#description' => $this->t('If there are terms with spaces and terms with dashes, like "co worker" and "co-worker", then only the last created term will be matched.'),
      '#default_value' => $this->options['transform'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    $query = $this->termStorage->getQuery();
    $query->sort('tid');

    if ($this->options['transform']) {
      // If there is a term "co-worker", no matches will be found when the dash
      // will be removed. Try to load both terms with spaces and terms with
      // dashes if any exist.
      $argument_pattern = str_replace('-', '[- ]', $argument);
      $query->condition('name', $argument_pattern, 'REGEXP');
    }
    else {
      $query->condition('name', $argument);
    }
    $query->accessCheck(FALSE);

    // If bundles is set then restrict the loaded terms to the given bundles.
    if (!empty($this->options['bundles'])) {
      $query->condition('vid', $this->options['bundles'], 'IN');
    }
    $terms_ids = $query->execute();

    $terms = $terms_ids ? $this->termStorage->loadMultiple($terms_ids) : [];

    // If multiple terms match, set the name of the latest term as an argument.
    if ($this->options['transform']) {
      $this->argument->argument = end($terms)->label();
    }

    // $terms are already bundle tested but we need to test access control.
    foreach ($terms as $term) {
      if ($this->validateEntity($term)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
