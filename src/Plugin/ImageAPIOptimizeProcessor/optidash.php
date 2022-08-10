<?php

namespace Drupal\optidash\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\imageapi_optimize\ConfigurableImageAPIOptimizeProcessorBase;
use Drupal\imageapi_optimize\ImageAPIOptimizeProcessorBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Optimize images using the Optidash.ai webservice.
 *
 * @ImageAPIOptimizeProcessor(
 *   id = "optidash",
 *   label = @Translation("optidash.ai"),
 *   description = @Translation("Optimize images using the optidash.ai webservice.")
 * )
 */
class optidash extends ConfigurableImageAPIOptimizeProcessorBase {

  /**
   * The file system service.
   *
   * @var \Drupal\core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageFactory $image_factory, FileSystemInterface $file_system, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $image_factory);

    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('imageapi_optimize'),
      $container->get('image.factory'),
      $container->get('file_system'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => NULL,
      'lossy' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = array(
      '#title' => t('API Key'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['api_key'],
    );

    $form['lossy'] = array(
      '#title' => t('Use lossy compression'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['lossy'],
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['lossy'] = $form_state->getValue('lossy');
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $description = '';

    if (!class_exists('\Optidash')) {
      $description .= $this->t('<strong>Very Sorry, Could not locate Optidash PHP library.</strong>');
    }
    else {
      if ($this->configuration['lossy']) {
        $description .= $this->t('Using lossy compression.');
      }
      else {
        $description .= $this->t('Using lossless compression.');
      }
    }

    $summary = array(
      '#markup' => $description,
    );
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function applyToImage($image_uri) {
    
    if (class_exists('\Optidash')) {
      if (!empty($this->configuration['api_key']) && !empty($this->configuration['api_secret'])) {
        $optidash = new \Optidash($this->configuration['api_key']);
        $params = array(
          'file' => $this->fileSystem->realpath($image_uri),
          'wait' => TRUE,
          'lossy' => (bool) $this->configuration['lossy'],
        );

        // Send the request to Optidash.
        $data = $optidash->upload($params);

        if (!empty($data['success']) && !empty($data['optidash_url'])) {
          try {
            $optidashedFile = $this->httpClient->get($data['optidash_url']);
            if ($optidashedFile->getStatusCode() == 200) {
              $this->fileSystem->saveData($optidashedFile->getBody(), $image_uri, FileSystemInterface::EXISTS_REPLACE);
              $this->logger->info('@file_name was successfully processed by Optidash.ai.
        Original size: @original_size; Optidash size: @optidash_size; Total saved:
        @saved_bytes. All figures in bytes', array(
                  '@file_name' => $image_uri,
                  '@original_size' => $data['original_size'],
                  '@optidash_size' => $data['optidash_size'],
                  '@saved_bytes' => $data['saved_bytes'],
                )
              );
              return TRUE;
            }
          } catch (RequestException $e) {
            $this->logger->error('Failed to download optimized image using Optidash.ai due to "%error".', array('%error' => $e->getMessage()));
          }
        }
        else {
          $this->logger->error('Optidash.ai could not optimize the uploaded image.');
        }
      }
      else {
        $this->logger->error('Optidash API key or secret not set.');
      }
    }
    else {
      $this->logger->error('Could not locate Optidash PHP library.');
    }
    return FALSE;
  }
}
