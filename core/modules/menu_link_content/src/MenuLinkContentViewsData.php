<?php

namespace Drupal\menu_link_content;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the Custom menu link entity type.
 */
class MenuLinkContentViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // The parent field allows NULL, so specify that for views.
    $data['menu_link_content_data']['parent']['filter']['allow empty'] = TRUE;

    return $data;
  }

}
