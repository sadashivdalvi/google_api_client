<?php

namespace Drupal\google_api_client\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\google_api_client\GoogleApiClientInterface;
use Google_Client;

/**
 * Class Google API Client Service.
 *
 * @package Drupal\google_api_client\Service
 */
class GoogleApiClientService {

  /**
   * The GoogleClient object.
   *
   * @var \Google_Client
   */
  public $googleClient;

  /**
   * The GoogleApiClient Entity Object.
   *
   * @var \Drupal\google_api_client\GoogleApiClientInterface
   */
  public $googleApiClient;

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
   * @param \Drupal\google_api_client\GoogleApiClientInterface $google_api_client
   *   Pass completely loaded GoogleApiClient object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setGoogleApiClient(GoogleApiClientInterface $google_api_client) {
    $this->googleApiClient = $google_api_client;
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getClient() {
    google_api_client_load_library();
    $client = new Google_Client();
    $client->setRedirectUri(google_api_client_callback_url());
    if ($this->googleApiClient == NULL) {
      return $client;
    }
    $google_api_client = $this->googleApiClient;
    $client->setClientId($google_api_client->getClientId());
    if ($google_api_client->getAccessType()) {
      $client->setAccessType('offline');
      $client->setApprovalPrompt('force');
    }
    $client->setClientSecret($google_api_client->getClientSecret());
    $client->setDeveloperKey($google_api_client->getDeveloperKey());
    $client->setRedirectUri(google_api_client_callback_url());
    $client->setApplicationName($google_api_client->getName());
    $scopes = $google_api_client->getScopes();

    // Let other modules change scopes.
    $google_api_client_id = $google_api_client->getId();
    \Drupal::moduleHandler()->alter('google_api_client_account_scopes', $scopes, $google_api_client_id);
    $client->addScope($scopes);
    $this->googleClient = $client;
    if ($google_api_client->getAuthenticated()) {
      $this->googleClient->setAccessToken($google_api_client->getAccessToken());
      $this->setAccessToken();
    }
    return $this->googleClient;
  }

  /**
   * Wrapper for Google_Client::fetchAccessTokenWithAuthCode.
   *
   * @param string $code
   *   Code string from callback url.
   *
   * @return array
   *   Token values array.
   *
   * @deprecated in google_api_client:8.x-2.0 and
   * is removed from google_api_client:8.x-3.0.
   *   Use Google_Client/fetchAccessTokenWithAuthCode()
   *   Instead, you should just check googleClient object function.
   */
  public function getAccessTokenByAuthCode($code) {
    return $this->googleClient->fetchAccessTokenWithAuthCode($code);
  }

  /**
   * Wrapper for Google_Client::fetchAccessTokenWithRefreshToken.
   *
   * @return array|bool
   *   token array or false.
   *
   * @deprecated in google_api_client:8.x-2.0
   * and is removed from google_api_client:8.x-3.0.
   *   Use Google_Client/fetchAccessTokenWithRefreshToken()
   *   Instead, you should just check googleClient object function.
   */
  public function getAccessTokenWithRefreshToken() {
    if ($access_token = $this->googleApiClient->getAccessToken() && isset($access_token['refresh_token'])) {
      return $this->googleClient->fetchAccessTokenWithRefreshToken($access_token['refresh_token']);
    }
    return FALSE;
  }

  /**
   * Wrapper for Google_Client::setAccessToken.
   *
   * @return bool
   *   Was the token added or not?
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function setAccessToken() {
    // If there was something in cache.
    if ($access_token = $this->googleApiClient->getAccessToken()) {
      // Check if the current token is expired?
      if ($this->googleClient->isAccessTokenExpired()) {
        // Refresh the access token using refresh token if it is set.
        if ($refresh_token = $this->googleClient->getRefreshToken()) {
          if ($tokenUpdated = $this->googleClient->fetchAccessTokenWithRefreshToken($refresh_token)) {
            $this->googleApiClient->setAccessToken($tokenUpdated);
            $this->googleApiClient->save();
            // There should be a new unexpired token.
            return TRUE;
          }
        }
        // Else the token fetch from refresh token failed.
        $this->googleClient->revokeToken();
        $this->googleApiClient->setAuthenticated(FALSE);
        $this->googleApiClient->setAccessToken('');
        $this->googleApiClient->save();
        // Unable to update token.
        return FALSE;
      }
      $this->googleClient->setAccessToken($access_token);
      // Token is set and is valid.
      return TRUE;
    }
    // There is no token in db.
    return FALSE;
  }

  /**
   * This function is designed to return objects of services classes.
   *
   * So if the account is authenticated for say Google calendar then
   * this function will return Google_Service_Calendar class object.
   *
   * @param bool $blank_client
   *   If we should use a blank client object.
   * @param bool $return_object
   *   True if we want objects else classes returned.
   *
   * @return array
   *   Array of Google_Service classes with servicename as index.
   */
  public function getServiceObjects($blank_client = FALSE, $return_object = TRUE) {
    $google_api_client = $this->googleApiClient;
    $services = $google_api_client->getServices();
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
