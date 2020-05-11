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
  protected $googleClient;

  /**
   * The GoogleApiClient Entity Object.
   *
   * @var \Drupal\google_api_client\GoogleApiClientInterface
   */
  protected $googleApiClient;

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
   */
  public function setGoogleApiClient(GoogleApiClientInterface $google_api_client) {
    $this->googleApiClient = $google_api_client;
    // Add the client without tokens.
    $this->googleClient = $this->getClient();

    // Check and add tokens.
    // Tokens wont always be set or valid, so this is a 2 step process.
    $this->setAccessToken();
  }

  /**
   * Function to retrieve the google client for different operations.
   *
   * Developers can pass the google_api_client object to setGoogleApiClient
   * and get the api client ready for operations.
   *
   * @param bool $blank
   *   If some resource wants a blank object with basic details set.
   *
   * @return \Google_Client
   *   Google_Client object with all params from the account.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getClient($blank = FALSE) {
    google_api_client_load_library();
    $client = new Google_Client();
    $client->setRedirectUri(google_api_client_callback_url());
    if ($this->googleApiClient == NULL || $blank) {
      return $client;
    }
    $google_api_client = $this->googleApiClient;
    $client->setClientId($google_api_client->getClientId());
    if ($google_api_client->getAuthenticated()) {
      $client->setAccessToken($google_api_client->getAccessToken());
    }
    if ($google_api_client->getAccessType()) {
      $client->setAccessType('offline');
    }
    $client->setClientSecret($google_api_client->getClientSecret());
    $client->setDeveloperKey($google_api_client->getDeveloperKey());
    $client->setRedirectUri(google_api_client_callback_url());
    if ($google_api_client->getAccessType()) {
      $client->setApprovalPrompt('force');
    }
    $client->setApplicationName("Google OAuth2");
    $scopes = $google_api_client->getScopes();

    // Let other modules change scopes.
    $google_api_client_id = $google_api_client->getId();
    \Drupal::moduleHandler()->alter('google_api_client_account_scopes', $scopes, $google_api_client_id);
    $client->addScope($scopes);

    if ($client->getAccessToken() && $client->isAccessTokenExpired()) {
      if ($client->getRefreshToken() != '') {
        // Access Type is Offline.
        $client->refreshToken($client->getRefreshToken());
        $token = $client->getAccessToken();
        $google_api_client->setAccessToken($token);
        $google_api_client->save();
      }
      else {
        $client->revokeToken();
        $google_api_client->setAuthenticated(FALSE);
        $google_api_client->setAccessToken('');
        $google_api_client->save();
        \Drupal::messenger()->addMessage(t('Access token is expired. If you are admin then you need to authenticate again. Consider configuring access type to offline.'));
      }
    }
    return $client;
  }

  /**
   * Wrapper for Google_Client::fetchAccessTokenWithAuthCode.
   *
   * @param string $code
   *   Code string from callback url.
   *
   * @return array
   *   Token values array.
   */
  public function getAccessTokenByAuthCode($code) {
    $token = $this->googleClient->fetchAccessTokenWithAuthCode($code);
    if (isset($token['access_token'])) {
      $this->setTokenCache('google_access_token', $token);
    }

    // Refresh token is only set the first time.
    if (isset($token['refresh_token'])) {
      $this->setTokenCache('google_refresh_token', [$token['refresh_token']]);
    }

    return $token;
  }

  /**
   * Wrapper for Google_Client::fetchAccessTokenWithRefreshToken.
   *
   * @return array|bool
   *   token array or false.
   */
  public function getAccessTokenWithRefreshToken() {
    if ($access_token = $this->googleApiClient->getAccessToken() && isset($access_token['refresh_token'])) {
      $token = $this->googleClient->fetchAccessTokenWithRefreshToken($access_token['refresh_token']);
      if (isset($token['access_token'])) {
        return $token;
      }
    }

    return FALSE;
  }

  /**
   * Wrapper for Google_Client::setAccessToken.
   *
   * @return bool
   *   Was the token added or not?
   */
  private function setAccessToken() {
    // If there was something in cache.
    if ($access_token = $this->googleApiClient->getAccessToken()) {
      $this->googleClient->setAccessToken($access_token);

      // Check if the current cached token is expired?
      if ($this->googleClient->isAccessTokenExpired()) {
        // Refresh the access token using refresh token.
        $tokenUpdated = $this->getAccessTokenWithRefreshToken();

        // Now that there is a new access token in cache,
        // set it into the client.
        if ($tokenUpdated != FALSE) {
          $this->googleClient->setAccessToken($tokenUpdated);
          // There should be a new unexpired token.
          return TRUE;
        }
        // Unable to update token.
        return FALSE;
      }
      // Token is set and is valid.
      return TRUE;
    }
    // There is no token cache.
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
      if ($blank_client) {
        $client = new Google_Client();
        if ($return_object) {
          $return[$service] = new $classes[$service]($client);
        }
        else {
          $return[$service] = $classes[$service];
        }
      }
      else {
        if ($return_object) {
          $return[$service] = new $classes[$service]($this->googleClient);
        }
        else {
          $return[$service] = $classes[$service];
        }
      }
    }
    return $return;
  }

}
