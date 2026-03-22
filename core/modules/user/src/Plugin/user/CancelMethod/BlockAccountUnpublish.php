<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\user\CancelMethod;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Attribute\AccountCancelMethod;

/**
 * Cancels the user by blocking them and unpublishing their content.
 */
#[AccountCancelMethod(
  id: 'user_cancel_block_unpublish',
  label: new TranslatableMarkup('Disable the account and unpublish its content.'),
  description: new TranslatableMarkup('Your account will be blocked and you will no longer be able to log in. All of your content will be hidden from everyone but administrators.'),
)]
class BlockAccountUnpublish extends BlockAccount {}
