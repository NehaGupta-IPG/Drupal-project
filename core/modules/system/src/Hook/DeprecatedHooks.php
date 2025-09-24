<?php

namespace Drupal\system\Hook;

use Drupal\Core\Hook\Attribute\DeprecatedHook;

/**
 * Class for deprecating core hooks.
 */
#[DeprecatedHook('updater_info', 'drupal', '11.2.0', '12.0.0', 'There is no replacement. Use composer to manage the code for your site.', 'https://www.drupal.org/node/3512364')]
#[DeprecatedHook('updater_info_alter', 'drupal', '11.2.0', '12.0.0', 'There is no replacement. Use composer to manage the code for your site.', 'https://www.drupal.org/node/3512364')]
#[DeprecatedHook('filetransfer_info', 'drupal', '11.2.0', '12.0.0', 'There is no replacement. Use composer to manage the code for your site.', 'https://www.drupal.org/node/3512364')]
#[DeprecatedHook('filetransfer_info_alter', 'drupal', '11.2.0', '12.0.0', 'There is no replacement. Use composer to manage the code for your site.', 'https://www.drupal.org/node/3512364')]
#[DeprecatedHook('file_mimetype_mapping_alter', 'drupal', '11.2.0', '12.0.0', 'Implement a \Drupal\Core\File\Event\MimeTypeMapLoadedEvent listener instead.', 'https://www.drupal.org/node/3494040')]
class DeprecatedHooks {

}
