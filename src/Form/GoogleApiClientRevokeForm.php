<?php

namespace Drupal\google_api_client\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\google_api_client\Entity\GoogleApiClient;

/**
 * Provides a form for revoking a google_api_client entity.
 *
 * @ingroup google_api_client
 */
class GoogleApiClientRevokeForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return 'Are you sure you want to revoke access token of this account';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return "This account can't be used for api call until authenticated again";
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return "Revoke";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.google_api_client.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $google_api_client = $this->entity;
    $client = GoogleApiClient::getGoogleApiClientClient($google_api_client);
    $client->revokeToken();
    $google_api_client->setAccessToken('{}');
    $google_api_client->setAuthenticated(FALSE);
    $google_api_client->save();
    parent::submitForm($form, $form_state);
    \Drupal::messenger()->addMessage('GoogleApiClient account revoked successfully');
    $response = new RedirectResponse('/admin/config/services/google_api_client');
    $response->send();
  }

}
