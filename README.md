/**
 * @mainpage YAML Toolkit
 *
 * Bienvenido a la documentación del proyecto YAML Toolkit.
 * 
 * - Visita las clases: @ref Drupal::yaml_toolkit
 * - Interfaces: @ref Drupal::yaml_toolkit::Contract
 */

# YAML Toolkit

Provides robust YAML validation and file handling services for Drupal applications.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Services](#services)
- [API](#api)
- [Troubleshooting](#troubleshooting)
- [Maintainers](#maintainers)

## Requirements

This module requires the following modules/libraries:

- Drupal: ^9.0 || ^10.0 || ^11.0
- PHP: ^8.1
- Symfony YAML Component: ^6.0 || ^7.0

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

### Via Composer (recommended)

```bash
composer require drupal/yaml_toolkit
```

### Manual installation

1. Download the latest version from the [project page](https://www.drupal.org/project/yaml_toolkit).
2. Extract the archive to your `modules/contrib` directory.
3. Enable the module at `admin/modules`.

## Configuration

No configuration is required. The module provides services that can be injected
into your custom code.

## Usage

### Basic YAML validation

```php
// Get the validator service
$validator = \Drupal::service('yaml_toolkit.validator');

// Validate a YAML string
$yamlString = "
key1: value1
key2:
  - item1
  - item2
";

$isValid = $validator->checkYaml($yamlString);
$result = $validator->getResult();

if ($isValid) {
  $parsedData = $result['parsed'];
  $yamlOutput = $result['yaml'];
}
```

### File operations

```php
// Get the storage service
$storage = \Drupal::service('yaml_toolkit.storage');

// Load YAML from file
$data = $storage->load('path/to/config.yml', 'my_module', TRUE);

if ($data !== FALSE) {
  // Process the loaded data
  $zones = $data['zones'] ?? [];
}

// Save YAML to file
$configData = [
  'zones' => [
    'content' => [
      'title' => [
        'plugin' => 'title_template',
        'tag' => 'h2',
      ],
    ],
  ],
];

$success = $storage->save('path/to/config.yml', $configData, 'my_module', TRUE);

if (!$success) {
  $error = $storage->getInfo();
  \Drupal::logger('my_module')->error($error['error_text']);
}
```

## Services

### yaml_toolkit.validator

Validates YAML content in multiple formats:

- Raw YAML strings
- PHP arrays (will be dumped and re-parsed)
- Arrays of YAML strings

**Service ID**: `yaml_toolkit.validator`  
**Interface**: `Drupal\yaml_toolkit\Contract\Service\YamlValidatorInterface`  
**Class**: `Drupal\yaml_toolkit\Service\YamlValidator`

#### Methods

- `checkYaml($data)`: Validates YAML content
- `getResult()`: Returns detailed validation information

### yaml_toolkit.storage

Handles YAML file operations with validation and error handling.

**Service ID**: `yaml_toolkit.storage`  
**Interface**: `Drupal\yaml_toolkit\Contract\Service\YamlStorageInterface`  
**Class**: `Drupal\yaml_toolkit\Service\YamlStorage`

#### Methods

- `load($filePath, $loggerChannel, $verbose)`: Load and validate YAML file
- `save($filePath, $data, $loggerChannel, $verbose)`: Save data as YAML file
- `getInfo()`: Get detailed information about the last operation

## API

### YamlValidatorInterface

```php
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
```

### YamlStorageInterface

```php
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

```

### Error codes

The validator uses the following error codes:

- `0`: Success
- `10`: YAML validation failed
- `11`: Scalar value (not supported)
- `12`: Conversion to YAML failed
- `13`: Unsupported input type
- `14`: No data provided

The storage service uses additional error codes:

- `1`: Empty file path
- `2`: File not found
- `3`: File not readable
- `4`: File not writable
- `5`: File read failed
- `6`: Empty file
- `7`: No YAML data
- `8`: File write failed
- `9`: YAML dump failed

## Troubleshooting

### Common issues

**"File not found" errors**
- Check file permissions
- Verify the file path is correct
- Ensure the file exists before attempting to load

**"YAML validation failed"**
- Check YAML syntax using an online validator
- Ensure proper indentation (2 spaces per level)
- Verify quotes and special characters are properly escaped

**"File not writable" errors**
- Check directory and file permissions
- Ensure the web server can write to the target directory
- Verify disk space is available

### Debugging

Enable verbose mode to display errors in the Drupal UI:

```php
$storage = \Drupal::service('yaml_toolkit.storage');
$result = $storage->load('config.yml', 'my_module', TRUE); // verbose = TRUE
```

Check the logs for detailed error information:

```bash
drush watchdog-show --type=my_module
```

## Maintainers

- [Carlos Espino Angulo](https://www.drupal.org/u/carlos-espino)
