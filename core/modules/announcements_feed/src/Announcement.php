<?php

declare(strict_types=1);

namespace Drupal\announcements_feed;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Object containing a single announcement from the feed.
 *
 * @internal
 */
final class Announcement {

  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly string $url,
    public readonly string $date_modified,
    public readonly string $date_published,
    public readonly string $content_html,
    public readonly string $version,
    public readonly bool $featured,
  ) {
  }

  /**
   * Returns the content of the announcement with no markup.
   *
   * @return string
   *   Content of the announcement without markup.
   */
  public function getContent() {
    return strip_tags($this->content_html);
  }

  /**
   * Gets the published date in timestamp format.
   *
   * @return int
   *   Date published timestamp.
   */
  public function getDatePublishedTimestamp() {
    return DrupalDateTime::createFromFormat(DATE_ATOM, $this->date_published)->getTimestamp();
  }

}
