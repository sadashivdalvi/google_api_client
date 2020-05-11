<?php

namespace Drupal\google_api_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\google_api_client\Service\GoogleApiClientService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Google Client Callback Controller.
 *
 * @package Drupal\google_api_client\Controller
 */
class Callback extends ControllerBase {

  /**
   * Google API Client.
   *
   * @var \Drupal\google_api_client\Service\GoogleApiClient
   */
  private $googleApiClient;

  /**
   * Callback constructor.
   *
   * @param \Drupal\google_api_client\Service\GoogleApiClientService $googleApiClient
   *   Google API Client.
   */
  public function __construct(GoogleApiClientService $googleApiClient) {
    $this->googleApiClient = $googleApiClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('google_api_client.client')
    );
  }

  /**
   * Callback URL for Google API Auth.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return array
   *   Return markup for the page.
   */
  public function callbackUrl(Request $request) {
    $account_id = $request->get('id');
    $entity_type = $request->get('type');
    if ($entity_type) {
      $_SESSION['google_api_client_account_type'] = $entity_type;
    }
    else {
      if (isset($_SESSION['google_api_client_account_type'])) {
        $entity_type = $_SESSION['google_api_client_account_type'];
      }
      else {
        $entity_type = 'google_api_client';
      }
    }
    google_api_client_load_library();
    if (!class_exists('Google_Client')) {
      \Drupal::messenger()->addError(t("Can't authenticate with google as library is missing check Status report or Readme for requirements"));
      return new RedirectResponse('/admin/config/services/google_api_client');
    }
    if ($account_id == NULL && isset($_SESSION['google_api_client_account_id'])) {
      $account_id = $_SESSION['google_api_client_account_id'];
    }
    elseif ($account_id) {
      $_SESSION['google_api_client_account_id'] = $account_id;
    }
    if ($account_id) {
      $google_api_client = \Drupal::entityTypeManager()->getStorage($entity_type)->load($account_id);
      $this->googleApiClient->setGoogleApiClient($google_api_client);
      $this->googleApiClient->googleClient->setApplicationName("Google OAuth2");

      if ($request->get('code')) {
        $this->googleApiClient->googleClient->fetchAccessTokenWithAuthCode($request->get('code'));
        $google_api_client->setAccessToken(json_encode($this->googleApiClient->googleClient->getAccessToken()));
        $google_api_client->setAuthenticated(TRUE);
        $google_api_client->save();
        unset($_SESSION['google_api_client_account_id']);
        $response = new RedirectResponse('/admin/config/services/google_api_client');
        \Drupal::messenger()->addMessage(t('Api Account saved'));
        return $response;
      }
      if ($this->googleApiClient->googleClient) {
        $auth_url = $this->googleApiClient->googleClient->createAuthUrl();
        $response = new TrustedRedirectResponse($auth_url);
        $response->send();
      }
    }
    // Let other modules act of google response.
    \Drupal::moduleHandler()->invokeAll('google_api_client_google_response', [$request]);
    $response = new RedirectResponse('/admin/config/services/google_api_client');
    return $response;
  }

}
