<?php
// Copyright 2008-2009 TripIt, Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License"); you may
// not use this file except in compliance with the License. You may obtain
// a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
// WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
// License for the specific language governing permissions and limitations
// under the License.


assert(function_exists("curl_init"));

$VERSION='$Id: tripit.php 29011 2010-10-01 19:13:08Z brian $';

class WebAuthCredential {
    var $_username;
    var $_password;

    function WebAuthCredential($username, $password) {
        $this->_username = $username;
        $this->_password = $password;
    }
    
    function getUsername() {
        return $this->_username;
    }

    function getPassword() {
        return $this->_password;
    }
    
    function authorize($curl, $http_method, $realm, $base_url, $args) {
        curl_setopt($curl, CURLOPT_USERPWD, $this->_username . ":" . $this->_password);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
}

class OAuthUtil {
    public static function urlencodeRFC3986($string) {
        return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($string)));

    }

    public static function generate_nonce() {
        return md5(microtime() . mt_rand()); // md5s look nicer than numbers
    }

    public static function generate_timestamp() {
        return time();
    }
}

class OAuthConsumerCredential {
    const OAUTH_SIGNATURE_METHOD = 'HMAC-SHA1';
    const OAUTH_VERSION = '1.0';

    var $_oauth_consumer_key;
    var $_oauth_consumer_secret;
    var $_oauth_token;
    var $_oauth_token_secret;
    var $_oauth_requestor_id;

    function OAuthConsumerCredential($oauth_consumer_key, $oauth_consumer_secret, $oauth_token_or_requestor_id='', $oauth_token_secret='') {
        $this->_oauth_consumer_key = $oauth_consumer_key;
        $this->_oauth_consumer_secret = $oauth_consumer_secret;
        
        $this->_oauth_token = $this->_oauth_token_secret = $this->_oauth_requestor_id = '';
        if ($oauth_token_or_requestor_id && $oauth_token_secret) {
            $this->_oauth_token = $oauth_token_or_requestor_id;
            $this->_oauth_token_secret = $oauth_token_secret;
        }
        elseif ($oauth_token_or_requestor_id) {
            $this->_oauth_requestor_id = $oauth_token_or_requestor_id;
        }
    }

    function getOAuthConsumerKey() {
        return $this->_oauth_consumer_key;
    }

    function getOAuthConsumerSecret() {
        return $this->_oauth_consumer_secret;
    }

    function getOAuthToken() {
        return $this->_oauth_token;
    }

    function getOAuthTokenSecret() {
        return $this->_oauth_token_secret;
    }
    
    function getOAuthRequestorId() {
        return $this->_oauth_requestor_id;
    }
    
    function authorize($curl, $http_method, $realm, $base_url, $args) {
        $authorization_header = $this->_generate_authorization_header($http_method, $realm, $base_url, $args);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: ' . $authorization_header));
    }
    
    function validate_signature($url) {
        list($base_url, $query) = explode('?', $url, 2);
        $params = array();
        parse_str($query, $params);
        $signature = $params['oauth_signature'];
        
        return ($signature == $this->_generate_signature('GET', $base_url, $params));
    }
    
    function get_session_parameters($redirect_url, $action) {
        $parameters = $this->_generate_oauth_parameters('GET', $action, array('redirect_url' => $redirect_url));
        $parameters['redirect_url'] = $redirect_url;
        $parameters['action'] = $action;
        return json_encode($parameters);
    }

    function get_signable_parameters($params) {
        // Remove oauth_signature if present
        if (isset ($params['oauth_signature'])) {
            unset ($params['oauth_signature']);
        }

        // Urlencode both keys and values
        $keys = array_map(array (
            'OAuthUtil',
            'urlencodeRFC3986'
        ), array_keys($params));
        $values = array_map(array (
            'OAuthUtil',
            'urlencodeRFC3986'
        ), array_values($params));
        $params = array_combine($keys, $values);

        // Sort by keys (natsort)
        uksort($params, 'strnatcmp');

        // Generate key=value pairs
        $pairs = array ();
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                // If the value is an array, it's because there are multiple 
                // with the same key, sort them, then add all the pairs
                natsort($value);
                foreach ($value as $v2) {
                    $pairs[] = $key . '=' . $v2;
                }
            }
            else {
                $pairs[] = $key . '=' . $value;
            }
        }

        // Return the pairs, concated with &
        return implode('&', $pairs);
    }
    
    private function _generate_signature($method, $base_url, $params) {
        $normalized_parameters = OAuthUtil :: urlencodeRFC3986($this->get_signable_parameters($params));
        
        $normalized_http_url = OAuthUtil :: urlencodeRFC3986($base_url);
        
        $base_string = $method . '&' . $normalized_http_url;
        if ($normalized_parameters) {
            $base_string .= '&' . $normalized_parameters;
        }
        
        $key_parts = array ( $this->_oauth_consumer_secret, $this->_oauth_token_secret );

        $key_parts = array_map(array (
            'OAuthUtil',
            'urlencodeRFC3986'
        ), $key_parts);
        $key = implode('&', $key_parts);
        
        return base64_encode(hash_hmac('sha1', $base_string, $key, true));
    }

    private function _generate_oauth_parameters($http_method, $base_url, $args=null) {
        $http_method = strtoupper($http_method);

        $parameters = array( 'oauth_consumer_key'     => $this->_oauth_consumer_key,
                             'oauth_nonce'            => OAuthUtil :: generate_nonce(),
                             'oauth_timestamp'        => OAuthUtil :: generate_timestamp(),
                             'oauth_signature_method' => self :: OAUTH_SIGNATURE_METHOD,
                             'oauth_version'          => self :: OAUTH_VERSION);

        if ($this->_oauth_token != '') {
            $parameters['oauth_token'] = $this->_oauth_token;
        }
        
        if ($this->_oauth_requestor_id != '') {
            $parameters['xoauth_requestor_id'] = $this->_oauth_requestor_id;
        }

        $parameters_for_base_string = $parameters;
        if ($args) {
            $parameters_for_base_string = array_merge($parameters, $args);
        }

        $parameters['oauth_signature'] = $this->_generate_signature($http_method, $base_url, $parameters_for_base_string);
        
        return $parameters;
    }
    
    private function _generate_authorization_header($http_method, $realm, $base_url, $args) {
        $authorization_header = 'OAuth realm="' . $realm . '",';

        $params = array();
        foreach (
            $this->_generate_oauth_parameters($http_method, $base_url, $args)
            as $k => $v)
        {
            if (substr($k, 0, 5) == 'oauth' || substr($k, 0, 6) == 'xoauth') {
                $params[] = OAuthUtil :: urlencodeRFC3986($k) . '="' . OAuthUtil :: urlencodeRFC3986($v) . '"';
            }
        }
        $authorization_header .= implode(',', $params);

        return $authorization_header;
    }
}

class TripIt {
    var $_credential;
    var $_api_version;
    var $_api_url;

    var $resource;
    var $http_code;
    var $response;
    var $info;

    function TripIt($credential, $api_url='https://api.tripit.com') {
        $this->_credential = $credential;
        $this->_api_version = 'v1';
        $this->_api_url = $api_url;

        $this->resource = null;
        $this->http_code = null;
        $this->response = null;
    }

    function _do_request($verb, $entity=null, $url_args=null, $post_args=null) {
        if (in_array($verb, array('/oauth/request_token', '/oauth/access_token') )) {
            $base_url = implode('/', array($this->_api_url, $verb));
        } else {
            if ($entity) {
                $base_url = implode('/', array($this->_api_url, $this->_api_version, $verb, $entity));
            } else {
                $base_url = implode('/', array($this->_api_url, $this->_api_version, $verb));
            }
        }

        $args = null;
        if ($url_args) {
            $args = $url_args;
            $pairs = array();
            foreach ($url_args as $name => $value) {
                $pairs[] = urlencode($name) . '=' . urlencode($value);
            }
            $url = $base_url . '?' . join('&', $pairs);
        } else {
            $url = $base_url;
        }

        $this->resource = $url;

        $curl = curl_init($this->_api_url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // In case you're running this against a server w/o
        //   properly signed certs
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        if ($post_args) {
            $args = $post_args;
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_args);
            $http_method = 'POST';
        } else {
            $http_method = 'GET';
        }
        
        $this->_credential->authorize($curl, $http_method, $this->_api_url, $base_url, $args);

        if (FALSE === $this->response = curl_exec($curl)) {
            throw new Exception(curl_error($curl));
        }
        
        $this->info = curl_getinfo($curl);
        $this->http_code = $this->info['http_code'];
        curl_close($curl);
        
        return $this->response;
    }

    function _xml_to_php($xml) {
        return new SimpleXMLElement($xml);
    }

    function _parse_command($func_name, $url_args = null, $post_args = null) {
        $pieces = explode('_', $func_name, 2);
        $verb = $pieces[0];
        $entity = count($pieces) > 1 ? $pieces[1] : null;

        $response = $this->_do_request($verb, $entity, $url_args, $post_args);
        return $this->_xml_to_php($response);        
    }

    function get_trip($id, $filter=array()) {
        $filter['id'] = $id;
        return $this->_parse_command(__FUNCTION__, $filter);
    }

    function get_air($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_lodging($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_car($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_rail($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_transport($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_cruise($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_restaurant($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_activity($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_note($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_map($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_directions($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function get_profile() {
        return $this->_parse_command(__FUNCTION__);
    }
    
    function get_points_program($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_trip($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_air($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_lodging($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_car($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_rail($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_transport($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_cruise($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_restaurant($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_activity($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_note($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_map($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }

    function delete_directions($id) {
        return $this->_parse_command(__FUNCTION__, array( 'id' => $id ));
    }
    
    function replace_trip($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_air($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_lodging($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_car($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_rail($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_transport($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_cruise($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_restaurant($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_activity($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_note($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_map($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function replace_directions($id, $xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'id' => $id, 'xml' => $xml ));
    }

    function list_trip($filter=null) {
        return $this->_parse_command(__FUNCTION__, $filter);
    }

    function list_object($filter=null) {
        return $this->_parse_command(__FUNCTION__, $filter);
    }
    
    function list_points_program() {
        return $this->_parse_command(__FUNCTION__);
    }

    function create($xml) {
        return $this->_parse_command(__FUNCTION__, null, array( 'xml' => $xml));
    }
    
    function crs_load_reservations($xml, $company_key=null) {
        $args = array('xml' => $xml);
        if ($company_key !== null) {
            $args['company_key'] = $company_key;
        }
        
        return $this->_parse_command('crsLoadReservations', null, $args);
    }
    
    function crs_delete_reservations($record_locator) {
        return $this->_parse_command('crsDeleteReservations', array('record_locator' => $record_locator), null);
    }

    function get_request_token() {
        $response = $this->_do_request('/oauth/request_token');
        if ($this->http_code == 200) {
            $request_token = array();
            parse_str($response, $request_token);
            return $request_token;
        } else {
            return $response;
        }
    }

    function get_access_token() {
        $response = $this->_do_request('/oauth/access_token');
        if ($this->http_code == 200) {
            $access_token = array();
            parse_str($response, $access_token);
            return $access_token;
        } else {
            return $response;
        }
    }
}
