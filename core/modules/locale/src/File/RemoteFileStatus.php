<?php

namespace Drupal\locale\File;

/**
 * Provides the locale RemoteFileStatus.
 */
enum RemoteFileStatus: int {

  // The file was found successfully.
  case Success = 1;

  // There was an error retrieving the file.
  case Error = 0;

  // The file was not found.
  case Missing = 404;

  // The file has not been modified since the given timestamp.
  case NotModified = 304;

}
