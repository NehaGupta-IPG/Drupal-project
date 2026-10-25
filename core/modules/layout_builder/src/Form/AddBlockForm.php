<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to add a block.
 *
 * @internal
 *   Form classes are internal.
 */
class AddBlockForm extends ConfigureBlockFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_add_block';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel() {
    return $this->t('Add block');
  }

  /**
   * Builds the form for the block.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string|null $plugin_id
   *   The plugin ID of the block to add.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $plugin_id = NULL) {
    // Only generate a new component once per form submission.
    if (!$component = $form_state->get('layout_builder__component')) {
      $component = new SectionComponent($this->uuidGenerator->generate(), $region, ['id' => $plugin_id]);
      $section_storage->getSection($delta)->appendComponent($component);
      $form_state->set('layout_builder__component', $component);
    }
    $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->blockAddHighlightId($delta, $region);
    return $this->doBuildForm($form, $form_state, $section_storage, $delta, $component);
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage): AjaxResponse {
    $response = $this->addRebuildBlock($section_storage, $this->delta, $this->uuid);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

  /**
   * Builds the block and creates a new 'Add block' link.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param $delta
   *   The section delta.
   * @param $uuid
   *   The block UUID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  protected function addRebuildBlock(SectionStorageInterface $section_storage, $delta, $uuid): AjaxResponse {
    $response = new AjaxResponse();
    $region = $this->getCurrentComponent()->getRegion();
    $response->addCommand(new BeforeCommand("[data-layout-delta=$delta] [data-region=$region] .layout-builder__add-block", $this->getBlockBuild($section_storage, $delta, $uuid)));
    return $response;
  }

}
