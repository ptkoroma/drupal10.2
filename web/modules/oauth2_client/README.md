[![GitLab Testing Status Badge](https://git.drupalcode.org/project/oauth2_client/badges/4.0.x/pipeline.svg?ignore_skipped=true)](https://git.drupalcode.org/project/oauth2_client/-/pipelines)
# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Usage
 * Troubleshooting
 * Maintainers


## Introduction

The OAuth2 Client module allows for the creation of OAuth2 clients as Drupal
plugins, handling all back end functionality for retrieval, refresh, and
deletion of tokens

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/oauth2_client

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/oauth2_client


## Requirements

This module depends upon the OAuth 2.0 Client library from _The League of
Extraordinary Packages_. This library will be installed automatically when the
module is downloaded and installed with Composer.

 * Composer (https://getcomposer.org/)
 * OAuth 2.0 Client (https://oauth2-client.thephpleague.com/)


## Installation

This module must be installed using the following composer command:

`composer require drupal/oauth2_client:^3.0`

## Usage

### Creating New Clients

As of version 8.x-2.x of the module, OAuth2 Clients are Drupal Plugins.
Plugins must be created in the `Plugin\Oauth2Client` namespace.
Plugins should extend the class:
`Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase`.

An example plugin declaration is as follows:

```
namespace Drupal\your_module\Plugin\Oauth2Client;

/**
 * OAuth2 Client to authenticate with Instagram
 *
 * @Oauth2Client(
 *   id = "instagram",
 *   name = @Translation("Instagram"),
 *   grant_type = "authorization_code",
 *   authorization_uri = "https://api.instagram.com/oauth/authorize",
 *   token_uri = "https://api.instagram.com/oauth/access_token",
 *   resource_owner_uri = "",
 *   scopes = {"basic", "firebase", "openid"},
 *   scope_separator = ",",
 *   success_message = TRUE,
 * )
 */
class Instagram extends Oauth2ClientPluginBase {}
  ```

Fill in the various plugin keys with the relevant data. Keys:

 * id: This should be a unique plugin ID. There cannot be another @OAuth2Client
      plugin on the system with the same ID.
 * name: The Human-readable name of the plugin.
 * grant_type: The type of grant flow of the OAuth2 authentication server.
   Possible values are:
   * [authorization_code](https://alexbilbie.com/guide-to-oauth-2-grants/#authorisation-code-grant-section-41)
   * [client_credentials](https://alexbilbie.com/guide-to-oauth-2-grants/#client-credentials-grant--section-44)
   * [resource_owner](https://alexbilbie.com/guide-to-oauth-2-grants/#resource-owner-credentials-grant-section-43)
 * authorization_uri: The Authorization URL on the OAuth2 authentication server.
 * token_uri: The Token URL on the OAuth2 authentication server.
 * resource_owner_uri: The Resource Owner URL on the OAuth2 authentication
   server. Leave blank if not provided by the OAuth2 authentication server.
 * scopes: Scope is a mechanism in OAuth 2.0 to limit an application's access
   to a user's account. An application can request one or more scopes, this
   information is then presented to the user in the consent screen, and the
   access token issued to the application will be limited to the scopes granted.
 * scope_separator: character that will be added between multiple scope values.
 * success_message: Implementations may conditionally display a message on
   successful storage
 * collaborators: A mapping of keys = class for use as replacements to the
   default objects composed into the GenericProvider. Each key must map to a
   class that extends a specific class.
   See Oauth2ClientPluginInterface::getCollaborators for details. For the client
   credentials grant, if scopes are defined in the plugin, your custom option
   provider must extend ClientCredentialsOptionProvider or your option provider
   will be replaced before the token is requested. Allowed keys are:
   * grantFactory
   * requestFactory
   * httpClient
   * optionProvider

Further examples can be found in `examples/oauth2_client_example_plugins`.

### Confidential data

All OAuth2 clients need a _client id_ and a _client secret_. The resource_owner
grant also needs the authorized user's username and password. This module either
stores the client id and secret in the database using the State service, or
delegates storage to the [Key module](https://www.drupal.org/project/key) if it
is installed.  A `KeyType` plugin is provided for this integration.  The Key
module provides a several ways to store confidential data outside the Drupal
site. Users of the Key module are **strongly
advised** to note that the configuration option in the Key module is for
development only.

### Managing Clients Plugins

All installed client plugins are listed at `/admin/config/system/oauth2-client`.
The configuration for each plugin stores the data required to retrieve any
confidential data, and can be edited from links provided on this page. For
plugins that should be authorized and store a single token for the site, an
additional submit button is provided on the configuration form.

### Storing Access Tokens

Plugins must implement `::storeAccessToken`, `::retrieveAccessToken` and
`::clearAccessToken`.  These methods are intentional omitted from
`Oauth2ClientPluginBase` as the storage implementation is dependent on the
use case.  Examples are given in the example plugins.  The use of TempStore
is best when each user needs a unique token.  The use of State is best when
a single connection with an external service is used.  Other cases may lend
themselves to other approaches.

### Retrieving Access Tokens

To retrieve an access token, use the `getAccessToken()` method of the OAuth2
Service, passing it the Plugin ID of the OAuth2 Client for which token should be
retrieved. This will return a `\League\OAuth2\Client\Token\AccessToken` object
on which `getToken()` can be called.

```
$access_token = Drupal::service('oauth2_client.service')->getAccessToken($client_id);
$token = $access_token->getToken();
```

In the above example `$token` will contain the access token that can be used in
requests made to the remote server. The `getAccessToken()` method should be
called before making any requests, to ensure that the token is always valid.
This method will refresh the token in the background if necessary.

### Other Customizations

See comments in `Oauth2ClientPluginAccessInterface` and
`Oauth2ClientPluginRedirectInterface` for additional customizations built in
to the plugin system.
