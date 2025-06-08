<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Symfony\Component\Mime\Email;

/**
 * Provides an interface for entities capable of generating email messages.
 *
 * @ingroup entity_type_characteristics
 */
interface EntityEmailInterface {

  /**
   * Returns an email message generated from an entity.
   *
   * @param string $key
   *   The key that should be used to identify the template used to render the
   *   entity.
   * @param string|null $langcode
   *   (optional) For which language the entity should be rendered, defaults to
   *   the current content language.
   */
  public function toEmail(string $key, ?string $langcode): Email;

}
