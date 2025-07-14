<?php

namespace Drupal\media\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\FileSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for a media delete multiple form.
 */
class MediaDeleteMultipleForm extends DeleteMultipleForm {

  /**
   * File usage resolver.
   */
  protected FileUsageInterface $usage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->usage = $container->get(FileUsageInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL): array {
    $form = parent::buildForm($form, $form_state, $entity_type_id);
    $config = $this->config('media.settings');

    $form['also_delete_file'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('delete_file_default'),
      '#title' => $this->t('Also delete the associated files?'),
      '#description' => $this->t('After deleting the media items, this will also remove the associated files from the file system.'),
      '#access' => !$config->get('disable_delete_control'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file_deleted_count = 0;
    $file_inaccessible_count = 0;
    $file_in_use_count = 0;
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $entities = $storage->loadMultiple(array_keys($this->selection));

    parent::submitForm($form, $form_state);

    if ($entities && $form_state->getValue('also_delete_file')) {
      foreach ($entities as $entity) {
        if ($entity instanceof MediaInterface && $entity->access('delete', $this->currentUser())) {
          $source = $entity->getSource();
          $file = $source instanceof FileSourceInterface ? $source->getFile($entity) : NULL;
          if (empty($file)) {
            continue;
          }
          if (!$file->access('delete', $this->currentUser())) {
            $file_inaccessible_count++;
            continue;
          }

          $usages = $this->usage->countUsage($file);
          if ($usages > 0) {
            $file_in_use_count++;
            continue;
          }

          $file->delete();
          $this->logger('media')->notice('Deleted the associated file %name.', [
            '%name' => $file->getFilename(),
          ]);
          $file_deleted_count++;
        }
      }

      if ($file_deleted_count) {
        $this->messenger->addStatus($this->formatPlural($file_deleted_count, 'Deleted @count associated file.', 'Deleted @count associated files.'));
      }
      if ($file_inaccessible_count) {
        $this->messenger->addWarning($this->formatPlural($file_inaccessible_count, 'Could not delete @count associated file because of insufficient privilege.', 'Could not delete @count associated files because of insufficient privilege.'));
      }
      if ($file_in_use_count) {
        $this->messenger->addWarning($this->formatPlural($file_in_use_count, 'Could not delete @count associated file because it is used in other places.', 'Could not delete @count associated files because they are used in other places.'));
      }
    }
  }

}
