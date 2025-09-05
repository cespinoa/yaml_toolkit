<?php

namespace Drupal\yaml_toolkit\Contract\Service;

/**
 * Interface for YAML validaton.
 */
interface YamlValidatorInterface {


  const ERROR_SUCCESS = 0;
  const ERROR_VALIDATION_FAILED = 10;
  const ERROR_SCALAR_VALUE = 11;
  const ERROR_PARSE_FAILED = 12;
  const ERROR_UNSUPPORTED_TYPE = 13;
  const ERROR_NO_DATA = 14;

  /**
   * Validates the given content (YAML string or array).
   *
   * @param mixed $content
   *   YAML string, PHP array, or array of strings.
   *
   * @return bool
   *   TRUE if validation passes, FALSE otherwise.
   */
  public function checkYaml($content): bool;

  /**
   * Returns detailed information from the last validation attempt.
   *
   * @return array
   *   An associative array with keys:
   *   - status: "success" or "error".
   *   - type: Type of input processed.
   *   - message: Human readable message.
   */
  public function getResult(): array;

}
