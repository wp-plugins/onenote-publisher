<?php

require_once 'includes/onwp-base.php';

class api_proxy extends onwp_base {
    protected static $ONENOTE_API_BASE_URL = 'https://www.onenote.com/api/v1.0';
    
    protected $access_token;
    
    public function __construct() {
        parent::__construct();
        $this->obtain_access_token();
        $this->handle_request();
    }
    
    // Try to obtain the access token or respond with 401 Unauthorized
    protected function obtain_access_token() {
        $this->access_token = parent::get_access_token();

        if ($this->access_token === false) {
            http_response_code(401);
            die();
        }
    }
    
    protected function handle_request() {
        $handled = false;  // Indicates whether the request has been handled or not
        $http_method = $_SERVER['REQUEST_METHOD'];  // Get the HTTP method
        $resource_url = stripcslashes($_GET['resource']);  // Get the requested resource URL
        $request_url = self::$ONENOTE_API_BASE_URL . $resource_url;
        
        if ($http_method == 'GET') {
            $this->handle_get_request($request_url);
            $handled = true;
        }
        
        // If the request was not handled, return a 400 Bad Request
        if (!$handled) {
            http_response_code(400);
        }
    }
    
    // Handle proxying HTTP GET requests
    protected function handle_get_request($url) {
        // Initialize the request parameters
        $request_headers = array(
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->access_token}"
        );

        $request_arguments = array(
            'headers' => $request_headers,
            'httpversion' => '1.1',
            'user-agent' => parent::$USER_AGENT,
            'timeout' => 60
        );

        $response = wp_remote_get($url, $request_arguments);
        if (!is_array($response)) {
            $response = array('body' => '');
            $response_http_code =  500;
        }
        else {
            $response_http_code = $response['response']['code'];
        }

        header("HTTP/1.1 $response_http_code");
        echo $response['body'];
    }
}

new api_proxy();

?>