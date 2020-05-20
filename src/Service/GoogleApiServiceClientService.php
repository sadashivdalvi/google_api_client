<?php

namespace Drupal\google_api_client\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\google_api_client\GoogleApiServiceClientInterface;
use Google_Client;

/**
 * Class Google API Client Service.
 *
 * @package Drupal\google_api_client\Service
 */
class GoogleApiServiceClientService {

  /**
   * The GoogleClient object.
   *
   * @var \Google_Client
   */
  public $googleClient;

  /**
   * The GoogleApiClient Entity Object.
   *
   * @var \Drupal\google_api_client\GoogleApiServiceClientInterface
   */
  public $googleApiServiceClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cacheBackend;

  /**
   * Callback Controller constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   LoggerChannelFactoryInterface.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache Backend.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory,
                              CacheBackendInterface $cacheBackend) {
    $this->loggerFactory = $loggerFactory;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * Function to set the GoogleApiClient account for the service.
   *
   * @param \Drupal\google_api_client\GoogleApiServiceClientInterface $google_api_client
   *   Pass completely loaded GoogleApiClient object.
   *
   * @throws \Google_Exception
   */
  public function setGoogleApiClient(GoogleApiServiceClientInterface $google_api_client) {
    $this->googleApiServiceClient = $google_api_client;
    // Add the client.
    $this->getClient();
  }

  /**
   * Function to retrieve the google client for different operations.
   *
   * Developers can pass the google_api_client object to setGoogleApiClient
   * and get the api client ready for operations.
   *
   * @return \Google_Client
   *   Google_Client object with all params from the account.
   *
   * @throws \Google_Exception
   */
  private function getClient() {
    google_api_client_load_library();
    $client = new Google_Client();
    $client->setAuthConfig($this->googleApiServiceClient->getAuthConfig());
    $client->setScopes($this->googleApiServiceClient->getScopes(TRUE));
    $this->googleClient = $client;
    return $client;
  }

  /**
   * This function is designed to return objects of services classes.
   *
   * So if the account is authenticated for say Google calendar then
   * this function will return Google_Service_Calendar class object.
   *
   * @return array
   *   Array of Google_Service classes with servicename as index.
   */
  public function getServiceObjects() {
    $google_api_service_client = $this->googleApiServiceClient;
    $services = $google_api_service_client->getServices();
    if (!is_array($services)) {
      $services = [$services];
    }
    $classes = \Drupal::config('google_api_client.google_api_classes')->get('google_api_client_google_api_classes');
    $return = [];
    foreach ($services as $service) {
      $return[$service] = new $classes[$service]($this->googleClient);
    }
    return $return;
  }

}