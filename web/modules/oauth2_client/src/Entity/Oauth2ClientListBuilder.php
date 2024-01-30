<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of oauth2 clients.
 */
class Oauth2ClientListBuilder extends ConfigEntityListBuilder {

  /**
   * Injected Oauth Plugin service.
   */
  protected Oauth2ClientPluginManager $oauthPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->oauthPluginManager = $container->get('oauth2_client.plugin_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    // Trigger discovery to ensure that any missing entities are created.
    $this->oauthPluginManager->getDefinitions();
    return parent::load();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\oauth2_client\Entity\Oauth2ClientInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // We want to remove the destination parameter from the edit link and allow
    // the form to determine a destination.
    if (isset($operations['edit']['url'])) {
      $operations['edit']['url'] = $entity->toUrl('edit-form');
    }
    return $operations;
  }

}
