<?php

declare(strict_types=1);

namespace Drupal\media\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\FileSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to delete media items.
 */
class MediaDeleteForm extends ContentEntityDeleteForm {

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
   * Gets the file for the given media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media.
   *
   * @return \Drupal\file\FileInterface|null
   *   File.
   */
  protected function getFile(MediaInterface $media) : ?FileInterface {
    $source = $media->getSource();
    return $source instanceof FileSourceInterface ? $source->getFile($media) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $media = $this->getEntity();
    assert($media instanceof MediaInterface);
    $file = $this->getFile($media);

    if ($file && $this->config('media.settings')->get('delete_files')) {
      $current_user = $this->currentUser();
      $access = $file->access('delete', $current_user);
      $usages = $this->usage->countUsage($file);

      $messenger = $this->messenger();
      if ($usages === 1 && $access) {
        $file->delete();
      }
      else {
        // Explain why the file wasn't deleted. Could have been an access thing,
        // a usage thing, or both.
        if (!$access) {
          $messenger->addStatus('The file attached to the media item was retained because you do not have permission to delete it.');
        }
        if ($usages > 1) {
          $messenger->addStatus($this->formatPlural(
            $usages - 1,
            'The file attached to the media item was retained because it is used in one other place.',
            'The file attached to this media item was retained because it is used in @count other places.'
          ));
        }
      }
    }
  }

}
