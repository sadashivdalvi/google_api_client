<?php

/**
 * @file
 * Hooks provided by the GAuth module.
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
function hook_gauth_google_response() {
  if (isset($_GET['state'])) {
    $state = json_decode(stripslashes($_GET['state']));
    $action = $state->action;
    // Some other code to handle things.
  }
}

/**
 * Allows other modules to modify the scope before authentication.
 *
 * Developers may add or remove scopes,
 * like in this example I remove the gmail metadata scope.
 */
function hook_gauth_account_scopes_alter($scopes, $gauth_id) {
  if ($gauth_id == 1) {
    unset($scopes['gmail']['GMAIL_METADATA']);
  }
}

/**
 * @} End of "gauth hooks".
 */
