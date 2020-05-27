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
    $state = json_decode($_GET['state']);
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
function hook_google_api_client_account_scopes_alter(&$scopes, $google_api_client_id) {
  if ($google_api_client_id == 1) {
    unset($scopes['gmail']['GMAIL_METADATA']);
  }
}

/**
 * Allows other modules to modify the state before authentication.
 *
 * Developers may state, redirect destination after authentication,
 * or set source or remove default source.
 */
function hook_google_api_client_account_state_alter(&$state, $google_api_client_id) {
  if ($google_api_client_id == 1) {
    // If we want that we don't save authentication with google api client
    // Example is if we use google api client for google sign in
    $google_api_client_index = array_search('google_api_client', $state['src']);
    unset($state['src'][$google_api_client_index]);
    // If we want to redirect to /user page after authentication
    // Say it's again login with google
    $state['destination'] = '/user';
    // If we are creating our own module which implements
    // hook_google_api_client_google_response()
    // In this case we can set the source and check this in response handler
    $state['src'][] = 'my_module';
  }
}

/**
 * @} End of "google_api_client hooks".
 */
