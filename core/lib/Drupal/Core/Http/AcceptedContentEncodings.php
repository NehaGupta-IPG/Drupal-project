<?php

declare(strict_types=1);

namespace Drupal\Core\Http;

/**
 * Provides the response content encodings the HTTP client can decode.
 */
final class AcceptedContentEncodings {

  /**
   * Gets the content encodings the active HTTP handler can decode.
   *
   * The returned string can be passed as Guzzle's 'decode_content' request
   * option to advertise these encodings in the Accept-Encoding request
   * header and transparently decode a compressed response. Guzzle itself
   * offers no way to determine what its handler supports and by default
   * sends no Accept-Encoding header at all, so servers do not compress
   * responses. This mirrors Guzzle's handler choice, which prefers curl
   * whenever the extension is available.
   *
   * @return string
   *   Comma separated content encodings for the Accept-Encoding header, or
   *   an empty string when decoding support cannot be determined.
   */
  public static function get(): string {
    $encodings = [];
    if (\extension_loaded('curl')) {
      // These are the same build features curl consults when it composes
      // the Accept-Encoding header itself and when it decodes responses.
      $features = curl_version()['features'];
      if ($features & CURL_VERSION_LIBZ) {
        $encodings[] = 'gzip';
        $encodings[] = 'deflate';
      }
      if (\defined('CURL_VERSION_BROTLI') && $features & CURL_VERSION_BROTLI) {
        $encodings[] = 'br';
      }
      if (\defined('CURL_VERSION_ZSTD') && $features & CURL_VERSION_ZSTD) {
        $encodings[] = 'zstd';
      }
    }
    elseif (\extension_loaded('zlib')) {
      // Without curl, Guzzle's stream handler decodes gzip and deflate
      // responses using the zlib extension.
      $encodings = ['gzip', 'deflate'];
    }
    return implode(', ', $encodings);
  }

}
