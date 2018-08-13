<?php
/**
 * A set of functions to enable interaction with the Cover API
 */

if (!defined('COVER_API_URL'))
    define('COVER_API_URL', 'https://www.svcover.nl/api.php');

if (!defined('COVER_COOKIE_NAME'))
    define('COVER_COOKIE_NAME', 'cover_session_id');
    
if (!defined('COVER_LOGIN_URL'))
    define('COVER_LOGIN_URL', 'https://www.svcover.nl/sessions.php?view=login');

if (!defined('COVER_LOGOUT_URL'))
    define('COVER_LOGOUT_URL', 'https://www.svcover.nl/sessions.php?view=logout');


/** Reverse of php's parse_url */
function _http_build_url($parts) { 
    return implode("", array(
        isset($parts['scheme']) ? $parts['scheme'] . '://' : '',
        isset($parts['user']) ? $parts['user'] : '',
        isset($parts['pass']) ? ':' . $parts['pass']  : '',
        (isset($parts['user']) || isset($parts['pass'])) ? "@" : '',
        isset($parts['host']) ? $parts['host'] : '',
        isset($parts['port']) ? ':' . intval($parts['port']) : '',
        isset($parts['path']) ? $parts['path'] : '',
        isset($parts['query']) ? '?' . $parts['query'] : '',
        isset($parts['fragment']) ? '#' . $parts['fragment'] : ''
    ));
}


/** Inject arguments into http url */
function _http_inject_url($url, array $data) {
    // Parse the url
    $url_parts = parse_url($url);

    // Explicitly parse the query part as well
    if (isset($url_parts['query']))
        parse_str($url_parts['query'], $url_query);
    else
        $url_query = array();

    // Splice in the token authentication
    $url_query = array_merge($data, $url_query);

    // Rebuild the url
    $url_parts['query'] = http_build_query($url_query);
    return _http_build_url($url_parts);
}


/** Perform a signed http request */
function _http_signed_request($app, $secret, $url, array $post = null, $timeout=30) {
    $body = $post !== null ? http_build_query($post) : '';

    $checksum = sha1($body . $secret);

    $headers = "X-App: ". $app. "\r\n".
              "X-Hash: ". $checksum . "\r\n";

    if ($post !== null)
        $options = [
            'http' => [
                'header'  => $headers."Content-type: application/x-www-form-urlencoded\r\n",
                'timeout' => $timeout,
                'method'  => 'POST',
                'content' => $body
            ]
        ];
    else
        $options = [ 
            'http' => [
                'header'  => $headers,
                'timeout' => $timeout,
                'method'  => 'GET'
            ] 
        ];

    $context = stream_context_create($options);

    return file_get_contents($url, false, $context);
}


/** Get JSON via a signed http request*/
function _http_get_json($url, array $data = null) {
    if ($data !== null)
        $url = _http_inject_url($url, $data);

    $response = _http_signed_request(COVER_APP, COVER_SECRET, $url);

    if (!$response)
        throw new Exception('No response');

    $data = json_decode($response);

    if ($data === null)
        throw new Exception('Response could not be parsed as JSON: <pre>' . htmlentities($response) . '</pre>');

    return $data;
}


/** Get Cover session data if member is logged in and login is valid*/
function get_cover_session() {
    static $session = null;

    // Is there a cover website global session id available?
    if (!empty($_COOKIE[COVER_COOKIE_NAME]))
        $session_id = $_COOKIE[COVER_COOKIE_NAME];

    // If not, bail out. I have no place else to look :(
    else
        return null;

    if ($session !== null)
        return $session;

    $data = array(
        'method' => 'session_get_member',
        'session_id' => $session_id
        );

    $response = _http_get_json(COVER_API_URL, $data);

    return $session = !empty($response->result)
        ? $response->result
        : null;
}


/** Check if member is logged in and login is valid */
function cover_session_logged_in() {
    return get_cover_session() !== null;
}


/** Create url to Cover Session management with return field */
function _cover_session_url($url, $next_url=null, $next_field='referrer') {
    if ($next_url === null)
        $next_url = SERVER_NAME.$_SERVER['REQUEST_URI'];
    return _http_inject_url($url, array($next_field => $next_url));
}

/** Create url to Cover Login with return field */
function cover_login_url($next_url=null) {
    return _cover_session_url(COVER_LOGIN_URL, $next_url);
}


/** Create url to Cover Logout with return field */
function cover_logout_url($next_url=null) {
    return _cover_session_url(COVER_LOGOUT_URL, $next_url);
}


/** Get of which the logged in member is part */
function cover_session_get_committees() {
    static $committees;

    if ($committees !== null)
        return $committees;
    
    $session = get_cover_session();

    if (!$session)
        return array();

    return $committees = array_keys((array) get_cover_session()->committees);
}


/** Check if logged in member is in one or multiple committee(s) */
function cover_session_in_committee($committees) {
    if (is_array($committees))
        return !empty(array_intersect(
            array_map( function($val) {  return strtolower($val); }, $committees ),
            cover_session_get_committees()
        ));
    return in_array(strtolower($committees), cover_session_get_committees());
}


/** Get JSON from Cover API with session id */
function cover_get_json($method, array $data=array(), $use_session=true) {
    if ($use_session && cover_session_logged_in() && !isset($data['session_id']))
        $data['session_id'] = $_COOKIE[COVER_COOKIE_NAME];

    $data['method'] = $method;

    return _http_get_json(COVER_API_URL, $data);
}
