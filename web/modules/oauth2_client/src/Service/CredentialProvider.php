<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\key\Entity\Key;
use Drupal\key\KeyRepositoryInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;

/**
 * Class KeyProvider.
 *
 * @package Drupal\oauth2_client\Service
 */
class CredentialProvider {

  /**
   * Key module service conditionally injected.
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * KeyService constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The key value store to use.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Injected entity manager service.
   */
  public function __construct(
    protected StateInterface $state,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Provides a means to our services.yml file to conditionally inject service.
   *
   * @param \Drupal\key\KeyRepositoryInterface $repository
   *   The injected service, if it exists.
   */
  public function setKeyRepository(KeyRepositoryInterface $repository): void {
    $this->keyRepository = $repository;
  }

  /**
   * Detects if key module service was injected.
   *
   * @return bool
   *   True if the KeyRepository is present.
   */
  public function additionalProviders(): bool {
    return isset($this->keyRepository);
  }

  /**
   * Get the provided credentials.
   *
   * @param \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $plugin
   *   An authorization plugin.
   *
   * @return string[]
   *   The value of the configured key.
   */
  public function getCredentials(Oauth2ClientPluginInterface $plugin): array {
    /** @var \Drupal\oauth2_client\Entity\Oauth2Client $config */
    $config = $this->entityTypeManager->getStorage('oauth2_client')->load($plugin->getId());
    $credentialProvider = $config->getCredentialProvider();
    $storageKey = $config->getCredentialStorageKey();
    $credentials = [];
    if (empty($credentialProvider) || empty($storageKey)) {
      return $credentials;
    }
    switch ($credentialProvider) {
      case 'key':
        $keyEntity = $this->keyRepository->getKey($storageKey);
        if ($keyEntity instanceof Key) {
          // A key was found in the repository.
          $credentials = $keyEntity->getKeyValues();
        }
        break;

      default:
        $credentials = $this->state->get($storageKey);
    }

    return $credentials ?? [];
  }

}
