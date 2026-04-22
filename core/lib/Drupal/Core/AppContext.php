<?php

declare(strict_types=1);

namespace Drupal\Core;

/**
 * Default application context container for Drupal.
 */
readonly class AppContext implements AppContextInterface {

  /**
   * Constructs a new app context object.
   *
   * @param string $appRoot
   *   The path of the application root.
   * @param string $sitePath
   *   The current site path directory.
   * @param string $baseUrl
   *   The base URL of the application.
   * @param string $basePath
   *   The path part of the base URL.
   */
  public function __construct(
    protected string $appRoot,
    protected string $sitePath,
    protected string $baseUrl,
    protected string $basePath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAppRoot() : string {
    return $this->appRoot;
  }

  /**
   * {@inheritdoc}
   */
  public function getSitePath() : string {
    return $this->sitePath;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseUrl(): string {
    return $this->baseUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath(): string {
    return $this->basePath;
  }

}
