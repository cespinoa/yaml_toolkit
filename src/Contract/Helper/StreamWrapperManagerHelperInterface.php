<?php

namespace Drupal\yaml_toolkit\Contract\Helper;

/**
 * Interface for YAML storage operations.
 */
interface StreamWrapperManagerHelperInterface {

  /**
   * Check the file schema.
   *
   * @param string $filePath
   *   The path of the file from which the schema is searched.
   *
   * @return bool
   *   True if $filePath contains scheme (private, public...).
   */
  public function getScheme(string $filePath): bool;

}
