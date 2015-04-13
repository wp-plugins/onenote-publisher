<?php

class onwp_base {
    public static $USER_AGENT = 'OneNote Wordpress';
    public static $ONWP_PLUGIN_OPTIONS = 'onwp_settings';
    public static $ONWP_TEMPORARY_ACCESS_TOKEN = 'onwp_temp_access_token';
    public static $ONWP_CURRENT_USER = 'onwp_current_user';  // Holds the current user for which were storing the access token
    public static $ONWP_CURRENT_USER_OAUTH_STATE = 'onwp_current_user_oauth_state';  // Holds the current user's oauth state value
    
    public function __construct() {
        $this->options = get_option(self::$ONWP_PLUGIN_OPTIONS);
    }
    
    public function get_configuration() {
        $client_id = $this->options['client_id'];
        $redirect_uri = admin_url();
        $configuration = array(
            'authentication' => array(
                'login_page_url' => 'https://login.live.com/oauth20_authorize.srf',
                'login_page_parameters' => array(
                    'client_id' => $client_id,
                    'scope' => 'office.onenote wl.signin wl.basic wl.offline_access',
                    'state' => '',  // This will be filled at request time
                    'response_type' => 'code',
                    'redirect_uri' => $redirect_uri
                ),
                'token_exchange_page_url' => 'https://login.live.com/oauth20_token.srf',
                'token_exchange_page_parameters' => array(
                    'client_id' => $client_id,
                    'client_secret' => $this->options['client_secret'],
                    'code' => '',  // This will be filled in at request time
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri
                ),
                'refresh_token_exchange_page_parameters' => array(  // These options are used when refreshing the access token using a refresh token
                    'client_id' => $client_id,
                    'client_secret' => $this->options['client_secret'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => '',  // This will be filled in at request time
                    'redirect_uri' => $redirect_uri
                ),
            )
        );
        
        return $configuration;
    }

    protected static function validate_current_user() {
        $current_user_id = get_current_user_id();
    
        if (get_option(self::$ONWP_CURRENT_USER) != $current_user_id) {
            delete_option(self::$ONWP_TEMPORARY_ACCESS_TOKEN);
            delete_option(self::$ONWP_CURRENT_USER_OAUTH_STATE);
            update_option(self::$ONWP_CURRENT_USER, $current_user_id);
        }
    }
    
    // Returns the access token of one was saved in the session, otherwise returns false
    public static function get_access_token() {
        self::validate_current_user();
        $access_token = get_option(self::$ONWP_TEMPORARY_ACCESS_TOKEN);

        if (empty($access_token)) {
            return false;
        }
        
        return $access_token;
    }
    
    public static function set_access_token($access_token) {
        self::validate_current_user();
        update_option(self::$ONWP_TEMPORARY_ACCESS_TOKEN, $access_token);
    }

    // Get the current user's oauth state value
    public static function get_oauth_state() {
        self::validate_current_user();
        $oauth_state = get_option(self::$ONWP_CURRENT_USER_OAUTH_STATE);
        return $oauth_state;
    }
    
    // Set the current user's oauth state value
    public static function set_oauth_state($oauth_state) {
        self::validate_current_user();
        update_option(self::$ONWP_CURRENT_USER_OAUTH_STATE, $oauth_state);
    }
    
    // Redirect to the specified URL with a 302 Temporarily Moved HTTP response
    public static function redirect($url) {
        header("Location: $url", true, 302);  // Send a redirect header
        die();  // Terminate execution
    }
    
    // Save the current value of $options to the WordPress settings database
    public function update_options() {
        update_option(self::$ONWP_PLUGIN_OPTIONS, $this->options);
    }
}

?>
