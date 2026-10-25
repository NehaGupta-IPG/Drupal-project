<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to update a block.
 *
 * @internal
 *   Form classes are internal.
 */
class UpdateBlockForm extends ConfigureBlockFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_update_block';
  }

  /**
   * Builds the block form.
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
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->blockUpdateHighlightId($uuid);
    return $this->doBuildForm($form, $form_state, $section_storage, $delta, $component);
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel() {
    return $this->t('Update');
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage): AjaxResponse {
    $response = $this->updateRebuildBlock($section_storage, $this->delta, $this->uuid);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

  /**
   * Builds the block and returns it.
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
  protected function updateRebuildBlock(SectionStorageInterface $section_storage, $delta, $uuid): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("[data-layout-block-uuid=$this->uuid]", $this->getBlockBuild($section_storage, $delta, $uuid)));
    return $response;
  }

}
