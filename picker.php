<?php

require_once 'includes/html-base.php';

class picker extends onwp_html_base {
    public function __construct() {
        parent::__construct();
        $this->authenticate();  // Make sure we have a valid access token

        // Initialize various HTML components
        $this->title = 'Select a OneNote Page';
        $this->css[] =  plugins_url('css/picker.css', __file__);
        $this->scripts[] = plugins_url('js/picker.js', __file__);
        $this->draw();  // Draw the HTML
    }

    protected function authenticate() {
        // Check if we need to re-authenticate
        if (array_key_exists('re-authenticate', $_GET)) {
            parent::set_access_token('');  // Reset the old access token, this will cause a redirect to the login page
        }
        else {
            // Check if we have a code value in the URL (which means we were redirected to this page by the login page)
            $code = @$_GET['code'];
           
            if (!empty($code)) {
                // Make sure we have a valid state value
                $state_value = parent::get_oauth_state();
                // If the state value returned from the login page is not the same as the one we've sent to the login page,
                // then this might be a spoofing attempt so kill the request.
                if ($state_value != $_GET['state']) {
                    die();
                }

                $this->get_access_token_from_code($code);
            }
        }

        // Check if we have an access code saved in the current sessions
        $access_token = parent::get_access_token();
        // No access token? check redirect to the login page
        if ($access_token === false) {
            $this->redirect_to_login_page();
        }
    }
    
    // Redirect the user to the login page
    protected function redirect_to_login_page() {
        $configuration = $this->get_configuration();
        $authentication_info = $configuration['authentication'];
        $login_page_parameters = $authentication_info['login_page_parameters'];
        $login_state = rand(rand(0,800), rand(800, 9792797974));  // Generate a random state number in order to verify this value when we return from the login page
        parent::set_oauth_state($login_state);  // Save the state value in the session
        $login_page_parameters['state'] = $login_state;
        $login_page_parameters = http_build_query($login_page_parameters);
        $login_page_url = $authentication_info['login_page_url'] . '?' . $login_page_parameters;
        $this->redirect($login_page_url);  
    }

    // Used to make a token request for example: get access token from code
    protected function make_token_request($request_parameters_configuration_name, $token_parameter_name, $token_value) {
        $configuration = $this->get_configuration();
        $authentication_info = $configuration['authentication'];
        $token_exchange_url = $authentication_info['token_exchange_page_url'];
        $request_parameters = $authentication_info[$request_parameters_configuration_name];
        $request_parameters[$token_parameter_name] = $token_value;  // Update the code in the request
        $request_headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        );

        $request_arguments = array(
            'headers' => $request_headers,
            'body' => $request_parameters,
            'timeout' => 60
        );
        
        $response = wp_remote_post($token_exchange_url, $request_arguments);
        
        // Check if the request was successfull...
        if ($response['response']['code'] == 200 && !empty($response['body'])) {
            $response = json_decode($response['body'], true);
        }
        else {
            $response = false;
        }
        
        return $response;  // Return either the response or false
    }
    
    // Converts a code value to an access token
    protected function get_access_token_from_code($code) {
        $response = $this->make_token_request('token_exchange_page_parameters', 'code', $code);

        if ($response !== false) {
            parent::set_access_token($response['access_token']);  // Save the access token
        }
        else {
            parent::Set_access_token('');
        }
    }

    protected function head_open() {
        wp_head();
        parent::head_open();
    }
    
    // Some javascript to launch the picker when the page loads
    protected function head_close() {
?>
<script>

    var imagesPath = '<?php echo plugins_url('images', __file__); ?>';
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

    function pageSelected(pageId) {
        var form = opener.document.createElement('form'),
            input = opener.document.createElement('input');
            
        form.method = 'post';
        input.type = 'hidden';
        input.name = 'onenote_page_id';
        input.value = pageId;
        form.appendChild(input);
        opener.document.body.appendChild(form);

        form.submit();
        window.close();
    }

    jQuery(document).ready(
        function () {
            _OneNotePickerLaunch('#onenote-picker', pageSelected, imagesPath, ajaxUrl);
        }
    );

</script>
<?php
        parent::head_close();
    }
    
    // The DIV which will hold the picker
    protected function body_content() {
?>
<div id="onenote-picker"></div>
<?php
    }
}

new picker();

?>