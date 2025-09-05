<?php

namespace Drupal\yaml_toolkit\Contract\Service;

/**
 * Interface for YAML storage operations.
 */
interface YamlStorageInterface {

  const ERROR_SUCCESS = 0;
  const ERROR_EMPTY_PATH = 1;
  const ERROR_FILE_NOT_FOUND = 2;
  const ERROR_FILE_NOT_READABLE = 3;
  const ERROR_FILE_NOT_WRITABLE = 4;
  const ERROR_READ_FAILED = 5;
  const ERROR_EMPTY_FILE = 6;
  const ERROR_NO_YAML_DATA = 7;
  const ERROR_WRITE_FAILED = 8;
  const ERROR_NO_DATA = 9;

  /**
   * Save YAML data into a file.
   *
   * @param string $filePath
   *   Path to the YAML file.
   * @param mixed $data
   *   Data to save (array or YAML string).
   * @param string $loggerChannel
   *   The logger channel to use.
   * @param bool $verbose
   *   If TRUE, display errors in UI messenger.
   *
   * @return bool
   *   TRUE if saved successfully, FALSE otherwise.
   */
  public function save(string $filePath, array $data, string $loggerChannel = 'default', bool $verbose = FALSE);

  /**
   * Load YAML data from a file.
   *
   * @param string $filePath
   *   Path to the YAML file (supports stream wrappers).
   * @param string $loggerChannel
   *   The logger channel to use for error logging.
   * @param bool $verbose
   *   If TRUE, display errors in UI messenger in addition to logging.
   *
   * @return array|false
   *   Parsed YAML data as array, or FALSE on failure.
   *   Use getInfo() to retrieve detailed error information.
   */
  public function load(string $filePath, string $loggerChannel = 'default', bool $verbose = FALSE);

  /**
   * Get information about the last operation.
   *
   * @return array
   *   Error code and message.
   */
  public function getInfo(): array;

}
