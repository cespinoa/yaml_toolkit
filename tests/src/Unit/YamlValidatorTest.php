<?php

namespace Drupal\Tests\yaml_toolkit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\yaml_toolkit\Service\YamlValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the YamlValidator service.
 *
 * @group yaml_toolkit
 * @coversDefaultClass \Drupal\yaml_toolkit\Service\YamlValidator
 */
class YamlValidatorTest extends UnitTestCase {

  /**
   * The YAML validator service.
   *
   * @var \Drupal\yaml_toolkit\Service\YamlValidator
   */
  protected YamlValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the string translation service for t() function.
    $stringTranslation = $this->createMock(TranslationInterface::class);
    $stringTranslation->method('translate')
      ->willReturnCallback(function ($string, array $args = []) {
        return new TranslatableMarkup($string, $args, [], $stringTranslation);
      });

    $this->validator = new YamlValidator();

    // Set up container for t() function.
    $container = new ContainerBuilder();
    $container->set('string_translation', $stringTranslation);
    \Drupal::setContainer($container);
  }

  /**
   * Tests validation with empty or null data.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   * @dataProvider providerEmptyData
   */
  public function testCheckYamlWithEmptyData($input, string $expectedType): void {
    $result = $this->validator->checkYaml($input);

    $this->assertFalse($result);

    $details = $this->validator->getResult();
    $this->assertFalse($details['pass']);
    $this->assertEquals(14, $details['error_code']);

    $this->assertTrue(
      strpos($details['validation_type'], 'Empty input') !== FALSE ||
      strpos($details['validation_type'], 'has not received data') !== FALSE,
      "validation_type should contain 'Empty input' or 'has not received data', got: " . $details['validation_type']
    );
    $this->assertNull($details['parsed']);
    $this->assertNull($details['yaml']);
    //~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);.
  }

  /**
   * Data provider for empty data tests.
   */
  public function providerEmptyData(): array {
    return [
      'null' => [NULL, 'NULL'],
      'empty string' => ['', 'string'],
      'whitespace only' => ['   ', 'string'],
      'empty array' => [[], 'array'],
    ];
  }

  /**
   * Tests validation with valid YAML strings.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   * @dataProvider providerValidYamlStrings
   */
  public function testCheckYamlWithValidStrings(string $yaml, array $expectedParsed): void {
    $result = $this->validator->checkYaml($yaml);

    $this->assertTrue($result);

    $details = $this->validator->getResult();
    $this->assertTrue($details['pass']);
    $this->assertEquals(0, $details['error_code']);
    $this->assertEquals($expectedParsed, $details['parsed']);
    $this->assertNotNull($details['yaml']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);.
  }

  /**
   * Data provider for valid YAML strings.
   */
  public function providerValidYamlStrings(): array {
    return [
      'simple key-value' => [
        'key: value',
        ['key' => 'value'],
      ],
      'nested structure' => [
        "zones:\n  content:\n    title:\n      plugin: title_template",
        [
          'zones' => [
            'content' => [
              'title' => [
                'plugin' => 'title_template',
              ],
            ],
          ],
        ],
      ],
      'array values' => [
        "items:\n  - first\n  - second",
        ['items' => ['first', 'second']],
      ],
    ];
  }

  /**
   * Tests validation with invalid YAML strings.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   * @dataProvider providerInvalidYamlStrings
   */
  public function testCheckYamlWithInvalidStrings(string $yaml): void {
    $result = $this->validator->checkYaml($yaml);

    $this->assertFalse($result);

    $details = $this->validator->getResult();
    $this->assertFalse($details['pass']);
    $this->assertEquals(12, $details['error_code']);
    $this->assertNull($details['parsed']);
    $this->assertNull($details['yaml']);
    $this->assertNotEmpty($details['error_description']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);
  }

  /**
   * Data provider for invalid YAML strings.
   */
  public function providerInvalidYamlStrings(): array {
    return [
      'unmatched brackets' => ['key: [value'],
      'invalid indentation' => ["key:\n   value:\n  invalid"],
      'unmatched quotes' => ['key: "unclosed string'],
      'tab characters' => ["key:\n\tvalue: test"],
    ];
  }

  /**
   * Tests validation with structured arrays.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   * @dataProvider providerStructuredArrays
   */
  public function testCheckYamlWithStructuredArrays(array $data): void {
    $result = $this->validator->checkYaml($data);

    $this->assertTrue($result);

    $details = $this->validator->getResult();
    $this->assertTrue($details['pass']);
    $this->assertEquals(0, $details['error_code']);
    $this->assertEquals($data, $details['parsed']);
    $this->assertNotNull($details['yaml']);
    $this->assertStringContainsString('Structured array', $details['validation_type']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);
  }

  /**
   * Data provider for structured arrays.
   */
  public function providerStructuredArrays(): array {
    return [
      'simple array' => [
        ['key' => 'value', 'number' => 42],
      ],
      'nested array' => [
        [
          'zones' => [
            'content' => [
              'title' => [
                'plugin' => 'title_template',
                'tag' => 'h2',
              ],
            ],
          ],
        ],
      ],
      'mixed types' => [
        [
          'string' => 'text',
          'number' => 123,
          'boolean' => TRUE,
          'array' => ['item1', 'item2'],
        ],
      ],
    ];
  }

  /**
   * Tests validation with array of YAML strings.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   */
  public function testCheckYamlWithStringArray(): void {
    $yamlStrings = [
      'key1: value1',
      'key2: value2',
      "nested:\n  item: value",
    ];

    $result = $this->validator->checkYaml($yamlStrings);

    $this->assertTrue($result);

    $details = $this->validator->getResult();
    $this->assertTrue($details['pass']);
    $this->assertEquals(0, $details['error_code']);
    $this->assertEquals($yamlStrings, $details['parsed']);
    $this->assertNotNull($details['yaml']);
    $this->assertStringContainsString('Array of strings', $details['validation_type']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);
  }

  /**
   * Tests validation with array containing invalid YAML strings.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   */
  public function testCheckYamlWithInvalidStringArray(): void {
    $yamlStrings = [
    // Valid.
      'key1: value1',
    // Invalid.
      'key2: [invalid',
    // Valid.
      'key3: value3',
    ];

    $result = $this->validator->checkYaml($yamlStrings);

    $this->assertFalse($result);

    $details = $this->validator->getResult();
    $this->assertFalse($details['pass']);
    $this->assertEquals(10, $details['error_code']);
    $this->assertNull($details['parsed']);
    $this->assertNotEmpty($details['error_description']);
    $this->assertStringContainsString('Array of strings', $details['validation_type']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);
  }

  /**
   * Tests validation with scalar values.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   * @dataProvider providerScalarValues
   */
  public function testCheckYamlWithScalarValues($scalar): void {
    $result = $this->validator->checkYaml($scalar);

    $this->assertFalse($result);

    $details = $this->validator->getResult();
    $this->assertFalse($details['pass']);
    $this->assertEquals(11, $details['error_code']);
    $this->assertNull($details['parsed']);
    $this->assertNotNull($details['yaml']);
    $this->assertStringContainsString('Scalar', $details['validation_type']);
    // ~ $this->assertInstanceOf(TranslatableMarkup::class, $details['error']);
  }

  /**
   * Data provider for scalar values.
   */
  public function providerScalarValues(): array {
    return [
      'integer' => [42],
      'float' => [3.14],
      'boolean true' => [TRUE],
      'boolean false' => [FALSE],
    ];
  }

  /**
   * Tests newline handling in YAML.
   *
   * @covers ::checkYaml
   */
  public function testNewlineHandling(): void {
    // Test actual newline in YAML literal string.
    $yamlWithLiteralNewline = "key: |\n  line1\n  line2";

    $result = $this->validator->checkYaml($yamlWithLiteralNewline);

    $this->assertTrue($result);

    $details = $this->validator->getResult();
    
    $this->assertEquals(['key' => "line1\nline2"], $details['parsed']);

    // Test folded string (spaces instead of newlines)
    $yamlWithFoldedString = "key: >\n  line1\n  line2";

    $result2 = $this->validator->checkYaml($yamlWithFoldedString);

    $this->assertTrue($result2);

    $details2 = $this->validator->getResult();
    
    $this->assertArrayHasKey('key', $details2['parsed']);
    $this->assertIsString($details2['parsed']['key']);
  }

  /**
   * Tests result structure completeness.
   *
   * @covers ::getResult
   */
  public function testResultStructure(): void {
    $this->validator->checkYaml('key: value');
    $result = $this->validator->getResult();

    $expectedKeys = [
      'pass',
      'validation_type',
      'error_code',
      'error',
      'error_description',
      'parsed',
      'yaml',
      'debug',
    ];

    foreach ($expectedKeys as $key) {
      $this->assertArrayHasKey($key, $result, "Result should contain key: $key");
    }
  }

  /**
   * Tests multiple consecutive validations.
   *
   * @covers ::checkYaml
   * @covers ::getResult
   */
  public function testConsecutiveValidations(): void {
    // First validation - valid.
    $result1 = $this->validator->checkYaml('key1: value1');
    $this->assertTrue($result1);
    $details1 = $this->validator->getResult();

    // Second validation - invalid.
    $result2 = $this->validator->checkYaml('key2: [invalid');
    $this->assertFalse($result2);
    $details2 = $this->validator->getResult();

    // Results should be independent.
    $this->assertNotEquals($details1['error_code'], $details2['error_code']);
    $this->assertNotEquals($details1['pass'], $details2['pass']);
  }

}
