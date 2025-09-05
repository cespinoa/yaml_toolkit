<?php

namespace Drupal\yaml_toolkit\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\yaml_toolkit\Contract\Service\YamlStorageInterface;
use Drupal\yaml_toolkit\Contract\Service\YamlValidatorInterface;
use Drupal\yaml_toolkit\Contract\Helper\StreamWrapperManagerHelperInterface;

/**
 * Service for loading and saving YAML files with validation and error handling.
 */
class YamlStorage implements YamlStorageInterface {

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
   * The FileSystemInterface Service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The LoggerChannelFactoryInterface Service.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The MessengerInterface Service.
   */
  protected MessengerInterface $messenger;

  /**
   * The YamlValidatorInterface Service.
   */
  protected YamlValidatorInterface $yamlValidator;

  /**
   * The StreamWrapperManagerHelperInterface Service.
   */
  protected StreamWrapperManagerHelperInterface $streamWrapperManagerHelper;

  /**
   * The file absolute path.
   */
  protected string $absolutePath = '';

  /**
   * The file name.
   */
  protected string $filename = '';

  /**
   * Info about some params.
   *
   * The array contents:
   *   - stage: load or save.
   *   - logger: the logger channel, default yaml_toolkit.
   *   - verbose: display messages or not.
   */
  protected array $info = [];

  /**
   * Constructor.
   */
  public function __construct(
    FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
    MessengerInterface $messenger,
    YamlValidatorInterface $yamlValidator,
    StreamWrapperManagerHelperInterface $streamWrapperManagerHelper,
  ) {
    $this->fileSystem = $fileSystem;
    $this->loggerFactory = $loggerFactory;
    $this->messenger = $messenger;
    $this->yamlValidator = $yamlValidator;
    $this->streamWrapperManagerHelper = $streamWrapperManagerHelper;
  }

  /**
   * Returns the last validation info.
   *
   * @return array
   *   Error code and error text of the last operation.
   */
  public function getInfo(): array {
    return $this->info;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $filePath, string $loggerChannel = 'default', bool $verbose = FALSE): array|false {
    $this->resetState($loggerChannel, $verbose, 'load');

    if (empty($filePath)) {
      return $this->fail(self::ERROR_EMPTY_PATH, 'Empty file path provided');
    }

    $absolutePath = $this->prepareFilePath($filePath);

    if (!$this->checkFileExists($absolutePath) || !$this->checkFileReadable($absolutePath)) {
      return FALSE;
    }

    return $this->parseYamlFile($absolutePath);
  }

  /**
   * {@inheritdoc}
   */
  public function save(string $filePath, $data, string $loggerChannel = 'default', bool $verbose = FALSE): bool {
    $this->resetState($loggerChannel, $verbose, 'save');

    if (empty($filePath)) {
      return $this->fail(self::ERROR_EMPTY_PATH, 'Empty file path provided');
    }

    if ($this->isEmptyData($data)) {
      return $this->fail(self::ERROR_NO_DATA, 'No data provided');
    }

    $absolutePath = $this->prepareFilePath($filePath);

    if (!$this->checkFileExists($absolutePath) || !$this->checkFileWritable($absolutePath)) {
      return FALSE;
    }

    if (is_string($data)) {
      $data = $this->normalizeYamlString($data);
    }
    $success = $this->yamlValidator->checkYaml($data);
    $details = $this->yamlValidator->getResult();
    if ($success) {
      $yaml = $details['yaml'];
      return $this->writeYamlFile($absolutePath, $yaml);
    }
    else {
      return $this->fail($details['error_code'], $this->formatValidationError($details));
    }
  }

  /**
   * Reset state for a new operation.
   */
  protected function resetState(string $loggerChannel, bool $verbose, string $stage): void {
    $this->info = [];
    $this->logger = $this->loggerFactory->get($loggerChannel);
    $this->verbose = $verbose;
    $this->stage = $stage;
  }

  /**
   * Resolve and prepare an absolute file path.
   */
  protected function prepareFilePath(string $filePath): string {
    if ($this->streamWrapperManagerHelper->getScheme($filePath)) {
      $filePath = $this->fileSystem->realpath($filePath);
    }
    $absolutePath = file_exists($filePath) ? $this->fileSystem->realpath($filePath) : $filePath;
    $this->absolutePath = $absolutePath;
    $this->filename = $this->fileSystem->basename($absolutePath);
    return $absolutePath;
  }

  /**
   * Check if a file exists.
   */
  protected function checkFileExists(string $absolutePath): bool {
    if (!file_exists($absolutePath)) {
      return $this->fail(self::ERROR_FILE_NOT_FOUND, 'File %filename not found', ['%filename' => $this->filename]);
    }
    return TRUE;
  }

  /**
   * Check if a file is readable.
   */
  protected function checkFileReadable(string $absolutePath): bool {
    if (!is_readable($absolutePath)) {
      return $this->fail(self::ERROR_FILE_NOT_READABLE, 'File %filename is not readable', ['%filename' => $this->filename]);
    }
    return TRUE;
  }

  /**
   * Check if a file is writable.
   */
  protected function checkFileWritable(string $absolutePath): bool {
    if (!is_writable($absolutePath)) {
      return $this->fail(self::ERROR_FILE_NOT_WRITABLE, 'File %filename is not writable', ['%filename' => $this->filename]);
    }
    return TRUE;
  }

  /**
   * Parse YAML content from a file.
   */
  protected function parseYamlFile(string $absolutePath): array|false {
    $content = file_get_contents($absolutePath);

    if ($content === FALSE) {
      return $this->fail(self::ERROR_READ_FAILED, 'The file %filename could not be read', ['%filename' => $this->filename]);
    }

    if (empty(trim($content))) {
      return $this->fail(self::ERROR_EMPTY_FILE, 'The file %filename is empty', ['%filename' => $this->filename]);
    }

    $content = $this->normalizeYamlString($content);

    if (!$this->checkYamlSyntax($content)) {
      return FALSE;
    }

    return $this->yamlValidator->getResult()['parsed'];
  }

  /**
   * Validate YAML syntax.
   */
  protected function checkYamlSyntax($data): bool {
    if (empty($data)) {
      return $this->fail(self::ERROR_NO_YAML_DATA, 'File %filename has no YAML data', ['%filename' => $this->filename]);
    }

    $success = $this->yamlValidator->checkYaml($data);

    if (!$success) {
      $details = $this->yamlValidator->getResult();
      return $this->fail($details['error_code'], $this->formatValidationError($details));
    }
    else {
      $details = $this->yamlValidator->getResult();
    }

    $this->info = ['error_code' => 0, 'error_text' => 'YAML validation passed.'];
    return TRUE;
  }

  /**
   * Write YAML content to a file.
   *
   * @param string $absolutePath
   *   The file absolute path.
   * @param string $yamlContent
   *   Pre-validated YAML content.
   *
   * @return bool
   *   TRUE if file was saved, FALSE otherwise
   */
  protected function writeYamlFile(string $absolutePath, string $yamlContent): bool {
    $result = $this->fileSystem->saveData($yamlContent, $absolutePath, FileSystemInterface::EXISTS_REPLACE);

    if ($result === FALSE) {
      return $this->fail(self::ERROR_WRITE_FAILED, 'The file %filename cannot be written.', ['%filename' => $this->filename]);
    }

    $this->info = ['error_code' => 0, 'error_text' => 'File saved successfully.'];
    return TRUE;
  }

  /**
   * Helper for logging and setting error info.
   */
  protected function fail(int $code, string $message, array $context = []): bool {
    $message = t($message, $context);
    $this->sendLogError($message);
    $this->info = ['error_code' => $code, 'error_text' => $message];
    return FALSE;
  }

  /**
   * Log and optionally display an error message.
   */
  protected function sendLogError(string $message): void {
    if ($this->verbose) {
      $this->messenger->addError($message);
    }
    if ($this->logger) {
      $this->logger->error($message . "\n\t" . 'File: ' . $this->absolutePath);
    }
  }

  /**
   * Helper function to check if data is empty.
   */
  private function isEmptyData($data): bool {
    return $data === NULL ||
      $data === '' ||
      $data === "\n" ||
      (is_array($data) && empty($data)) ||
      (is_string($data) && trim($data) === '');
  }

  /**
   * Helper function to normalize data string.
   */
  protected function normalizeYamlString(string $data): string {
    return str_replace(['\n', '\r\n', '\r'], ["\n", "\n", "\n"], $data);
  }

  /**
   * Helper to format the validation error result.
   */
  private function formatValidationError(array $details): string {
    $stages = ['load' => t('Loading'), 'save' => t('Saving')];
    $message = ($details['error']);
    $message .= "<br>" . t('@stage_action the file: %filename', [
      '@stage_action' => $stages[$this->stage],
      '%filename' => $this->filename,
    ]);
    $message .= "<br>" . $details['error_description'];
    return str_replace("\n", "<br>", $message);
  }

}
