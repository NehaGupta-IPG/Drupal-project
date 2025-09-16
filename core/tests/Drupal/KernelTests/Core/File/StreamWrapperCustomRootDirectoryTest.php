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
     *
     * This checks that when the public files folder root is the same as a subfolder
     * being created, it does actually create it correctly. There has been an edge case
     * where if the root is "custom-dir" and the folder being created is also called
     * "custom-dir", it will not create the folder correctly and think it already exists.
     */
    public function testRealPathHandlesCustomRootDirectory(): void {
      $this->setSetting('file_public_path', '/custom-dir');
      $this->assertDirectoryDoesNotExist("public://foo/custom-dir");
    }

  }

}

namespace Drupal\Core\StreamWrapper {

  /**
   * Overrides the core realpath() function to help with this test.
   */
  function realpath(string $path): string|false {
    // Simulate that /custom-dir is an existing directory.
    if (preg_match('/^\/custom-dir$/', $path)) {
      return $path;
    }
    return \realpath($path);
  }

  /**
   * Overrides the core stat() function to help with this test.
   */
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
