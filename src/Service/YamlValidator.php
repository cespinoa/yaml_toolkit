<?php

namespace Drupal\yaml_toolkit\Service;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Drupal\yaml_toolkit\Contract\Service\YamlValidatorInterface;

/**
 * Provides tolerant validation for YAML input.
 *
 * This class accepts different input types:
 * - A structured PHP array (will be dumped and re-parsed as YAML).
 * - An array of YAML strings (snippets).
 * - A raw YAML string (e.g. from a textarea).
 *
 * The main function checkYaml returns
 */
class YamlValidator implements YamlValidatorInterface {

  const ERROR_SUCCESS = 0;
  const ERROR_VALIDATION_FAILED = 10;
  const ERROR_SCALAR_VALUE = 11;
  const ERROR_PARSE_FAILED = 12;
  const ERROR_UNSUPPORTED_TYPE = 13;
  const ERROR_NO_DATA = 14;

  /**
   * Validation result.
   *
   * @var array
   */
  protected array $result = [];

  /**
   * Validates YAML content in various formats.
   *
   * Supported input types:
   * - String: Raw YAML content
   * - Array (indexed): Array of YAML strings to validate individually
   * - Array (associative): Structured data to dump as YAML and re-parse.
   *
   * @param mixed $content
   *   The content to validate.
   *
   * @return bool
   *   TRUE if validation passes, FALSE otherwise.
   */
  public function checkYaml($content): bool {

    if ($content === NULL ||
        $content === '' ||
        (is_string($content) && trim($content) === '') ||
        (is_array($content) && empty($content))) {

      $this->result = [
        'pass' => FALSE,
        'validation_type' => 'Empty input',
        'error_code' => self::ERROR_NO_DATA,
        'error' => t('No data to validate'),
        'error_description' => t('YAML Validator received empty or null data'),
        'parsed' => NULL,
        'yaml' => NULL,
        'debug' => $content,
      ];
      return FALSE;
    }

    if (is_string($content)) {
      $content = str_replace('\n', "\n", $content);
    }

    $content_type = gettype($content);

    try {
      if (!is_array($content)) {
        $data = Yaml::parse($content);
      }
      else {
        $data = $content;
      }

      if (is_array($data)) {

        if (array_is_list($data)) {
          $processInfo = self::checkStringArray($data);
          $debug = $processInfo;
          array_shift($debug);
          $success = $processInfo['pass'];
          $this->result = [
            'pass' => $success,
            'validation_type' => 'Array of strings from input ' . $content_type,
            'error_code' => $success ? self::ERROR_SUCCESS : self::ERROR_VALIDATION_FAILED,
            'error' => $success ? t('YAML validation passed') : t('YAML validation failed'),
            'error_description' => $success ? '' : self::packArrayListErrors($processInfo),
            'parsed' => $processInfo['parsed'],
            'yaml' => $processInfo['yaml'],
            'debug' => $debug,
          ];
          return $success;
        }
        else {
          $processInfo = self::checkStructuredArray($data);
          $success = $processInfo['pass'];
          $this->result = [
            'pass' => $success,
            'validation_type' => 'Structured array from input ' . $content_type,
            'error_code' => $success ? 0 : self::ERROR_VALIDATION_FAILED,
            'error' => $success ? t('YAML validation passed') : t('YAML validation failed'),
            'error_description' => $success ? '' : $processInfo['error'],
            'parsed' => $processInfo['parsed'],
            'yaml' => $processInfo['yaml'],
            'debug' => $processInfo,
          ];
          return $success;
        }
      }
      else {
        $this->result = [
          'pass' => FALSE,
          'validation_type' => 'Scalar from input ' . $content_type,
          'error_code' => self::ERROR_SCALAR_VALUE,
          'error' => t('Scalar value'),
          'error_description' => t('Scalar values ​​are not evaluated'),
          'parsed' => NULL,
          'yaml' => Yaml::dump($data, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
          'debug' => $content,
        ];
        return FALSE;
      }
    }
    catch (ParseException $e) {
      $this->result = [
        'pass' => FALSE,
        'validation_type' => 'Without validation from input ' . $content_type,
        'error_code' => self::ERROR_PARSE_FAILED,
        'error' => t('Conversion to yaml failed'),
        'error_description' => $e->getMessage(),
        'parsed' => NULL,
        'yaml' => NULL,
        'debug' => $content,
      ];
      return FALSE;
    }

    $this->result = [
      'pass' => FALSE,
      'validation_type' => 'Without validation from input ' . $content_type,
      'error_code' => self::ERROR_UNSUPPORTED_TYPE,
      'error' => t('Unsupported input type'),
      'error_description' => 'Without description',
      'parsed' => NULL,
      'yaml' => NULL,
      'debug' => $content,
    ];

    return FALSE;
  }

  /**
   * Returns detailed information from the last validation attempt.
   *
   * @return array
   *   An associative array with keys:
   *   - pass (boolean)
   *   - validation_type (Array of strings|Structured array|Scalar)
   *   - error_code 10 t0 14
   *   - error short string wiht the description of error
   *    10 YAML validation failed
   *    11 Scalar value
   *    12 Conversion to yaml failed
   *    13 Unsupported input type
   *    14 aml Validator has not received data
   *   - error_description long description with info from Yaml exception class
   *   - parsed (array|null)
   *   - yaml (string|null)
   *   - debug (aditional info)
   */
  public function getResult(): array {
    return $this->result;
  }

  /**
   * Packs all errors as one message string.
   *
   * @param array $processInfo
   *   The processInfo of checking string array.
   *
   * @return string
   *   The error message
   */
  private function packArrayListErrors($processInfo): string {
    array_shift($processInfo);
    unset($processInfo['yaml'], $processInfo['parsed']);

    $errors = array_filter($processInfo, function ($line) {
        return isset($line['valid']) && !$line['valid'];
    });

    return implode("\n", array_column($errors, 'error'));
  }

  /**
   * Validate an array of YAML strings (snippets).
   *
   * @param array $snippets
   *   Array of YAML strings.
   *
   * @return array
   *   List of validation processInfos.
   */
  protected static function checkStringArray(array $snippets): array {
    $processInfos['pass'] = TRUE;
    $processInfos['parsed'] = NULL;
    $processInfos['yaml'] = '';
    foreach ($snippets as $i => $snippet) {
      $item = self::checkYamlString($snippet, $i);
      if ($item['error']) {
        $processInfos['pass'] = FALSE;
      }
      $processInfos[] = $item;
      $processInfos['yaml'] .= "\n" . $item['yaml'];
    }
    if ($processInfos['pass'] === TRUE) {
      $processInfos['parsed'] = $snippets;
      $processInfos['yaml'] = Yaml::dump($snippets, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
    return $processInfos;
  }

  /**
   * Validate a raw YAML string.
   *
   * @param string $snippet
   *   YAML content as string.
   * @param int|null $index
   *   Optional index (used when validating an array of snippets).
   *
   * @return array
   *   Validation processInfo.
   */
  protected static function checkYamlString(string $snippet, ?int $index = NULL): array {
    try {
      $parsed = Yaml::parse($snippet);
      return [
        'index'  => $index,
        'valid'  => TRUE,
        'yaml'   => $snippet,
        'parsed' => $parsed,
        'error'  => NULL,
      ];
    }
    catch (ParseException $e) {
      return [
        'index'  => $index,
        'valid'  => FALSE,
        'yaml'   => $snippet,
        'parsed' => NULL,
        'error'  => $e->getMessage(),
      ];
    }
  }

  /**
   * Validate a structured PHP array by dumping and re-parsing as YAML.
   *
   * @param array $data
   *   Structured array.
   *
   * @return array
   *   Validation result.
   */
  protected static function checkStructuredArray(array $data): array {
    try {
      $yaml = Yaml::dump($data, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
      $parsed = Yaml::parse($yaml);
      $isValid = json_encode($parsed) === json_encode($data);
      return [
        'index'  => NULL,
        'pass'  => $isValid,
        'yaml'   => $yaml,
        'parsed' => $parsed,
        'error'  => $isValid ? NULL : 'Dumped YAML does not match the original array',
      ];
    }
    catch (ParseException $e) {
      return [
        'index'  => NULL,
        'pass'  => FALSE,
        'yaml'   => NULL,
        'parsed' => NULL,
        'error'  => $e->getMessage(),
      ];
    }
  }

}
