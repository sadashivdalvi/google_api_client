<?php

namespace Drupal\google_api_client\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for google_api_client entity.
 *
 * @ingroup google_api_client
 */
class GoogleApiClientListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('GoogleApiClient implements a GoogleApiClient account model. These google_api_client accounts are fieldable entities. You can manage the fields on the <a href="@adminlink">GoogleApiClient admin page</a>.', [
        '@adminlink' => \Drupal::urlGenerator()
          ->generateFromRoute('google_api_client.google_api_client_settings'),
      ]),
    ];
    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the snapshot list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header = ['Id', 'Name', 'Services', 'Is Authenticated'];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\google_api_client\Entity\GoogleApiClient */

    $row = [
      'id' => $entity->getId(),
      'Name' => $entity->getName(),
      'Services' => implode(", ", _google_api_client_google_services_names($entity->getServices())),
      'is_authenticated' => $entity->getAuthenticated() ? t('Yes') : t('No'),
    ];
    return $row + parent::buildRow($entity);
  }

}
