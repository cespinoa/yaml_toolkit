<?php

namespace Drupal\Tests\yaml_toolkit\Unit;

use org\bovigo\vfs\vfsStreamDirectory;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\yaml_toolkit\Contract\Service\YamlValidatorInterface;
use Drupal\yaml_toolkit\Service\YamlStorage;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use Drupal\yaml_toolkit\Contract\Helper\StreamWrapperManagerHelperInterface;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use Drupal\yaml_toolkit\Service\YamlValidator;

/**
 * @coversDefaultClass \Drupal\yaml_toolkit\Service\YamlStorage
 *
 * @group yaml_toolkit
 */
class YamlStorageTest extends UnitTestCase {

  /**
   * The YamlStorage to check.
   */
  private YamlStorage $yamlStorage;

  /**
   * The fileSystem service.
   */
  private FileSystemInterface $fileSystem;

  /**
   * The streamWrapperManagerHelper service.
   */
  private StreamWrapperManagerHelperInterface $streamWrapperManagerHelper;

  /**
   * The loggerFactory service.
   */
  private LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The loggerChannel service.
   */
  private LoggerChannelInterface $loggerChannel;

  /**
   * The messenger service.
   */
  private MessengerInterface $messenger;

  /**
   * The yamlValidator service.
   */
  private YamlValidatorInterface $yamlValidator;

  /**
   * The virtual fileSystem service.
   */
  private vfsStreamDirectory $vfsRoot;

  /**
   * Setup the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vfsRoot = vfsStream::setup('root');

    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->streamWrapperManagerHelper = $this->createMock(StreamWrapperManagerHelperInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->yamlValidator = $this->createMock(YamlValidatorInterface::class);

    $this->loggerFactory->method('get')->willReturn($this->loggerChannel);
    $this->fileSystem->method('basename')->willReturnCallback(fn($p) => basename($p));
    $this->fileSystem->method('realpath')->willReturnCallback(fn($p) => $p);

    $this->yamlValidator->method('checkYaml')->willReturn(TRUE);
    $this->yamlValidator->method('getResult')->willReturn([
      'yaml' => "foo: bar\n",
      'parsed' => ['foo' => 'bar'],
      'error_code' => 0,
    ]);

    // Mock the string translation service for t() function.
    $stringTranslation = $this->createMock(TranslationInterface::class);
    $stringTranslation->method('translate')
      ->willReturnCallback(function ($string, array $args = []) {
        return new TranslatableMarkup($string, $args, [], $stringTranslation);
      });

    $this->streamWrapperManagerHelper->method('getScheme')->willReturn(FALSE);

    $this->yamlStorage = new YamlStorage(
      $this->fileSystem,
      $this->loggerFactory,
      $this->messenger,
      $this->yamlValidator,
      $this->streamWrapperManagerHelper
    );

    // Set up container for t() function.
    $container = new ContainerBuilder();
    $container->set('string_translation', $stringTranslation);
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::load */
  public function testLoadNonExistingFile(): void {
    $filePath = $this->vfsRoot->url() . '/nonexistent.yml';
    $result = $this->yamlStorage->load($filePath);
    $this->assertFalse($result);
    $this->assertSame(YamlStorage::ERROR_FILE_NOT_FOUND, $this->yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::load */
  public function testLoadNonReadableFile(): void {
    $file = vfsStream::newFile('unreadable.yml', 0000)->at($this->vfsRoot);
    $result = $this->yamlStorage->load($file->url());
    $this->assertFalse($result);
    $this->assertSame(YamlStorage::ERROR_FILE_NOT_READABLE, $this->yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::load */
  public function testLoadExistingFileInvalidYaml(): void {
    $file = vfsStream::newFile('invalid.yml')->at($this->vfsRoot);
    file_put_contents($file->url(), 'invalid: [unclosed');

    $validatorMock = $this->createMock(YamlValidatorInterface::class);
    $validatorMock->method('checkYaml')->willReturn(FALSE);
    $validatorMock->method('getResult')->willReturn([
      'error_code' => YamlValidatorInterface::ERROR_SCALAR_VALUE,
      'error' => 'Invalid YAML',
      'error_description' => '',
      'parsed' => NULL,
      'yaml' => NULL,
    ]);

    $yamlStorage = new YamlStorage(
          $this->fileSystem,
          $this->loggerFactory,
          $this->messenger,
          $validatorMock,
          $this->streamWrapperManagerHelper
      );

    $result = $yamlStorage->load($file->url());

    $this->assertFalse($result);
    $this->assertSame(YamlValidatorInterface::ERROR_SCALAR_VALUE, $yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::load */
  public function testLoadExistingFileValidYaml(): void {
    $file = vfsStream::newFile('valid.yml')->at($this->vfsRoot);
    file_put_contents($file->url(), "foo: bar\n");

    $result = $this->yamlStorage->load($file->url());
    $this->assertIsArray($result);
    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertSame(YamlStorage::ERROR_SUCCESS, $this->yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::save */
  public function testSaveToNewFile(): void {

    $file = vfsStream::newFile('new.yml')->at($this->vfsRoot);

    $realValidator = new YamlValidator();

    $yamlStorage = new YamlStorage(
        $this->fileSystem,
        $this->loggerFactory,
        $this->messenger,
        $realValidator,
        $this->streamWrapperManagerHelper
    );

    $result = $yamlStorage->save($file->url(), ['foo' => 'bar']);
    $this->assertTRUE($result);
    $this->assertSame(YamlValidator::ERROR_SUCCESS, $yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::save */
  public function testSaveToEmptyPath(): void {
    $result = $this->yamlStorage->save('', ['foo' => 'bar']);
    $this->assertFalse($result);
    $this->assertSame(YamlStorage::ERROR_EMPTY_PATH, $this->yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::save */
  public function testSaveToNonWritableFile(): void {
    // Creamos el archivo dentro del vfsRoot.
    // Solo lectura.
    $file = vfsStream::newFile('readonly.yml', 0444)
      ->at($this->vfsRoot);

    $filePath = $file->url();

    $realValidator = new YamlValidator();
    $yamlStorage = new YamlStorage(
          $this->fileSystem,
          $this->loggerFactory,
          $this->messenger,
          $realValidator,
          $this->streamWrapperManagerHelper
      );

    $result = $yamlStorage->save($filePath, ['foo' => 'bar']);

    $this->assertFalse($result);
    $this->assertSame(YamlStorage::ERROR_FILE_NOT_WRITABLE, $yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::save */
  public function testSaveInvalidYaml(): void {
    $validatorMock = $this->createMock(YamlValidatorInterface::class);
    $validatorMock->method('checkYaml')->willReturn(FALSE);
    $validatorMock->method('getResult')->willReturn([
      'error_code' => YamlValidatorInterface::ERROR_SCALAR_VALUE,
      'error' => 'Invalid YAML',
      'error_description' => '',
      'parsed' => NULL,
      'yaml' => NULL,
    ]);

    $yamlStorage = new YamlStorage(
      $this->fileSystem,
      $this->loggerFactory,
      $this->messenger,
      $validatorMock,
      $this->streamWrapperManagerHelper
    );

    $file = vfsStream::newFile('invalid_save.yml')->at($this->vfsRoot);
    $result = $yamlStorage->save($file->url(), 123);

    $this->assertFalse($result);
    $this->assertSame(YamlValidatorInterface::ERROR_SCALAR_VALUE, $yamlStorage->getInfo()['error_code']);
  }

  /**
   * @covers ::save */
  public function testSaveValidYaml(): void {
    $file = vfsStream::newFile('valid_save.yml')->at($this->vfsRoot);
    $result = $this->yamlStorage->save($file->url(), ['foo' => 'bar']);
    $this->assertTrue($result);
    $this->assertSame(YamlStorage::ERROR_SUCCESS, $this->yamlStorage->getInfo()['error_code']);
  }

}
