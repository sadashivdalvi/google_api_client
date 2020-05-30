<?php

namespace Drupal\google_api_client\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\google_api_client\Service\GoogleApiClientService;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;

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
    if (isset($_GET['state'])) {
      $state = Json::decode($_GET['state']);
      /** We implement an additional hash check so that if the callback
       *  is opened for public access like it will be done for google login
       *  In that case we rely on the has for verifying that no one is hacking.
       */
      if ($state['hash'] != $_SESSION['google_api_client_state']['hash']) {
        \Drupal::messenger()->addError(t('Invalid state parameter'), 'error');
        drupal_access_denied();
        return $this->redirect('<front>');
      }
      if (isset($state['src']) && !in_array('google_api_client', $state['src'])) {
        // Handle response only if the request was from google_api_client.
        // Here some other module has set that we don't process standard google_api_client
        // so we invoke the webhook and return.
        \Drupal::moduleHandler()->invokeAll('google_api_client_google_response', [$request]);
        // we return to home page if not redirected in the webhook.
        return $this->redirect('<front>');
      }
    }
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
        $_SESSION['google_api_client_account_type'] = $entity_type;
      }
    }
    if (!google_api_client_load_library()) {
      // We don't have library installed notify admin and abort.
      $status_report_link = Link::createFromRoute($this->t('Status Report'), 'system.status')->toString();
      \Drupal::messenger()->addError($this->t("Can't authenticate with google as library is missing check %status_report for more details", [
        '%status_report' => $status_report_link,
      ]));
      return $this->redirect('entity.google_api_client.collection');
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
        $google_api_client->setAccessToken(Json::encode($this->googleApiClient->googleClient->getAccessToken()));
        $google_api_client->setAuthenticated(TRUE);
        $google_api_client->save();
        $destination = FALSE;
        if (isset($_SESSION['google_api_client_state']['destination'])) {
          $destination = $_SESSION['google_api_client_state']['destination'];
        }
        unset($_SESSION['google_api_client_state']);
        unset($_SESSION['google_api_client_account_id']);
        unset($_SESSION['google_api_client_account_type']);
        \Drupal::messenger()->addMessage($this->t('Api Account saved'));
        // Let other modules act of google response.
        \Drupal::moduleHandler()->invokeAll('google_api_client_google_response', [$request]);
        if ($destination) {
          return new RedirectResponse(Url::fromUserInput($destination)->toString());
        }
        return $this->redirect('entity.google_api_client.collection');
      }
      if ($this->googleApiClient->googleClient) {
        if (!isset($_SESSION['google_api_client_state'])) {
          $state = array(
            'src' => array('google_api_client'),
            'hash' => md5(rand())
          );
          if (isset($_GET['destination'])) {
            $state['destination'] = $_GET['destination'];
            unset($_GET['destination']);
          }
        }
        else {
          $state = $_SESSION['google_api_client_state'];
        }
        // Allow other modules to alter the state param.
        \Drupal::moduleHandler()->alter('google_api_client_state', $state, $google_api_client_id);
        $_SESSION['google_api_client_state'] = $state;
        $state = Json::encode($state);
        $this->googleApiClient->googleClient->setState($state);
        $auth_url = $this->googleApiClient->googleClient->createAuthUrl();
        $response = new TrustedRedirectResponse($auth_url);
        $response->send();
      }
    }
    return $this->redirect('entity.google_api_client.collection');
  }

  /**
   * Checks access for authenticate url.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function authenticateAccess(AccountInterface $account) {
    if ($account->hasPermission('administer google api settings')) {
      return AccessResult::allowed();
    }
    $account_id = \Drupal::request()->get('id');
    $account_type = \Drupal::request()->get('type', 'google_api_client');
    $access = \Drupal::moduleHandler()->invokeAll('google_api_client_authenticate_account_access', [
      $account_id,
      $account_type,
      $account]);
    // If any module returns forbidden then we don't allow authenticate.
    if (in_array(AccessResult::forbidden(), $access)) {
      return AccessResult::forbidden();
    }
    elseif (in_array(AccessResult::allowed(), $access)) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

}
