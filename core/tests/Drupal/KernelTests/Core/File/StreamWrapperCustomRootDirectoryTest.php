<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File {

  /**
   * Tests stream wrapper functions.
   *
   * @group File
   */
  class StreamWrapperCustomRootDirectoryTest extends FileTestBase {

    /**
     * Tests that the public stream wrapper can handle a custom root directory.
     */
    public function testRealPathHandlesCustomRootDirectory(): void {
      $this->setSetting('file_public_path', '/custom-dir');
      $this->assertDirectoryDoesNotExist("public://foo/custom-dir");
    }

  }

}

namespace Drupal\Core\StreamWrapper {

  function realpath(string $path): string|false {
    // Simulate that /custom-dir is an existing directory.
    if (preg_match('/^\/custom-dir$/', $path)) {
      return $path;
    }
    return \realpath($path);
  }

  function stat(string $filename): array|false {
    // Simulate that /custom-dir is an existing directory.
    if (preg_match('/^\/custom-dir$/', $filename)) {
      if ($realPath = \realpath('public://')) {
        return \stat($realPath);
      }
      return FALSE;
    }
    return \stat($filename);
  }

}
