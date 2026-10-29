<?php

namespace Drupal\locale\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for locale.
 */
class LocaleViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data): void {
    $data['locales_source']['table']['group'] = $this->t('Locale source');

    $data['locales_source']['table']['base'] = [
      'field' => 'lid',
      'title' => $this->t('Locale source'),
      'help' => $this->t('A source string for translation, in English or the default site language.'),
    ];

    $data['locales_source']['lid'] = [
      'title' => $this->t('LID'),
      'help' => $this->t('The ID of the source string.'),
      'field' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
        'numeric' => TRUE,
        'validate type' => 'lid',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_source']['source'] = [
      'title' => $this->t('Source'),
      'help' => $this->t('The full original string.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ];

    $data['locales_source']['context'] = [
      'title' => $this->t('Context'),
      'help' => $this->t('The context this string applies to.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_source']['version'] = [
      'title' => $this->t('Version'),
      'help' => $this->t('The release version of the project.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'locale_version',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_source']['edit_lid'] = [
      'field' => [
        'title' => $this->t('Edit link'),
        'help' => $this->t('Provide a simple link to edit the translations.'),
        'id' => 'locale_link_edit',
      ],
    ];

    $data['locales_target']['table']['group'] = $this->t('Locale target');

    $data['locales_target']['table']['join'] = [
      'locales_source' => [
        'left_field' => 'lid',
        'field' => 'lid',
      ],
    ];

    $data['locales_target']['translation'] = [
      'title' => $this->t('Translation'),
      'help' => $this->t('The full translation string.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_target']['language'] = [
      'title' => $this->t('Language'),
      'help' => $this->t('The language this translation is in.'),
      'field' => [
        'id' => 'language',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'language',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_target']['customized'] = [
      'title' => $this->t('Customized'),
      'help' => $this->t('Boolean indicating whether the translation is custom to this site.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'boolean',
      ],
      'argument' => [
        'id' => 'boolean',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['locales_location']['table']['group'] = $this->t('Locale location');

    $data['locales_location']['table']['join'] = [
      'locales_source' => [
        'left_field' => 'lid',
        'field' => 'lid',
      ],
      'locales_target' => [
        'left_field' => 'lid',
        'field' => 'lid',
      ],
    ];

    $data['locales_location']['type'] = [
      'title' => $this->t('Type'),
      'help' => $this->t('The location type (file, config, path, etc).'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_location']['name'] = [
      'title' => $this->t('Name'),
      'help' => $this->t('Type dependent location information (file name, path, etc).'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locales_location']['version'] = [
      'title' => $this->t('Version'),
      'help' => $this->t('Version of Drupal where the location was found.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'locale_version',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locale_file']['table']['group'] = $this->t('Locale group');

    $data['locale_file']['langcode'] = [
      'title' => $this->t('Langcode'),
      'help' => $this->t('The language the file is for.'),
      'field' => [
        'id' => 'language',
      ],
      'filter' => [
        'id' => 'language',
      ],
      'argument' => [
        'id' => 'language',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locale_file']['filename'] = [
      'title' => $this->t('Langcode'),
      'help' => $this->t('Filename for importing the file.'),
      'field' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locale_file']['uri'] = [
      'title' => $this->t('URI'),
      'help' => $this->t('File system path for importing the file.'),
      'field' => [
        'id' => 'url',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['locale_file']['timestamp'] = [
      'title' => $this->t('Timestamp'),
      'help' => $this->t('Unix timestamp of the file itself from the point when it was last imported.'),
      'field' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
  }

}
