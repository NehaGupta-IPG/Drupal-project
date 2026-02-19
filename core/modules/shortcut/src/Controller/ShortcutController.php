<?php

namespace Drupal\shortcut\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\shortcut\ShortcutInterface;
use Drupal\shortcut\ShortcutSetInterface;

/**
 * Provides route responses for taxonomy.module.
 */
class ShortcutController extends ControllerBase {

  /**
   * Returns a form to add a new shortcut to a given set.
   *
   * @param ShortcutSetInterface $shortcut_set
   *   The shortcut set this shortcut will be added to.
   *
   * @return array
   *   The shortcut add form.
   */
  public function addForm(ShortcutSetInterface $shortcut_set) {
    $shortcut = $this->entityTypeManager()->getStorage('shortcut')->create(['shortcut_set' => $shortcut_set->id()]);
    return $this->entityFormBuilder()->getForm($shortcut, 'add');
  }

  /**
   * Deletes the selected shortcut.
   *
   * @param ShortcutInterface $shortcut
   *   The shortcut to delete.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the previous location or the front page when destination
   *   is not set.
   */
  public function deleteShortcutLinkInline(ShortcutInterface $shortcut) {
    $label = $shortcut->label();

    try {
      $shortcut->delete();
      $this->messenger()->addStatus($this->t('The shortcut %title has been deleted.', ['%title' => $label]));
    }
    catch (\Exception) {
      $this->messenger()->addStatus($this->t('Unable to delete the shortcut for %title.', ['%title' => $label]), 'error');
    }

    return $this->redirect('<front>');
  }

}
