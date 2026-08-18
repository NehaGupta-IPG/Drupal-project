<?php

declare(strict_types=1);

namespace Drupal\binary_file_response_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller routines for binary file response tests.
 */
class BinaryFileResponseTestController {

  /**
   * Download the file set in the relative_file_url query parameter.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The response wrapping the file content.
   */
  #[Route(
    path: '/binary_file_response_test/download',
    name: 'binary_file_response_test.download',
    title: new TranslatableMarkup('Redirect'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function download(Request $request) {
    if (!$request->query->has('relative_file_url')) {
      throw new BadRequestHttpException();
    }

    $relative_file_url = $request->query->get('relative_file_url');

    // A relative URL for a file contains '%20' instead of spaces. A relative
    // file path contains spaces.
    $relative_file_path = rawurldecode($relative_file_url);

    // Ensure the file path does not start with a slash to prevent exploring
    // the file system root.
    $relative_file_path = ltrim($relative_file_path, '/');

    return new BinaryFileResponse($relative_file_path);
  }

}
