<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\KeyType;

use Drupal\key\Plugin\KeyType\AuthenticationMultivalueKeyType;

/**
 * Key module plugin to define an oauth credentials KeyType.
 *
 * @KeyType(
 *   id = "oauth2_client",
 *   label = @Translation("Oauth2 Client"),
 *   description = @Translation("A key type to store oauth credentials for the Oauth2 Client module. Store as JSON:<br><pre>{<br>&quot;client_id&quot;: &quot;client_id value&quot;,<br>&quot;client_secret&quot;: &quot;client_secret value&quot;<br>}</pre>"),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "client_id" = @Translation("Client ID"),
 *       "client_secret" = @Translation("Client Secret"),
 *     }
 *   }
 * )
 */
class Oauth2ClientKey extends AuthenticationMultivalueKeyType {

}
