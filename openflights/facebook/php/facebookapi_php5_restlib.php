<?php
// Copyright 2004-2008 Facebook. All Rights Reserved.
//
// +---------------------------------------------------------------------------+
// | Facebook Platform PHP5 client                                             |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Facebook, Inc.                                    |
// | All rights reserved.                                                      |
// |                                                                           |
// | Redistribution and use in source and binary forms, with or without        |
// | modification, are permitted provided that the following conditions        |
// | are met:                                                                  |
// |                                                                           |
// | 1. Redistributions of source code must retain the above copyright         |
// |    notice, this list of conditions and the following disclaimer.          |
// | 2. Redistributions in binary form must reproduce the above copyright      |
// |    notice, this list of conditions and the following disclaimer in the    |
// |    documentation and/or other materials provided with the distribution.   |
// |                                                                           |
// | THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR      |
// | IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES |
// | OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.   |
// | IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT  |
// | NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY     |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT       |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF  |
// | THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.         |
// +---------------------------------------------------------------------------+
// | For help with this library, contact developers-help@facebook.com          |
// +---------------------------------------------------------------------------+
//

include_once 'jsonwrapper/jsonwrapper.php';

class FacebookRestClient {
  public $secret;
  public $session_key;
  public $api_key;
  // to save making the friends.get api call, this will get prepopulated on
  // canvas pages
  public $friends_list;
  public $user;
  // to save making the pages.isAppAdded api call, this will get prepopulated
  // on canvas pages
  public $added;
  public $is_user;
  // we don't pass friends list to iframes, but we want to make
  // friends_get really simple in the canvas_user (non-logged in) case.
  // So we use the canvas_user as default arg to friends_get
  public $canvas_user;
  public $batch_mode;
  private $batch_queue;
  private $call_as_apikey;
  private $use_curl_if_available;

  const BATCH_MODE_DEFAULT = 0;
  const BATCH_MODE_SERVER_PARALLEL = 0;
  const BATCH_MODE_SERIAL_ONLY = 2;

  /**
   * Create the client.
   * @param string $session_key if you haven't gotten a session key yet, leave
   *                            this as null and then set it later by just
   *                            directly accessing the $session_key member
   *                            variable.
   */
  public function __construct($api_key, $secret, $session_key=null) {
    $this->secret       = $secret;
    $this->session_key  = $session_key;
    $this->api_key      = $api_key;
    $this->batch_mode = FacebookRestClient::BATCH_MODE_DEFAULT;
    $this->last_call_id = 0;
    $this->call_as_apikey = '';
    $this->use_curl_if_available = true;
    $this->server_addr  = Facebook::get_facebook_url('api') . '/restserver.php';

    if (!empty($GLOBALS['facebook_config']['debug'])) {
      $this->cur_id = 0;
      ?>
<script type="text/javascript">
var types = ['params', 'xml', 'php', 'sxml'];
function getStyle(elem, style) {
  if (elem.getStyle) {
    return elem.getStyle(style);
  } else {
    return elem.style[style];
  }
}
function setStyle(elem, style, value) {
  if (elem.setStyle) {
    elem.setStyle(style, value);
  } else {
    elem.style[style] = value;
  }
}
function toggleDisplay(id, type) {
  for (var i = 0; i < types.length; i++) {
    var t = types[i];
    var pre = document.getElementById(t + id);
    if (pre) {
      if (t != type || getStyle(pre, 'display') == 'block') {
        setStyle(pre, 'display', 'none');
      } else {
        setStyle(pre, 'display', 'block');
      }
    }
  }
  return false;
}
</script>
<?php
    }
  }

  /**
   * Set the default user id for methods that allow the caller
   * to pass an uid parameter to identify the target user
   * instead of a session key. This currently applies to
   * the user preferences methods.
   *
   * @param $uid int the user id
   */
  public function set_user($uid) {
    $this->user = $uid;
  }

  /**
   * Normally, if the cURL library/PHP extension is available, it is used for
   * HTTP transactions.  This allows that behavior to be overridden, falling
   * back to a vanilla-PHP implementation even if cURL is installed.
   *
   * @param $use_curl_if_available bool whether or not to use cURL if available
   */
  public function set_use_curl_if_available($use_curl_if_available) {
    $this->use_curl_if_available = $use_curl_if_available;
  }

  /**
   * Start a batch operation.
   */
  public function begin_batch() {
    if($this->batch_queue !== null) {
      $code = FacebookAPIErrorCodes::API_EC_BATCH_ALREADY_STARTED;
      $description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
      throw new FacebookRestClientException($description, $code);
    }

    $this->batch_queue = array();
  }

  /*
   * End current batch operation
   */
  public function end_batch() {
    if($this->batch_queue === null) {
      $code = FacebookAPIErrorCodes::API_EC_BATCH_NOT_STARTED;
      $description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
      throw new FacebookRestClientException($description, $code);
    }

    $this->execute_server_side_batch();

    $this->batch_queue = null;
  }

  private function execute_server_side_batch() {
    $item_count = count($this->batch_queue);
    $method_feed = array();
    foreach($this->batch_queue as $batch_item) {
      $method = $batch_item['m'];
      $params = $batch_item['p'];
      $this->finalize_params($method, $params);
      $method_feed[] = $this->create_post_string($method, $params);
    }

    $method_feed_json = json_encode($method_feed);

    $serial_only =
      ($this->batch_mode == FacebookRestClient::BATCH_MODE_SERIAL_ONLY);
    $params = array('method_feed' => $method_feed_json,
                    'serial_only' => $serial_only);
    if ($this->call_as_apikey) {
      $params['call_as_apikey'] = $this->call_as_apikey;
    }

    $xml = $this->post_request('batch.run', $params);

    $result = $this->convert_xml_to_result($xml, 'batch.run', $params);


    if (is_array($result) && isset($result['error_code'])) {
      throw new FacebookRestClientException($result['error_msg'],
                                            $result['error_code']);
    }

    for($i = 0; $i < $item_count; $i++) {
      $batch_item = $this->batch_queue[$i];
      $batch_item_result_xml = $result[$i];
      $batch_item_result = $this->convert_xml_to_result($batch_item_result_xml,
                                                        $batch_item['m'],
                                                        $batch_item['p']);

      if (is_array($batch_item_result) &&
          isset($batch_item_result['error_code'])) {
        throw new FacebookRestClientException($batch_item_result['error_msg'],
                                              $batch_item_result['error_code']);
      }
      $batch_item['r'] = $batch_item_result;
    }
  }

  public function begin_permissions_mode($permissions_apikey) {
    $this->call_as_apikey = $permissions_apikey;
  }

  public function end_permissions_mode() {
    $this->call_as_apikey = '';
  }

  /**
   * Returns public information for an application (as shown in the application
   * directory) by either application ID, API key, or canvas page name.
   *
   * @param int $application_id              (Optional) app id
   * @param string $application_api_key      (Optional) api key
   * @param string $application_canvas_name  (Optional) canvas name
   *
   * Exactly one argument must be specified, otherwise it is an error.
   *
   * @return array  An array of public information about the application.
   */
  public function application_getPublicInfo($application_id=null,
                                            $application_api_key=null,
                                            $application_canvas_name=null) {
    return $this->call_method('facebook.application.getPublicInfo',
        array('application_id' => $application_id,
              'application_api_key' => $application_api_key,
              'application_canvas_name' => $application_canvas_name));
  }

  /**
   * Creates an authentication token to be used as part of the desktop login
   * flow.  For more information, please see
   * http://wiki.developers.facebook.com/index.php/Auth.createToken.
   *
   * @return string  An authentication token.
   */
  public function auth_createToken() {
    return $this->call_method('facebook.auth.createToken', array());
  }

  /**
   * Returns the session information available after current user logs in.
   *
   * @param string $auth_token             the token returned by
   *                                       auth_createToken or passed back to
   *                                       your callback_url.
   * @param bool $generate_session_secret  whether the session returned should
   *                                       include a session secret
   *
   * @return array  An assoc array containing session_key, uid
   */
  public function auth_getSession($auth_token, $generate_session_secret=false) {
    //Check if we are in batch mode
    if($this->batch_queue === null) {
      $result = $this->call_method('facebook.auth.getSession',
          array('auth_token' => $auth_token,
                'generate_session_secret' => $generate_session_secret));
      $this->session_key = $result['session_key'];

    if (!empty($result['secret']) && !$generate_session_secret) {
      // desktop apps have a special secret
      $this->secret = $result['secret'];
    }
      return $result;
    }
  }

  /**
   * Generates a session-specific secret. This is for integration with
   * client-side API calls, such as the JS library.
   *
   * @return array  A session secret for the current promoted session
   *
   * @error API_EC_PARAM_SESSION_KEY
   *        API_EC_PARAM_UNKNOWN
   */
  public function auth_promoteSession() {
      return $this->call_method('facebook.auth.promoteSession', array());
  }

  /**
   * Expires the session that is currently being used.  If this call is
   * successful, no further calls to the API (which require a session) can be
   * made until a valid session is created.
   *
   * @return bool  true if session expiration was successful, false otherwise
   */
  public function auth_expireSession() {
      return $this->call_method('facebook.auth.expireSession', array());
  }

  /**
   * Revokes the user's agreement to the Facebook Terms of Service for your
   * application.  If you call this method for one of your users, you will no
   * longer be able to make API requests on their behalf until they again
   * authorize your application.  Use with care.  Note that if this method is
   * called without a user parameter, then it will revoke access for the
   * current session's user.
   *
   * @param int $uid  (Optional) User to revoke
   *
   * @return bool  true if revocation succeeds, false otherwise
   */
  public function auth_revokeAuthorization($uid=null) {
      return $this->call_method('facebook.auth.revokeAuthorization',
          array('uid' => $uid));
  }

  /**
   * Returns the number of unconnected friends that exist in this application.
   * This number is determined based on the accounts registered through
   * connect.registerUsers() (see below).
   */
  public function connect_getUnconnectedFriendsCount() {
    return $this->call_method('facebook.connect.getUnconnectedFriendsCount',
        array());
  }

 /**
  * This method is used to create an association between an external user
  * account and a Facebook user account, as per Facebook Connect.
  *
  * This method takes an array of account data, including a required email_hash
  * and optional account data. For each connected account, if the user exists,
  * the information is added to the set of the user's connected accounts.
  * If the user has already authorized the site, the connected account is added
  * in the confirmed state. If the user has not yet authorized the site, the
  * connected account is added in the pending state.
  *
  * This is designed to help Facebook Connect recognize when two Facebook
  * friends are both members of a external site, but perhaps are not aware of
  * it.  The Connect dialog (see fb:connect-form) is used when friends can be
  * identified through these email hashes. See the following url for details:
  *
  *   http://wiki.developers.facebook.com/index.php/Connect.registerUsers
  *
  * @param mixed $accounts A (JSON-encoded) array of arrays, where each array
  *                        has three properties:
  *                        'email_hash'  (req) - public email hash of account
  *                        'account_id'  (opt) - remote account id;
  *                        'account_url' (opt) - url to remote account;
  *
  * @return array  The list of email hashes for the successfully registered
  *                accounts.
  */
  public function connect_registerUsers($accounts) {
    return $this->call_method('facebook.connect.registerUsers',
        array('accounts' => $accounts));
  }

 /**
  * Unregisters a set of accounts registered using connect.registerUsers.
  *
  * @param array $email_hashes  The (JSON-encoded) list of email hashes to be
  *                             unregistered.
  *
  * @return array  The list of email hashes which have been successfully
  *                unregistered.
  */
  public function connect_unregisterUsers($email_hashes) {
    return $this->call_method('facebook.connect.unregisterUsers',
        array('email_hashes' => $email_hashes));
  }

  /**
   * Returns events according to the filters specified.
   *
   * @param int $uid            (Optional) User associated with events. A null
   *                            parameter will default to the session user.
   * @param array $eids         (Optional) Filter by these event ids. A null
   *                            parameter will get all events for the user.
   * @param int $start_time     (Optional) Filter with this unix time as lower
   *                            bound.  A null or zero parameter indicates no
   *                            lower bound.
   * @param int $end_time       (Optional) Filter with this UTC as upper bound.
   *                            A null or zero parameter indicates no upper
   *                            bound.
   * @param string $rsvp_status (Optional) Only show events where the given uid
   *                            has this rsvp status.  This only works if you
   *                            have specified a value for $uid.  Values are as
   *                            in events.getMembers.  Null indicates to ignore
   *                            rsvp status when filtering.
   *
   * @return array  The events matching the query.
   */
  public function &events_get($uid=null,
                              $eids=null,
                              $start_time=null,
                              $end_time=null,
                              $rsvp_status=null) {
    return $this->call_method('facebook.events.get',
        array('uid' => $uid,
              'eids' => $eids,
              'start_time' => $start_time,
              'end_time' => $end_time,
              'rsvp_status' => $rsvp_status));
  }

  /**
   * Returns membership list data associated with an event.
   *
   * @param int $eid  event id
   *
   * @return array  An assoc array of four membership lists, with keys
   *                'attending', 'unsure', 'declined', and 'not_replied'
   */
  public function &events_getMembers($eid) {
    return $this->call_method('facebook.events.getMembers',
      array('eid' => $eid));
  }

  /**
   * RSVPs the current user to this event.
   *
   * @param int $eid             event id
   * @param string $rsvp_status  'attending', 'unsure', or 'declined'
   *
   * @return bool  true if successful
   */
  public function &events_rsvp($eid, $rsvp_status) {
    return $this->call_method('facebook.events.rsvp',
        array(
        'eid' => $eid,
        'rsvp_status' => $rsvp_status));
  }

  /**
   * Cancels an event. Only works for events where application is the admin.
   *
   * @param int $eid                event id
   * @param string $cancel_message  (Optional) message to send to members of
   *                                the event about why it is cancelled
   *
   * @return bool  true if successful
   */
  public function &events_cancel($eid, $cancel_message='') {
    return $this->call_method('facebook.events.cancel',
        array('eid' => $eid,
              'cancel_message' => $cancel_message));
  }

  /**
   * Creates an event on behalf of the user is there is a session, otherwise on
   * behalf of app.  Successful creation guarantees app will be admin.
   *
   * @param assoc array $event_info  json encoded event information
   *
   * @return int  event id
   */
  public function &events_create($event_info) {
    return $this->call_method('facebook.events.create',
        array('event_info' => $event_info));
  }

  /**
   * Edits an existing event. Only works for events where application is admin.
   *
   * @param int $eid                 event id
   * @param assoc array $event_info  json encoded event information
   *
   * @return bool  true if successful
   */
  public function &events_edit($eid, $event_info) {
    return $this->call_method('facebook.events.edit',
        array('eid' => $eid,
              'event_info' => $event_info));
  }

  /**
   * Fetches and re-caches the image stored at the given URL, for use in images
   * published to non-canvas pages via the API (for example, to user profiles
   * via profile.setFBML, or to News Feed via feed.publishUserAction).
   *
   * @param string $url  The absolute URL from which to refresh the image.
   *
   * @return bool  true on success
   */
  public function &fbml_refreshImgSrc($url) {
    return $this->call_method('facebook.fbml.refreshImgSrc',
        array('url' => $url));
  }

  /**
   * Fetches and re-caches the content stored at the given URL, for use in an
   * fb:ref FBML tag.
   *
   * @param string $url  The absolute URL from which to fetch content. This URL
   *                     should be used in a fb:ref FBML tag.
   *
   * @return bool  true on success
   */
  public function &fbml_refreshRefUrl($url) {
    return $this->call_method('facebook.fbml.refreshRefUrl',
        array('url' => $url));
  }

  /**
   * Lets you insert text strings in their native language into the Facebook
   * Translations database so they can be translated.
   *
   * @param array $native_strings  An array of maps, where each map has a 'text'
   *                               field and a 'description' field.
   *
   * @return int  Number of strings uploaded.
   */
  public function &fbml_uploadNativeStrings($native_strings) {
    return $this->call_method('facebook.fbml.uploadNativeStrings',
        array('native_strings' => json_encode($native_strings)));
  }

  /**
   * Associates a given "handle" with FBML markup so that the handle can be
   * used within the fb:ref FBML tag. A handle is unique within an application
   * and allows an application to publish identical FBML to many user profiles
   * and do subsequent updates without having to republish FBML on behalf of
   * each user.
   *
   * @param string $handle  The handle to associate with the given FBML.
   * @param string $fbml    The FBML to associate with the given handle.
   *
   * @return bool  true on success
   */
  public function &fbml_setRefHandle($handle, $fbml) {
    return $this->call_method('facebook.fbml.setRefHandle',
        array('handle' => $handle, 'fbml' => $fbml));
  }

  /**
   * Register custom tags for the application. Custom tags can be used
   * to extend the set of tags available to applications in FBML
   * markup.
   *
   * Before you call this function,
   * make sure you read the full documentation at
   *
   * http://wiki.developers.facebook.com/index.php/Fbml.RegisterCustomTags
   *
   * IMPORTANT: This function overwrites the values of
   * existing tags if the names match. Use this function with care because
   * it may break the FBML of any application that is using the
   * existing version of the tags.
   *
   * @param mixed $tags an array of tag objects (the full description is on the
   *   wiki page)
   *
   * @return int  the number of tags that were registered
   */
  public function &fbml_registerCustomTags($tags) {
    $tags = json_encode($tags);
    return $this->call_method('facebook.fbml.registerCustomTags',
                              array('tags' => $tags));
  }

  /**
   * Get the custom tags for an application. If $app_id
   * is not specified, the calling app's tags are returned.
   * If $app_id is different from the id of the calling app,
   * only the app's public tags are returned.
   * The return value is an array of the same type as
   * the $tags parameter of fbml_registerCustomTags().
   *
   * @param int $app_id the application's id (optional)
   *
   * @return mixed  an array containing the custom tag  objects
   */
  public function &fbml_getCustomTags($app_id = null) {
    return $this->call_method('facebook.fbml.getCustomTags',
                              array('app_id' => $app_id));
  }


  /**
   * Delete custom tags the application has registered. If
   * $tag_names is null, all the application's custom tags will be
   * deleted.
   *
   * IMPORTANT: If your application has registered public tags
   * that other applications may be using, don't delete those tags!
   * Doing so can break the FBML ofapplications that are using them.
   *
   * @param array $tag_names the names of the tags to delete (optinal)
   * @return bool true on success
   */
  public function &fbml_deleteCustomTags($tag_names = null) {
    return $this->call_method('facebook.fbml.deleteCustomTags',
                              array('tag_names' => json_encode($tag_names)));
  }



  /**
   * This method is deprecated for calls made on behalf of users. This method
   * works only for publishing stories on a Facebook Page that has installed
   * your application. To publish stories to a user's profile, use
   * feed.publishUserAction instead.
   *
   * For more details on this call, please visit the wiki page:
   *
   * http://wiki.developers.facebook.com/index.php/Feed.publishTemplatizedAction
   */
  public function &feed_publishTemplatizedAction($title_template,
                                                 $title_data,
                                                 $body_template,
                                                 $body_data,
                                                 $body_general,
                                                 $image_1=null,
                                                 $image_1_link=null,
                                                 $image_2=null,
                                                 $image_2_link=null,
                                                 $image_3=null,
                                                 $image_3_link=null,
                                                 $image_4=null,
                                                 $image_4_link=null,
                                                 $target_ids='',
                                                 $page_actor_id=null) {
    return $this->call_method('facebook.feed.publishTemplatizedAction',
      array('title_template' => $title_template,
            'title_data' => $title_data,
            'body_template' => $body_template,
            'body_data' => $body_data,
            'body_general' => $body_general,
            'image_1' => $image_1,
            'image_1_link' => $image_1_link,
            'image_2' => $image_2,
            'image_2_link' => $image_2_link,
            'image_3' => $image_3,
            'image_3_link' => $image_3_link,
            'image_4' => $image_4,
            'image_4_link' => $image_4_link,
            'target_ids' => $target_ids,
            'page_actor_id' => $page_actor_id));
  }

  /**
   * Registers a template bundle.  Template bundles are somewhat involved, so
   * it's recommended you check out the wiki for more details:
   *
   *  http://wiki.developers.facebook.com/index.php/Feed.registerTemplateBundle
   *
   * @return string  A template bundle id
   */
  public function &feed_registerTemplateBundle($one_line_story_templates,
                                               $short_story_templates = array(),
                                               $full_story_template = null,
                                               $action_links = array()) {

    $one_line_story_templates = json_encode($one_line_story_templates);

    if (!empty($short_story_templates)) {
      $short_story_templates = json_encode($short_story_templates);
    }

    if (isset($full_story_template)) {
      $full_story_template = json_encode($full_story_template);
    }

    if (isset($action_links)) {
      $action_links = json_encode($action_links);
    }

    return $this->call_method('facebook.feed.registerTemplateBundle',
        array('one_line_story_templates' => $one_line_story_templates,
              'short_story_templates' => $short_story_templates,
              'full_story_template' => $full_story_template,
              'action_links' => $action_links));
  }

  /**
   * Retrieves the full list of active template bundles registered by the
   * requesting application.
   *
   * @return array  An array of template bundles
   */
  public function &feed_getRegisteredTemplateBundles() {
    return $this->call_method('facebook.feed.getRegisteredTemplateBundles',
        array());
  }

  /**
   * Retrieves information about a specified template bundle previously
   * registered by the requesting application.
   *
   * @param string $template_bundle_id  The template bundle id
   *
   * @return array  Template bundle
   */
  public function &feed_getRegisteredTemplateBundleByID($template_bundle_id) {
    return $this->call_method('facebook.feed.getRegisteredTemplateBundleByID',
        array('template_bundle_id' => $template_bundle_id));
  }

  /**
   * Deactivates a previously registered template bundle.
   *
   * @param string $template_bundle_id  The template bundle id
   *
   * @return bool  true on success
   */
  public function &feed_deactivateTemplateBundleByID($template_bundle_id) {
    return $this->call_method('facebook.feed.deactivateTemplateBundleByID',
        array('template_bundle_id' => $template_bundle_id));
  }

  const STORY_SIZE_ONE_LINE = 1;
  const STORY_SIZE_SHORT = 2;
  const STORY_SIZE_FULL = 4;

  /**
   * Publishes a story on behalf of the user owning the session, using the
   * specified template bundle. This method requires an active session key in
   * order to be called.
   *
   * The parameters to this method ($templata_data in particular) are somewhat
   * involved.  It's recommended you visit the wiki for details:
   *
   *  http://wiki.developers.facebook.com/index.php/Feed.publishUserAction
   *
   * @param int $template_bundle_id  A template bundle id previously registered
   * @param array $template_data     See wiki article for syntax
   * @param array $target_ids        (Optional) An array of friend uids of the
   *                                 user who shared in this action.
   * @param string $body_general     (Optional) Additional markup that extends
   *                                 the body of a short story.
   * @param int $story_size          (Optional) A story size (see above)
   *
   * @return bool  true on success
   */
  public function &feed_publishUserAction(
      $template_bundle_id, $template_data, $target_ids='', $body_general='',
      $story_size=FacebookRestClient::STORY_SIZE_ONE_LINE) {

    if (is_array($template_data)) {
      $template_data = json_encode($template_data);
    } // allow client to either pass in JSON or an assoc that we JSON for them

    if (is_array($target_ids)) {
      $target_ids = json_encode($target_ids);
      $target_ids = trim($target_ids, "[]"); // we don't want square brackets
    }

    return $this->call_method('facebook.feed.publishUserAction',
        array('template_bundle_id' => $template_bundle_id,
              'template_data' => $template_data,
              'target_ids' => $target_ids,
              'body_general' => $body_general,
              'story_size' => $story_size));
  }

  /**
   * For the current user, retrieves stories generated by the user's friends
   * while using this application.  This can be used to easily create a
   * "News Feed" like experience.
   *
   * @return array  An array of feed story objects.
   */
  public function &feed_getAppFriendStories() {
    return $this->call_method('facebook.feed.getAppFriendStories', array());
  }

  /**
   * Makes an FQL query.  This is a generalized way of accessing all the data
   * in the API, as an alternative to most of the other method calls.  More
   * info at http://developers.facebook.com/documentation.php?v=1.0&doc=fql
   *
   * @param string $query  the query to evaluate
   *
   * @return array  generalized array representing the results
   */
  public function &fql_query($query) {
    return $this->call_method('facebook.fql.query',
      array('query' => $query));
  }

  /**
   * Returns whether or not pairs of users are friends.
   * Note that the Facebook friend relationship is symmetric.
   *
   * @param array $uids1  array of ids (id_1, id_2,...) of some length X
   * @param array $uids2  array of ids (id_A, id_B,...) of SAME length X
   *
   * @return array  An array with uid1, uid2, and bool if friends, e.g.:
   *   array(0 => array('uid1' => id_1, 'uid2' => id_A, 'are_friends' => 1),
   *         1 => array('uid1' => id_2, 'uid2' => id_B, 'are_friends' => 0)
   *         ...)
   * @error
   *    API_EC_PARAM_USER_ID_LIST
   */
  public function &friends_areFriends($uids1, $uids2) {
    return $this->call_method('facebook.friends.areFriends',
        array('uids1' => $uids1, 'uids2' => $uids2));
  }

  /**
   * Returns the friends of the current session user.
   *
   * @param int $flid  (Optional) Only return friends on this friend list.
   * @param int $uid   (Optional) Return friends for this user.
   *
   * @return array  An array of friends
   */
  public function &friends_get($flid=null, $uid = null) {
    if (isset($this->friends_list)) {
      return $this->friends_list;
    }
    $params = array();
    if (!$uid && isset($this->canvas_user)) {
      $uid = $this->canvas_user;
    }
    if ($uid) {
      $params['uid'] = $uid;
    }
    if ($flid) {
      $params['flid'] = $flid;
    }
    return $this->call_method('facebook.friends.get', $params);

  }

  /**
   * Returns the set of friend lists for the current session user.
   *
   * @return array  An array of friend list objects
   */
  public function &friends_getLists() {
    return $this->call_method('facebook.friends.getLists', array());
  }

  /**
   * Returns the friends of the session user, who are also users
   * of the calling application.
   *
   * @return array  An array of friends also using the app
   */
  public function &friends_getAppUsers() {
    return $this->call_method('facebook.friends.getAppUsers', array());
  }

  /**
   * Returns groups according to the filters specified.
   *
   * @param int $uid     (Optional) User associated with groups.  A null
   *                     parameter will default to the session user.
   * @param array $gids  (Optional) Group ids to query. A null parameter will
   *                     get all groups for the user.
   *
   * @return array  An array of group objects
   */
  public function &groups_get($uid, $gids) {
    return $this->call_method('facebook.groups.get',
        array('uid' => $uid,
              'gids' => $gids));
  }

  /**
   * Returns the membership list of a group.
   *
   * @param int $gid  Group id
   *
   * @return array  An array with four membership lists, with keys 'members',
   *                'admins', 'officers', and 'not_replied'
   */
  public function &groups_getMembers($gid) {
    return $this->call_method('facebook.groups.getMembers',
      array('gid' => $gid));
  }

  /**
   * Returns cookies according to the filters specified.
   *
   * @param int $uid     User for which the cookies are needed.
   * @param string $name (Optional) A null parameter will get all cookies
   *                     for the user.
   *
   * @return array  Cookies!  Nom nom nom nom nom.
   */
  public function data_getCookies($uid, $name) {
    return $this->call_method('facebook.data.getCookies',
        array('uid' => $uid,
              'name' => $name));
  }

  /**
   * Sets cookies according to the params specified.
   *
   * @param int $uid       User for which the cookies are needed.
   * @param string $name   Name of the cookie
   * @param string $value  (Optional) if expires specified and is in the past
   * @param int $expires   (Optional) Expiry time
   * @param string $path   (Optional) Url path to associate with (default is /)
   *
   * @return bool  true on success
   */
  public function data_setCookie($uid, $name, $value, $expires, $path) {
    return $this->call_method('facebook.data.setCookie',
        array('uid' => $uid,
              'name' => $name,
              'value' => $value,
              'expires' => $expires,
              'path' => $path));
  }

  /**
   * Permissions API
   */

  /**
   * Checks API-access granted by self to the specified application.
   *
   * @param string $permissions_apikey  Other application key
   *
   * @return array  API methods/namespaces which are allowed access
   */
  public function permissions_checkGrantedApiAccess($permissions_apikey) {
    return $this->call_method('facebook.permissions.checkGrantedApiAccess',
        array('permissions_apikey' => $permissions_apikey));
  }

  /**
   * Checks API-access granted to self by the specified application.
   *
   * @param string $permissions_apikey  Other application key
   *
   * @return array  API methods/namespaces which are allowed access
   */
  public function permissions_checkAvailableApiAccess($permissions_apikey) {
    return $this->call_method('facebook.permissions.checkAvailableApiAccess',
        array('permissions_apikey' => $permissions_apikey));
  }

  /**
   * Grant API-access to the specified methods/namespaces to the specified
   * application.
   *
   * @param string $permissions_apikey  Other application key
   * @param array(string) $method_arr   (Optional) API methods/namespaces
   *                                    allowed
   *
   * @return array  API methods/namespaces which are allowed access
   */
  public function permissions_grantApiAccess($permissions_apikey, $method_arr) {
    return $this->call_method('facebook.permissions.grantApiAccess',
        array('permissions_apikey' => $permissions_apikey,
              'method_arr' => $method_arr));
  }

  /**
   * Revoke API-access granted to the specified application.
   *
   * @param string $permissions_apikey  Other application key
   *
   * @return bool  true on success
   */
  public function permissions_revokeApiAccess($permissions_apikey) {
    return $this->call_method('facebook.permissions.revokeApiAccess',
        array('permissions_apikey' => $permissions_apikey));
  }

  /**
   * Returns the outstanding notifications for the session user.
   *
   * @return array An assoc array of notification count objects for
   *               'messages', 'pokes' and 'shares', a uid list of
   *               'friend_requests', a gid list of 'group_invites',
   *               and an eid list of 'event_invites'
   */
  public function &notifications_get() {
    return $this->call_method('facebook.notifications.get', array());
  }

  /**
   * Sends a notification to the specified users.
   *
   * @return A comma separated list of successful recipients
   * @error
   *    API_EC_PARAM_USER_ID_LIST
   */
  public function &notifications_send($to_ids, $notification, $type) {
    return $this->call_method('facebook.notifications.send',
        array('to_ids' => $to_ids,
              'notification' => $notification,
              'type' => $type));
  }

  /**
   * Sends an email to the specified user of the application.
   *
   * @param array $recipients  id of the recipients
   * @param string $subject    subject of the email
   * @param string $text       (plain text) body of the email
   * @param string $fbml       fbml markup for an html version of the email
   *
   * @return string  A comma separated list of successful recipients
   * @error
   *    API_EC_PARAM_USER_ID_LIST
   */
  public function &notifications_sendEmail($recipients,
                                           $subject,
                                           $text,
                                           $fbml) {
    return $this->call_method('facebook.notifications.sendEmail',
        array('recipients' => $recipients,
              'subject' => $subject,
              'text' => $text,
              'fbml' => $fbml));
  }

  /**
   * Returns the requested info fields for the requested set of pages.
   *
   * @param array  $page_ids  an array of page ids
   * @param array  $fields    an array of strings describing the info fields
   *                          desired
   * @param int    $uid       (Optional) limit results to pages of which this
   *                          user is a fan.
   * @param string type       limits results to a particular type of page.
   *
   * @return array  An array of pages
   */
  public function &pages_getInfo($page_ids, $fields, $uid, $type) {
    return $this->call_method('facebook.pages.getInfo',
        array('page_ids' => $page_ids,
              'fields' => $fields,
              'uid' => $uid,
              'type' => $type));
  }

  /**
   * Returns true if the given user is an admin for the passed page.
   *
   * @param int $page_id  target page id
   * @param int $uid      (Optional) user id (defaults to the logged-in user)
   *
   * @return bool  true on success
   */
  public function &pages_isAdmin($page_id, $uid = null) {
    return $this->call_method('facebook.pages.isAdmin',
        array('page_id' => $page_id,
              'uid' => $uid));
  }

  /**
   * Returns whether or not the given page has added the application.
   *
   * @param int $page_id  target page id
   *
   * @return bool  true on success
   */
  public function &pages_isAppAdded($page_id) {
    return $this->call_method('facebook.pages.isAppAdded',
        array('page_id' => $page_id));
  }

  /**
   * Returns true if logged in user is a fan for the passed page.
   *
   * @param int $page_id target page id
   * @param int $uid user to compare.  If empty, the logged in user.
   *
   * @return bool  true on success
   */
  public function &pages_isFan($page_id, $uid = null) {
    return $this->call_method('facebook.pages.isFan',
        array('page_id' => $page_id,
              'uid' => $uid));
  }

  /**
   * Adds a tag with the given information to a photo. See the wiki for details:
   *
   *  http://wiki.developers.facebook.com/index.php/Photos.addTag
   *
   * @param int $pid          The ID of the photo to be tagged
   * @param int $tag_uid      The ID of the user being tagged. You must specify
   *                          either the $tag_uid or the $tag_text parameter
   *                          (unless $tags is specified).
   * @param string $tag_text  Some text identifying the person being tagged.
   *                          You must specify either the $tag_uid or $tag_text
   *                          parameter (unless $tags is specified).
   * @param float $x          The horizontal position of the tag, as a
   *                          percentage from 0 to 100, from the left of the
   *                          photo.
   * @param float $y          The vertical position of the tag, as a percentage
   *                          from 0 to 100, from the top of the photo.
   * @param array $tags       (Optional) An array of maps, where each map
   *                          can contain the tag_uid, tag_text, x, and y
   *                          parameters defined above.  If specified, the
   *                          individual arguments are ignored.
   * @param int $owner_uid    (Optional)  The user ID of the user whose photo
   *                          you are tagging. If this parameter is not
   *                          specified, then it defaults to the session user.
   *
   * @return bool  true on success
   */
  public function &photos_addTag($pid,
                                 $tag_uid,
                                 $tag_text,
                                 $x,
                                 $y,
                                 $tags,
                                 $owner_uid=0) {
    return $this->call_method('facebook.photos.addTag',
        array('pid' => $pid,
              'tag_uid' => $tag_uid,
              'tag_text' => $tag_text,
              'x' => $x,
              'y' => $y,
              'tags' => json_encode($tags),
              'owner_uid' => $this->get_uid($owner_uid)));
  }

  /**
   * Creates and returns a new album owned by the specified user or the current
   * session user.
   *
   * @param string $name         The name of the album.
   * @param string $description  (Optional) A description of the album.
   * @param string $location     (Optional) A description of the location.
   * @param string $visible      (Optional) A privacy setting for the album.
   *                             One of 'friends', 'friends-of-friends',
   *                             'networks', or 'everyone'.  Default 'everyone'.
   * @param int $uid             (Optional) User id for creating the album; if
   *                             not specified, the session user is used.
   *
   * @return array  An album object
   */
  public function &photos_createAlbum($name,
                                      $description='',
                                      $location='',
                                      $visible='',
                                      $uid=0) {
    return $this->call_method('facebook.photos.createAlbum',
        array('name' => $name,
              'description' => $description,
              'location' => $location,
              'visible' => $visible,
              'uid' => $this->get_uid($uid)));
  }

  /**
   * Returns photos according to the filters specified.
   *
   * @param int $subj_id  (Optional) Filter by uid of user tagged in the photos.
   * @param int $aid      (Optional) Filter by an album, as returned by
   *                      photos_getAlbums.
   * @param array $pids   (Optional) Restrict to a list of pids
   *
   * Note that at least one of these parameters needs to be specified, or an
   * error is returned.
   *
   * @return array  An array of photo objects.
   */
  public function &photos_get($subj_id, $aid, $pids) {
    return $this->call_method('facebook.photos.get',
      array('subj_id' => $subj_id, 'aid' => $aid, 'pids' => $pids));
  }

  /**
   * Returns the albums created by the given user.
   *
   * @param int $uid     (Optional) The uid of the user whose albums you want.
   *                     A null will return the albums of the session user.
   * @param array $aids  (Optional) A list of aids to restrict the query.
   *
   * Note that at least one of the (uid, aids) parameters must be specified.
   *
   * @returns an array of album objects.
   */
  public function &photos_getAlbums($uid, $aids) {
    return $this->call_method('facebook.photos.getAlbums',
      array('uid' => $uid,
            'aids' => $aids));
  }

  /**
   * Returns the tags on all photos specified.
   *
   * @param string $pids  A list of pids to query
   *
   * @return array  An array of photo tag objects, which include pid,
   *                subject uid, and two floating-point numbers (xcoord, ycoord)
   *                for tag pixel location.
   */
  public function &photos_getTags($pids) {
    return $this->call_method('facebook.photos.getTags',
      array('pids' => $pids));
  }

  /**
   * Uploads a photo.
   *
   * @param string $file     The location of the photo on the local filesystem.
   * @param int $aid         (Optional) The album into which to upload the
   *                         photo.
   * @param string $caption  (Optional) A caption for the photo.
   * @param int uid          (Optional) The user ID of the user whose photo you
   *                         are uploading
   *
   * @return array  An array of user objects
   */
  public function photos_upload($file, $aid=null, $caption=null, $uid=null) {
    return $this->call_upload_method('facebook.photos.upload',
                                     array('aid' => $aid,
                                           'caption' => $caption,
                                           'uid' => $uid),
                                     $file);
  }

  /**
   * Returns the requested info fields for the requested set of users.
   *
   * @param array $uids    An array of user ids
   * @param array $fields  An array of info field names desired
   *
   * @return array  An array of user objects
   */
  public function &users_getInfo($uids, $fields) {
    return $this->call_method('facebook.users.getInfo',
        array('uids' => $uids, 'fields' => $fields));
  }

  /**
   * Returns the requested info fields for the requested set of users. A
   * session key must not be specified. Only data about users that have
   * authorized your application will be returned.
   *
   * Check the wiki for fields that can be queried through this API call.
   * Data returned from here should not be used for rendering to application
   * users, use users.getInfo instead, so that proper privacy rules will be
   * applied.
   *
   * @param array $uids    An array of user ids
   * @param array $fields  An array of info field names desired
   *
   * @return array  An array of user objects
   */
  public function &users_getStandardInfo($uids, $fields) {
    return $this->call_method('facebook.users.getStandardInfo',
        array('uids' => $uids, 'fields' => $fields));
  }

  /**
   * Returns the user corresponding to the current session object.
   *
   * @return integer  User id
   */
  public function &users_getLoggedInUser() {
    return $this->call_method('facebook.users.getLoggedInUser', array());
  }

  /**
   * Returns 1 if the user has the specified permission, 0 otherwise.
   * http://wiki.developers.facebook.com/index.php/Users.hasAppPermission
   *
   * @return integer  1 or 0
   */
  public function &users_hasAppPermission($ext_perm, $uid=null) {
    return $this->call_method('facebook.users.hasAppPermission',
        array('ext_perm' => $ext_perm, 'uid' => $uid));
  }

  /**
   * Returns whether or not the user corresponding to the current
   * session object has the give the app basic authorization.
   *
   * @return boolean  true if the user has authorized the app
   */
  public function &users_isAppUser($uid=null) {
    if ($uid === null && isset($this->is_user)) {
      return $this->is_user;
    }

    return $this->call_method('facebook.users.isAppUser', array('uid' => $uid));
  }

  /**
   * Sets the users' current status message. Message does NOT contain the
   * word "is" , so make sure to include a verb.
   *
   * Example: setStatus("is loving the API!")
   * will produce the status "Luke is loving the API!"
   *
   * @param string $status                text-only message to set
   * @param int    $uid                   user to set for (defaults to the
   *                                      logged-in user)
   * @param bool   $clear                 whether or not to clear the status,
   *                                      instead of setting it
   * @param bool   $status_includes_verb  if true, the word "is" will *not* be
   *                                      prepended to the status message
   *
   * @return boolean
   */
  public function &users_setStatus($status,
                                   $uid = null,
                                   $clear = false,
                                   $status_includes_verb = true) {
    $args = array(
      'status' => $status,
      'uid' => $uid,
      'clear' => $clear,
      'status_includes_verb' => $status_includes_verb,
    );
    return $this->call_method('facebook.users.setStatus', $args);
  }

  /**
   * Sets the FBML for the profile of the user attached to this session.
   *
   * @param   string   $markup           The FBML that describes the profile
   *                                     presence of this app for the user
   * @param   int      $uid              The user
   * @param   string   $profile          Profile FBML
   * @param   string   $profile_action   Profile action FBML (deprecated)
   * @param   string   $mobile_profile   Mobile profile FBML
   * @param   string   $profile_main     Main Tab profile FBML
   *
   * @return  array  A list of strings describing any compile errors for the
   *                 submitted FBML
   */
  function profile_setFBML($markup,
                           $uid=null,
                           $profile='',
                           $profile_action='',
                           $mobile_profile='',
                           $profile_main='') {
    return $this->call_method('facebook.profile.setFBML',
        array('markup' => $markup,
              'uid' => $uid,
              'profile' => $profile,
              'profile_action' => $profile_action,
              'mobile_profile' => $mobile_profile,
              'profile_main' => $profile_main));
  }

  /**
   * Gets the FBML for the profile box that is currently set for a user's
   * profile (your application set the FBML previously by calling the
   * profile.setFBML method).
   *
   * @param int $uid   (Optional) User id to lookup; defaults to session.
   * @param int $type  (Optional) 1 for original style, 2 for profile_main boxes
   *
   * @return string  The FBML
   */
  public function &profile_getFBML($uid=null, $type=null) {
    return $this->call_method('facebook.profile.getFBML',
        array('uid' => $uid,
              'type' => $type));
  }

  /**
   * Returns the specified user's application info section for the calling
   * application. These info sections have either been set via a previous
   * profile.setInfo call or by the user editing them directly.
   *
   * @param int $uid  (Optional) User id to lookup; defaults to session.
   *
   * @return array  Info fields for the current user.  See wiki for structure:
   *
   *  http://wiki.developers.facebook.com/index.php/Profile.getInfo
   *
   */
  public function &profile_getInfo($uid=null) {
    return $this->call_method('facebook.profile.getInfo',
        array('uid' => $uid));
  }

  /**
   * Returns the options associated with the specified info field for an
   * application info section.
   *
   * @param string $field  The title of the field
   *
   * @return array  An array of info options.
   */
  public function &profile_getInfoOptions($field) {
    return $this->call_method('facebook.profile.getInfoOptions',
        array('field' => $field));
  }

  /**
   * Configures an application info section that the specified user can install
   * on the Info tab of her profile.  For details on the structure of an info
   * field, please see:
   *
   *  http://wiki.developers.facebook.com/index.php/Profile.setInfo
   *
   * @param string $title       Title / header of the info section
   * @param int $type           1 for text-only, 5 for thumbnail views
   * @param array $info_fields  An array of info fields. See wiki for details.
   * @param int $uid            (Optional)
   *
   * @return bool  true on success
   */
  public function &profile_setInfo($title, $type, $info_fields, $uid=null) {
    return $this->call_method('facebook.profile.setInfo',
        array('uid' => $uid,
              'type' => $type,
              'title'   => $title,
              'info_fields' => json_encode($info_fields)));
  }

  /**
   * Specifies the objects for a field for an application info section. These
   * options populate the typeahead for a thumbnail.
   *
   * @param string $field   The title of the field
   * @param array $options  An array of items for a thumbnail, including
   *                        'label', 'link', and optionally 'image',
   *                        'description' and 'sublabel'
   *
   * @return bool  true on success
   */
  public function profile_setInfoOptions($field, $options) {
    return $this->call_method('facebook.profile.setInfoOptions',
        array('field'   => $field,
              'options' => json_encode($options)));
  }

  /**
   * Get all the marketplace categories.
   *
   * @return array  A list of category names
   */
  function marketplace_getCategories() {
    return $this->call_method('facebook.marketplace.getCategories',
        array());
  }

  /**
   * Get all the marketplace subcategories for a particular category.
   *
   * @param  category  The category for which we are pulling subcategories
   *
   * @return array A list of subcategory names
   */
  function marketplace_getSubCategories($category) {
    return $this->call_method('facebook.marketplace.getSubCategories',
        array('category' => $category));
  }

  /**
   * Get listings by either listing_id or user.
   *
   * @param listing_ids   An array of listing_ids (optional)
   * @param uids          An array of user ids (optional)
   *
   * @return array  The data for matched listings
   */
  function marketplace_getListings($listing_ids, $uids) {
    return $this->call_method('facebook.marketplace.getListings',
        array('listing_ids' => $listing_ids, 'uids' => $uids));
  }

  /**
   * Search for Marketplace listings.  All arguments are optional, though at
   * least one must be filled out to retrieve results.
   *
   * @param category     The category in which to search (optional)
   * @param subcategory  The subcategory in which to search (optional)
   * @param query        A query string (optional)
   *
   * @return array  The data for matched listings
   */
  function marketplace_search($category, $subcategory, $query) {
    return $this->call_method('facebook.marketplace.search',
        array('category' => $category,
              'subcategory' => $subcategory,
              'query' => $query));
  }

  /**
   * Remove a listing from Marketplace.
   *
   * @param listing_id  The id of the listing to be removed
   * @param status      'SUCCESS', 'NOT_SUCCESS', or 'DEFAULT'
   *
   * @return bool  True on success
   */
  function marketplace_removeListing($listing_id,
                                     $status='DEFAULT',
                                     $uid=null) {
    return $this->call_method('facebook.marketplace.removeListing',
        array('listing_id' => $listing_id,
              'status' => $status,
              'uid' => $uid));
  }

  /**
   * Create/modify a Marketplace listing for the loggedinuser.
   *
   * @param int              listing_id  The id of a listing to be modified, 0
   *                                     for a new listing.
   * @param show_on_profile  bool        Should we show this listing on the
   *                                     user's profile
   * @param listing_attrs    array       An array of the listing data
   *
   * @return int  The listing_id (unchanged if modifying an existing listing).
   */
  function marketplace_createListing($listing_id,
                                     $show_on_profile,
                                     $attrs,
                                     $uid=null) {
    return $this->call_method('facebook.marketplace.createListing',
        array('listing_id' => $listing_id,
              'show_on_profile' => $show_on_profile,
              'listing_attrs' => json_encode($attrs),
              'uid' => $uid));
  }

  /////////////////////////////////////////////////////////////////////////////
  // Data Store API

  /**
   * Set a user preference.
   *
   * @param  pref_id    preference identifier (0-200)
   * @param  value      preferece's value
   * @param  uid        the user id (defaults to current session user)
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   *    API_EC_PERMISSION_OTHER_USER
   */
  public function &data_setUserPreference($pref_id, $value, $uid = null) {
    return $this->call_method('facebook.data.setUserPreference',
       array('pref_id' => $pref_id,
             'value' => $value,
             'uid' => $this->get_uid($uid)));
  }

  /**
   * Set a user's all preferences for this application.
   *
   * @param  values     preferece values in an associative arrays
   * @param  replace    whether to replace all existing preferences or
   *                    merge into them.
   * @param  uid        the user id (defaults to current session user)
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   *    API_EC_PERMISSION_OTHER_USER
   */
  public function &data_setUserPreferences($values,
                                           $replace = false,
                                           $uid = null) {
    return $this->call_method('facebook.data.setUserPreferences',
       array('values' => json_encode($values),
             'replace' => $replace,
             'uid' => $this->get_uid($uid)));
  }

  /**
   * Get a user preference.
   *
   * @param  pref_id    preference identifier (0-200)
   * @param  uid        the user id (defaults to current session user)
   * @return            preference's value
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   *    API_EC_PERMISSION_OTHER_USER
   */
  public function &data_getUserPreference($pref_id, $uid = null) {
    return $this->call_method('facebook.data.getUserPreference',
       array('pref_id' => $pref_id,
             'uid' => $this->get_uid($uid)));
  }

  /**
   * Get a user preference.
   *
   * @param  uid        the user id (defaults to current session user)
   * @return            preference values
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   *    API_EC_PERMISSION_OTHER_USER
   */
  public function &data_getUserPreferences($uid = null) {
    return $this->call_method('facebook.data.getUserPreferences',
       array('uid' => $this->get_uid($uid)));
  }

  /**
   * Create a new object type.
   *
   * @param  name       object type's name
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_createObjectType($name) {
    return $this->call_method('facebook.data.createObjectType',
       array('name' => $name));
  }

  /**
   * Delete an object type.
   *
   * @param  obj_type       object type's name
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_dropObjectType($obj_type) {
    return $this->call_method('facebook.data.dropObjectType',
       array('obj_type' => $obj_type));
  }

  /**
   * Rename an object type.
   *
   * @param  obj_type       object type's name
   * @param  new_name       new object type's name
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_renameObjectType($obj_type, $new_name) {
    return $this->call_method('facebook.data.renameObjectType',
       array('obj_type' => $obj_type,
             'new_name' => $new_name));
  }

  /**
   * Add a new property to an object type.
   *
   * @param  obj_type       object type's name
   * @param  prop_name      name of the property to add
   * @param  prop_type      1: integer; 2: string; 3: text blob
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_defineObjectProperty($obj_type,
                                             $prop_name,
                                             $prop_type) {
    return $this->call_method('facebook.data.defineObjectProperty',
       array('obj_type' => $obj_type,
             'prop_name' => $prop_name,
             'prop_type' => $prop_type));
  }

  /**
   * Remove a previously defined property from an object type.
   *
   * @param  obj_type      object type's name
   * @param  prop_name     name of the property to remove
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_undefineObjectProperty($obj_type, $prop_name) {
    return $this->call_method('facebook.data.undefineObjectProperty',
       array('obj_type' => $obj_type,
             'prop_name' => $prop_name));
  }

  /**
   * Rename a previously defined property of an object type.
   *
   * @param  obj_type      object type's name
   * @param  prop_name     name of the property to rename
   * @param  new_name      new name to use
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_renameObjectProperty($obj_type, $prop_name,
                                            $new_name) {
    return $this->call_method('facebook.data.renameObjectProperty',
       array('obj_type' => $obj_type,
             'prop_name' => $prop_name,
             'new_name' => $new_name));
  }

  /**
   * Retrieve a list of all object types that have defined for the application.
   *
   * @return               a list of object type names
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getObjectTypes() {
    return $this->call_method('facebook.data.getObjectTypes', array());
  }

  /**
   * Get definitions of all properties of an object type.
   *
   * @param obj_type       object type's name
   * @return               pairs of property name and property types
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getObjectType($obj_type) {
    return $this->call_method('facebook.data.getObjectType',
       array('obj_type' => $obj_type));
  }

  /**
   * Create a new object.
   *
   * @param  obj_type      object type's name
   * @param  properties    (optional) properties to set initially
   * @return               newly created object's id
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_createObject($obj_type, $properties = null) {
    return $this->call_method('facebook.data.createObject',
       array('obj_type' => $obj_type,
             'properties' => json_encode($properties)));
  }

  /**
   * Update an existing object.
   *
   * @param  obj_id        object's id
   * @param  properties    new properties
   * @param  replace       true for replacing existing properties;
   *                       false for merging
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_updateObject($obj_id, $properties, $replace = false) {
    return $this->call_method('facebook.data.updateObject',
       array('obj_id' => $obj_id,
             'properties' => json_encode($properties),
             'replace' => $replace));
  }

  /**
   * Delete an existing object.
   *
   * @param  obj_id        object's id
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_deleteObject($obj_id) {
    return $this->call_method('facebook.data.deleteObject',
       array('obj_id' => $obj_id));
  }

  /**
   * Delete a list of objects.
   *
   * @param  obj_ids       objects to delete
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_deleteObjects($obj_ids) {
    return $this->call_method('facebook.data.deleteObjects',
       array('obj_ids' => json_encode($obj_ids)));
  }

  /**
   * Get a single property value of an object.
   *
   * @param  obj_id        object's id
   * @param  prop_name     individual property's name
   * @return               individual property's value
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getObjectProperty($obj_id, $prop_name) {
    return $this->call_method('facebook.data.getObjectProperty',
       array('obj_id' => $obj_id,
             'prop_name' => $prop_name));
  }

  /**
   * Get properties of an object.
   *
   * @param  obj_id      object's id
   * @param  prop_names  (optional) properties to return; null for all.
   * @return             specified properties of an object
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getObject($obj_id, $prop_names = null) {
    return $this->call_method('facebook.data.getObject',
       array('obj_id' => $obj_id,
             'prop_names' => json_encode($prop_names)));
  }

  /**
   * Get properties of a list of objects.
   *
   * @param  obj_ids     object ids
   * @param  prop_names  (optional) properties to return; null for all.
   * @return             specified properties of an object
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getObjects($obj_ids, $prop_names = null) {
    return $this->call_method('facebook.data.getObjects',
       array('obj_ids' => json_encode($obj_ids),
             'prop_names' => json_encode($prop_names)));
  }

  /**
   * Set a single property value of an object.
   *
   * @param  obj_id        object's id
   * @param  prop_name     individual property's name
   * @param  prop_value    new value to set
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_setObjectProperty($obj_id, $prop_name,
                                         $prop_value) {
    return $this->call_method('facebook.data.setObjectProperty',
       array('obj_id' => $obj_id,
             'prop_name' => $prop_name,
             'prop_value' => $prop_value));
  }

  /**
   * Read hash value by key.
   *
   * @param  obj_type      object type's name
   * @param  key           hash key
   * @param  prop_name     (optional) individual property's name
   * @return               hash value
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getHashValue($obj_type, $key, $prop_name = null) {
    return $this->call_method('facebook.data.getHashValue',
       array('obj_type' => $obj_type,
             'key' => $key,
             'prop_name' => $prop_name));
  }

  /**
   * Write hash value by key.
   *
   * @param  obj_type      object type's name
   * @param  key           hash key
   * @param  value         hash value
   * @param  prop_name     (optional) individual property's name
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_setHashValue($obj_type,
                                     $key,
                                     $value,
                                     $prop_name = null) {
    return $this->call_method('facebook.data.setHashValue',
       array('obj_type' => $obj_type,
             'key' => $key,
             'value' => $value,
             'prop_name' => $prop_name));
  }

  /**
   * Increase a hash value by specified increment atomically.
   *
   * @param  obj_type      object type's name
   * @param  key           hash key
   * @param  prop_name     individual property's name
   * @param  increment     (optional) default is 1
   * @return               incremented hash value
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_incHashValue($obj_type,
                                     $key,
                                     $prop_name,
                                     $increment = 1) {
    return $this->call_method('facebook.data.incHashValue',
       array('obj_type' => $obj_type,
             'key' => $key,
             'prop_name' => $prop_name,
             'increment' => $increment));
  }

  /**
   * Remove a hash key and its values.
   *
   * @param  obj_type    object type's name
   * @param  key         hash key
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_removeHashKey($obj_type, $key) {
    return $this->call_method('facebook.data.removeHashKey',
       array('obj_type' => $obj_type,
             'key' => $key));
  }

  /**
   * Remove hash keys and their values.
   *
   * @param  obj_type    object type's name
   * @param  keys        hash keys
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_removeHashKeys($obj_type, $keys) {
    return $this->call_method('facebook.data.removeHashKeys',
       array('obj_type' => $obj_type,
             'keys' => json_encode($keys)));
  }

  /**
   * Define an object association.
   *
   * @param  name        name of this association
   * @param  assoc_type  1: one-way 2: two-way symmetric 3: two-way asymmetric
   * @param  assoc_info1 needed info about first object type
   * @param  assoc_info2 needed info about second object type
   * @param  inverse     (optional) name of reverse association
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_defineAssociation($name, $assoc_type, $assoc_info1,
                                         $assoc_info2, $inverse = null) {
    return $this->call_method('facebook.data.defineAssociation',
       array('name' => $name,
             'assoc_type' => $assoc_type,
             'assoc_info1' => json_encode($assoc_info1),
             'assoc_info2' => json_encode($assoc_info2),
             'inverse' => $inverse));
  }

  /**
   * Undefine an object association.
   *
   * @param  name        name of this association
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_undefineAssociation($name) {
    return $this->call_method('facebook.data.undefineAssociation',
       array('name' => $name));
  }

  /**
   * Rename an object association or aliases.
   *
   * @param  name        name of this association
   * @param  new_name    (optional) new name of this association
   * @param  new_alias1  (optional) new alias for object type 1
   * @param  new_alias2  (optional) new alias for object type 2
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_ALREADY_EXISTS
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_renameAssociation($name, $new_name, $new_alias1 = null,
                                         $new_alias2 = null) {
    return $this->call_method('facebook.data.renameAssociation',
       array('name' => $name,
             'new_name' => $new_name,
             'new_alias1' => $new_alias1,
             'new_alias2' => $new_alias2));
  }

  /**
   * Get definition of an object association.
   *
   * @param  name        name of this association
   * @return             specified association
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociationDefinition($name) {
    return $this->call_method('facebook.data.getAssociationDefinition',
       array('name' => $name));
  }

  /**
   * Get definition of all associations.
   *
   * @return             all defined associations
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociationDefinitions() {
    return $this->call_method('facebook.data.getAssociationDefinitions',
       array());
  }

  /**
   * Create or modify an association between two objects.
   *
   * @param  name        name of association
   * @param  obj_id1     id of first object
   * @param  obj_id2     id of second object
   * @param  data        (optional) extra string data to store
   * @param  assoc_time  (optional) extra time data; default to creation time
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_setAssociation($name, $obj_id1, $obj_id2, $data = null,
                                      $assoc_time = null) {
    return $this->call_method('facebook.data.setAssociation',
       array('name' => $name,
             'obj_id1' => $obj_id1,
             'obj_id2' => $obj_id2,
             'data' => $data,
             'assoc_time' => $assoc_time));
  }

  /**
   * Create or modify associations between objects.
   *
   * @param  assocs      associations to set
   * @param  name        (optional) name of association
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_setAssociations($assocs, $name = null) {
    return $this->call_method('facebook.data.setAssociations',
       array('assocs' => json_encode($assocs),
             'name' => $name));
  }

  /**
   * Remove an association between two objects.
   *
   * @param  name        name of association
   * @param  obj_id1     id of first object
   * @param  obj_id2     id of second object
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_removeAssociation($name, $obj_id1, $obj_id2) {
    return $this->call_method('facebook.data.removeAssociation',
       array('name' => $name,
             'obj_id1' => $obj_id1,
             'obj_id2' => $obj_id2));
  }

  /**
   * Remove associations between objects by specifying pairs of object ids.
   *
   * @param  assocs      associations to remove
   * @param  name        (optional) name of association
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_removeAssociations($assocs, $name = null) {
    return $this->call_method('facebook.data.removeAssociations',
       array('assocs' => json_encode($assocs),
             'name' => $name));
  }

  /**
   * Remove associations between objects by specifying one object id.
   *
   * @param  name        name of association
   * @param  obj_id      who's association to remove
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_removeAssociatedObjects($name, $obj_id) {
    return $this->call_method('facebook.data.removeAssociatedObjects',
       array('name' => $name,
             'obj_id' => $obj_id));
  }

  /**
   * Retrieve a list of associated objects.
   *
   * @param  name        name of association
   * @param  obj_id      who's association to retrieve
   * @param  no_data     only return object ids
   * @return             associated objects
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociatedObjects($name, $obj_id, $no_data = true) {
    return $this->call_method('facebook.data.getAssociatedObjects',
       array('name' => $name,
             'obj_id' => $obj_id,
             'no_data' => $no_data));
  }

  /**
   * Count associated objects.
   *
   * @param  name        name of association
   * @param  obj_id      who's association to retrieve
   * @return             associated object's count
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociatedObjectCount($name, $obj_id) {
    return $this->call_method('facebook.data.getAssociatedObjectCount',
       array('name' => $name,
             'obj_id' => $obj_id));
  }

  /**
   * Get a list of associated object counts.
   *
   * @param  name        name of association
   * @param  obj_ids     whose association to retrieve
   * @return             associated object counts
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_DATA_OBJECT_NOT_FOUND
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_INVALID_OPERATION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociatedObjectCounts($name, $obj_ids) {
    return $this->call_method('facebook.data.getAssociatedObjectCounts',
       array('name' => $name,
             'obj_ids' => json_encode($obj_ids)));
  }

  /**
   * Find all associations between two objects.
   *
   * @param  obj_id1     id of first object
   * @param  obj_id2     id of second object
   * @param  no_data     only return association names without data
   * @return             all associations between objects
   * @error
   *    API_EC_DATA_DATABASE_ERROR
   *    API_EC_PARAM
   *    API_EC_PERMISSION
   *    API_EC_DATA_QUOTA_EXCEEDED
   *    API_EC_DATA_UNKNOWN_ERROR
   */
  public function &data_getAssociations($obj_id1, $obj_id2, $no_data = true) {
    return $this->call_method('facebook.data.getAssociations',
       array('obj_id1' => $obj_id1,
             'obj_id2' => $obj_id2,
             'no_data' => $no_data));
  }

  /**
   * Get the properties that you have set for an app.
   *
   * @param properties  List of properties names to fetch
   *
   * @return array  A map from property name to value
   */
  public function admin_getAppProperties($properties) {
    return json_decode(
        $this->call_method('facebook.admin.getAppProperties',
            array('properties' => json_encode($properties))), true);
  }

  /**
   * Set properties for an app.
   *
   * @param properties  A map from property names to values
   *
   * @return bool  true on success
   */
  public function admin_setAppProperties($properties) {
    return $this->call_method('facebook.admin.setAppProperties',
       array('properties' => json_encode($properties)));
  }

  /**
   * Returns the allocation limit value for a specified integration point name
   * Integration point names are defined in lib/api/karma/constants.php in the
   * limit_map.
   *
   * @param string $integration_point_name  Name of an integration point
   *                                        (see developer wiki for list).
   *
   * @return int  Integration point allocation value
   */
  public function &admin_getAllocation($integration_point_name) {
    return $this->call_method('facebook.admin.getAllocation',
        array('integration_point_name' => $integration_point_name));
  }

  /**
   * Returns values for the specified metrics for the current application, in
   * the given time range.  The metrics are collected for fixed-length periods,
   * and the times represent midnight at the end of each period.
   *
   * @param start_time  unix time for the start of the range
   * @param end_time    unix time for the end of the range
   * @param period      number of seconds in the desired period
   * @param metrics     list of metrics to look up
   *
   * @return array  A map of the names and values for those metrics
   */
  public function &admin_getMetrics($start_time, $end_time, $period, $metrics) {
    return $this->call_method('admin.getMetrics',
        array('start_time' => $start_time,
              'end_time' => $end_time,
              'period' => $period,
              'metrics' => json_encode($metrics)));
  }

  /**
   * Sets application restriction info.
   *
   * Applications can restrict themselves to only a limited user demographic
   * based on users' age and/or location or based on static predefined types
   * specified by facebook for specifying diff age restriction for diff
   * locations.
   *
   * @param array $restriction_info  The age restriction settings to set.
   *
   * @return bool  true on success
   */
  public function admin_setRestrictionInfo($restriction_info = null) {
    $restriction_str = null;
    if (!empty($restriction_info)) {
      $restriction_str = json_encode($restriction_info);
    }
    return $this->call_method('admin.setRestrictionInfo',
        array('restriction_str' => $restriction_str));
  }

  /**
   * Gets application restriction info.
   *
   * Applications can restrict themselves to only a limited user demographic
   * based on users' age and/or location or based on static predefined types
   * specified by facebook for specifying diff age restriction for diff
   * locations.
   *
   * @return array  The age restriction settings for this application.
   */
  public function admin_getRestrictionInfo() {
    return json_decode(
        $this->call_method('admin.getRestrictionInfo', array()),
        true);
  }

  /* UTILITY FUNCTIONS */

  /**
   * Calls the specified normal POST method with the specified parameters.
   *
   * @param string $method  Name of the Facebook method to invoke
   * @param array $params   A map of param names => param values
   *
   * @return mixed  Result of method call; this returns a reference to support
   *                'delayed returns' when in a batch context.
   *     See: http://wiki.developers.facebook.com/index.php/Using_batching_API
   */
  public function &call_method($method, $params) {
    //Check if we are in batch mode
    if($this->batch_queue === null) {
      if ($this->call_as_apikey) {
        $params['call_as_apikey'] = $this->call_as_apikey;
      }
      $data = $this->post_request($method, $params);
      if (empty($params['format']) || strtolower($params['format']) != 'json') {
        $result = $this->convert_xml_to_result($data, $method, $params);
      }
      else {
        $result = json_decode($data, true);
      }

      if (is_array($result) && isset($result['error_code'])) {
        throw new FacebookRestClientException($result['error_msg'],
                                              $result['error_code']);
      }
    }
    else {
      $result = null;
      $batch_item = array('m' => $method, 'p' => $params, 'r' => & $result);
      $this->batch_queue[] = $batch_item;
    }

    return $result;
  }

  /**
   * Calls the specified file-upload POST method with the specified parameters
   *
   * @param string $method Name of the Facebook method to invoke
   * @param array  $params A map of param names => param values
   * @param string $file   A path to the file to upload (required)
   *
   * @return array A dictionary representing the response.
   */
  public function call_upload_method($method, $params, $file) {
    if ($this->batch_queue === null) {
      if (!file_exists($file)) {
        $code =
          FacebookAPIErrorCodes::API_EC_PARAM;
        $description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
        throw new FacebookRestClientException($description, $code);
      }

      $xml = $this->post_upload_request($method, $params, $file);
      $result = $this->convert_xml_to_result($xml, $method, $params);

      if (is_array($result) && isset($result['error_code'])) {
        throw new FacebookRestClientException($result['error_msg'],
                                              $result['error_code']);
      }
    }
    else {
      $code =
        FacebookAPIErrorCodes::API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE;
      $description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
      throw new FacebookRestClientException($description, $code);
    }

    return $result;
  }

  private function convert_xml_to_result($xml, $method, $params) {
    $sxml = simplexml_load_string($xml);
    $result = self::convert_simplexml_to_array($sxml);

    if (!empty($GLOBALS['facebook_config']['debug'])) {
      // output the raw xml and its corresponding php object, for debugging:
      print '<div style="margin: 10px 30px; padding: 5px; border: 2px solid black; background: gray; color: white; font-size: 12px; font-weight: bold;">';
      $this->cur_id++;
      print $this->cur_id . ': Called ' . $method . ', show ' .
            '<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'params\');">Params</a> | '.
            '<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'xml\');">XML</a> | '.
            '<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'sxml\');">SXML</a> | '.
            '<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'php\');">PHP</a>';
      print '<pre id="params'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($params, true).'</pre>';
      print '<pre id="xml'.$this->cur_id.'" style="display: none; overflow: auto;">'.htmlspecialchars($xml).'</pre>';
      print '<pre id="php'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($result, true).'</pre>';
      print '<pre id="sxml'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($sxml, true).'</pre>';
      print '</div>';
    }
    return $result;
  }

  private function finalize_params($method, &$params) {
    $this->add_standard_params($method, $params);
    // we need to do this before signing the params
    $this->convert_array_values_to_csv($params);
    $params['sig'] = Facebook::generate_sig($params, $this->secret);
  }

  private function convert_array_values_to_csv(&$params) {
    foreach ($params as $key => &$val) {
      if (is_array($val)) {
        $val = implode(',', $val);
      }
    }
  }

  private function add_standard_params($method, &$params) {
    if ($this->call_as_apikey) {
      $params['call_as_apikey'] = $this->call_as_apikey;
    }
    $params['method'] = $method;
    $params['session_key'] = $this->session_key;
    $params['api_key'] = $this->api_key;
    $params['call_id'] = microtime(true);
    if ($params['call_id'] <= $this->last_call_id) {
      $params['call_id'] = $this->last_call_id + 0.001;
    }
    $this->last_call_id = $params['call_id'];
    if (!isset($params['v'])) {
      $params['v'] = '1.0';
    }
  }

  private function create_post_string($method, $params) {
    $post_params = array();
    foreach ($params as $key => &$val) {
      $post_params[] = $key.'='.urlencode($val);
    }
    return implode('&', $post_params);
  }

  private function run_multipart_http_transaction($method, $params, $file) {
    // the format of this message is specified in RFC1867/RFC1341.
    // we add twenty pseudo-random digits to the end of the boundary string.
    $boundary = '--------------------------FbMuLtIpArT' .
                sprintf("%010d", mt_rand()) .
                sprintf("%010d", mt_rand());
    $content_type = 'multipart/form-data; boundary=' . $boundary;
    // within the message, we prepend two extra hyphens.
    $delimiter = '--' . $boundary;
    $close_delimiter = $delimiter . '--';
    $content_lines = array();
    foreach ($params as $key => &$val) {
      $content_lines[] = $delimiter;
      $content_lines[] = 'Content-Disposition: form-data; name="' . $key . '"';
      $content_lines[] = '';
      $content_lines[] = $val;
    }
    // now add the file data
    $content_lines[] = $delimiter;
    $content_lines[] =
      'Content-Disposition: form-data; filename="' . $file . '"';
    $content_lines[] = 'Content-Type: application/octet-stream';
    $content_lines[] = '';
    $content_lines[] = file_get_contents($file);
    $content_lines[] = $close_delimiter;
    $content_lines[] = '';
    $content = implode("\r\n", $content_lines);
    return $this->run_http_post_transaction($content_type, $content);
  }

  public function post_request($method, $params) {
    $this->finalize_params($method, $params);
    $post_string = $this->create_post_string($method, $params);
    if ($this->use_curl_if_available && function_exists('curl_init')) {
      $useragent = 'Facebook API PHP5 Client 1.1 (curl) ' . phpversion();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->server_addr);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
      $result = curl_exec($ch);
      curl_close($ch);
    } else {
      $content_type = 'application/x-www-form-urlencoded';
      $content = $post_string;
      $this->run_http_post_transaction($content_type, $content);
    }
    return $result;
  }

  private function post_upload_request($method, $params, $file) {
    $this->finalize_params($method, $params);
    if ($this->use_curl_if_available && function_exists('curl_init')) {
      // prepending '@' causes cURL to upload the file; the key is ignored.
      $params['_file'] = '@' . $file;
      $useragent = 'Facebook API PHP5 Client 1.1 (curl) ' . phpversion();
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->server_addr);
      // this has to come before the POSTFIELDS set!
      curl_setopt($ch, CURLOPT_POST, 1 );
      // passing an array gets curl to use the multipart/form-data content type
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
      $result = curl_exec($ch);
      curl_close($ch);
    } else {
      $result = $this->run_multipart_http_transaction($method, $params, $file);
    }
    return $result;
  }

  private function run_http_post_transaction($content_type, $content) {

    $user_agent = 'Facebook API PHP5 Client 1.1 (non-curl) ' . phpversion();
    $content_length = strlen($content);
    $context =
      array('http' =>
              array('method' => 'POST',
                    'user_agent' => $user_agent,
                    'header' => 'Content-Type: ' . $content_type . "\r\n" .
                                'Content-Length: ' . $content_length,
                    'content' => $content));
    $context_id = stream_context_create($context);
    $sock = fopen($this->server_addr, 'r', false, $context_id);

    $result = '';
    if ($sock) {
      while (!feof($sock)) {
        $result .= fgets($sock, 4096);
      }
      fclose($sock);
    }
    return $result;
  }

  public static function convert_simplexml_to_array($sxml) {
    $arr = array();
    if ($sxml) {
      foreach ($sxml as $k => $v) {
        if ($sxml['list']) {
          $arr[] = self::convert_simplexml_to_array($v);
        } else {
          $arr[$k] = self::convert_simplexml_to_array($v);
        }
      }
    }
    if (sizeof($arr) > 0) {
      return $arr;
    } else {
      return (string)$sxml;
    }
  }

  private function get_uid($uid) {
    return $uid ? $uid : $this->user;
  }
}


class FacebookRestClientException extends Exception {
}

// Supporting methods and values------

/**
 * Error codes and descriptions for the Facebook API.
 */

class FacebookAPIErrorCodes {

  const API_EC_SUCCESS = 0;

  /*
   * GENERAL ERRORS
   */
  const API_EC_UNKNOWN = 1;
  const API_EC_SERVICE = 2;
  const API_EC_METHOD = 3;
  const API_EC_TOO_MANY_CALLS = 4;
  const API_EC_BAD_IP = 5;

  /*
   * PARAMETER ERRORS
   */
  const API_EC_PARAM = 100;
  const API_EC_PARAM_API_KEY = 101;
  const API_EC_PARAM_SESSION_KEY = 102;
  const API_EC_PARAM_CALL_ID = 103;
  const API_EC_PARAM_SIGNATURE = 104;
  const API_EC_PARAM_USER_ID = 110;
  const API_EC_PARAM_USER_FIELD = 111;
  const API_EC_PARAM_SOCIAL_FIELD = 112;
  const API_EC_PARAM_EMAIL = 113;
  const API_EC_PARAM_USER_ID_LIST = 114;
  const API_EC_PARAM_ALBUM_ID = 120;
  const API_EC_PARAM_BAD_EID = 150;
  const API_EC_PARAM_UNKNOWN_CITY = 151;

  /*
   * USER PERMISSIONS ERRORS
   */
  const API_EC_PERMISSION = 200;
  const API_EC_PERMISSION_USER = 210;
  const API_EC_PERMISSION_ALBUM = 220;
  const API_EC_PERMISSION_PHOTO = 221;
  const API_EC_PERMISSION_EVENT = 290;
  const API_EC_PERMISSION_RSVP_EVENT = 299;

  /*
   * DATA EDIT ERRORS
   */
  const API_EC_EDIT_ALBUM_SIZE = 321;

  const FQL_EC_PARSER = 601;
  const FQL_EC_UNKNOWN_FIELD = 602;
  const FQL_EC_UNKNOWN_TABLE = 603;
  const FQL_EC_NOT_INDEXABLE = 604;
  const FQL_EC_UNKNOWN_FUNCTION = 605;
  const FQL_EC_INVALID_PARAM = 606;

  /**
   * DATA STORE API ERRORS
   */
  const API_EC_DATA_UNKNOWN_ERROR = 800;
  const API_EC_DATA_INVALID_OPERATION = 801;
  const API_EC_DATA_QUOTA_EXCEEDED = 802;
  const API_EC_DATA_OBJECT_NOT_FOUND = 803;
  const API_EC_DATA_OBJECT_ALREADY_EXISTS = 804;
  const API_EC_DATA_DATABASE_ERROR = 805;

  /*
   * Batch ERROR
   */
  const API_EC_BATCH_ALREADY_STARTED = 900;
  const API_EC_BATCH_NOT_STARTED = 901;
  const API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE = 902;

  public static $api_error_descriptions = array(
      self::API_EC_SUCCESS           => 'Success',
      self::API_EC_UNKNOWN           => 'An unknown error occurred',
      self::API_EC_SERVICE           => 'Service temporarily unavailable',
      self::API_EC_METHOD            => 'Unknown method',
      self::API_EC_TOO_MANY_CALLS    => 'Application request limit reached',
      self::API_EC_BAD_IP            => 'Unauthorized source IP address',
      self::API_EC_PARAM             => 'Invalid parameter',
      self::API_EC_PARAM_API_KEY     => 'Invalid API key',
      self::API_EC_PARAM_SESSION_KEY => 'Session key invalid or no longer valid',
      self::API_EC_PARAM_CALL_ID     => 'Call_id must be greater than previous',
      self::API_EC_PARAM_SIGNATURE   => 'Incorrect signature',
      self::API_EC_PARAM_USER_ID     => 'Invalid user id',
      self::API_EC_PARAM_USER_FIELD  => 'Invalid user info field',
      self::API_EC_PARAM_SOCIAL_FIELD => 'Invalid user field',
      self::API_EC_PARAM_ALBUM_ID    => 'Invalid album id',
      self::API_EC_PARAM_BAD_EID     => 'Invalid eid',
      self::API_EC_PARAM_UNKNOWN_CITY => 'Unknown city',
      self::API_EC_PERMISSION        => 'Permissions error',
      self::API_EC_PERMISSION_USER   => 'User not visible',
      self::API_EC_PERMISSION_ALBUM  => 'Album not visible',
      self::API_EC_PERMISSION_PHOTO  => 'Photo not visible',
      self::API_EC_PERMISSION_EVENT  => 'Creating and modifying events required the extended permission create_event',
      self::API_EC_PERMISSION_RSVP_EVENT => 'RSVPing to events required the extended permission rsvp_event',
      self::API_EC_EDIT_ALBUM_SIZE   => 'Album is full',
      self::FQL_EC_PARSER            => 'FQL: Parser Error',
      self::FQL_EC_UNKNOWN_FIELD     => 'FQL: Unknown Field',
      self::FQL_EC_UNKNOWN_TABLE     => 'FQL: Unknown Table',
      self::FQL_EC_NOT_INDEXABLE     => 'FQL: Statement not indexable',
      self::FQL_EC_UNKNOWN_FUNCTION  => 'FQL: Attempted to call unknown function',
      self::FQL_EC_INVALID_PARAM     => 'FQL: Invalid parameter passed in',
      self::API_EC_DATA_UNKNOWN_ERROR => 'Unknown data store API error',
      self::API_EC_DATA_INVALID_OPERATION => 'Invalid operation',
      self::API_EC_DATA_QUOTA_EXCEEDED => 'Data store allowable quota was exceeded',
      self::API_EC_DATA_OBJECT_NOT_FOUND => 'Specified object cannot be found',
      self::API_EC_DATA_OBJECT_ALREADY_EXISTS => 'Specified object already exists',
      self::API_EC_DATA_DATABASE_ERROR => 'A database error occurred. Please try again',
      self::API_EC_BATCH_ALREADY_STARTED => 'begin_batch already called, please make sure to call end_batch first',
      self::API_EC_BATCH_NOT_STARTED => 'end_batch called before start_batch',
      self::API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE => 'This method is not allowed in batch mode'
  );
}
