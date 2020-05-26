<?php

/**
 * @file
 * Hooks provided by the Google Api Client module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * A google response was received.
 *
 * Modules may use this hook to carry operations based on google response.
 * This is helpful when response other than authentication are received.
 * Google response have data in url so $_GET can be used in this function.
 */
function hook_google_api_client_google_response() {
  if (isset($_GET['state'])) {
    $state = json_decode($_GET['state'], TRUE);
    if (isset($state['src']) && in_array('my_module', $state['src'])) {
      // Handle response only if the request was from my_module.
      return;
    }
    // Changes to be made for custom module
  }
}

/**
 * Allows other modules to modify the scope before authentication.
 *
 * Developers may add or remove scopes,
 * like in this example I remove the gmail metadata scope.
 */
function hook_google_api_client_account_scopes_alter($scopes, $gauth_id) {
  if ($gauth_id == 1) {
    unset($scopes['gmail']['GMAIL_METADATA']);
  }
}

/**
 * @} End of "google_api_client hooks".
 */
