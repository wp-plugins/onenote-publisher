<?php

/*
* Plugin Name: OneNote Publisher for WordPress
* Plugin URI: http://dev.onenote.com
* Description: The OneNote Publisher for WordPress plugin allows you to publish your OneNote pages into your WordPress posts or pages. 
* Author: Microsoft
* Author URI: http://dev.onenote.com	
* Version: 1.1
* License: GPLv2 or later (license.txt)
*/

include_once 'includes/onwp-base.php';

class wp_onenote_plugin extends onwp_base
{
    static $POPUP_QUERY_STRING_FLAG = 'onwp-popup';
    static $INSTRUCTIONS_PAGE_QUERY_STRING_FLAG = 'onwp-instructions';
    static $CODE_QUERY_STRING_PARAMETER = 'code';
    static $STATE_QUERY_STRING_PARAMETER = 'state';
    static $RE_AUTHENTICATE_QUERY_STRING_PARAMETER = 're-authenticate';

    protected $options;
    protected $page_contents = null;

    public function __construct() {
        parent::__construct();
        $this->register_actions();  // Register actions
    }

    protected function register_actions() {
        add_action('admin_init', array($this, 'initialize'));
        add_action('admin_menu', array($this, 'register_settings_menu'));  // Adds the plugin to the sidebar on the dashboard menu
        add_action( 'wp_ajax_onwp_ajax_api_proxy', array($this, 'onwp_ajax_api_proxy'));
    }
    
    protected function is_live_referer() {
        if (array_key_exists(self::$POPUP_QUERY_STRING_FLAG, $_GET) ||
            (array_key_exists(self::$CODE_QUERY_STRING_PARAMETER, $_GET) &&
            array_key_exists(self::$STATE_QUERY_STRING_PARAMETER, $_GET)) ||
            array_key_exists(self::$RE_AUTHENTICATE_QUERY_STRING_PARAMETER, $_GET)) {
            return true;
        }
    }
    
    // This methods registers the various "hooks" needed for our plugin to be executed in all the various
    // location where we wish to perform our magic.
    public function initialize() {
        if ($this->is_live_referer()) {
            require_once('picker.php');
            exit;
        }
        else if (array_key_exists(self::$INSTRUCTIONS_PAGE_QUERY_STRING_FLAG, $_GET)) {
            require_once('msa-instructions.php');
            exit;
        }
    
        // Only reigster our hooks if the current user can edit posts & pages
        if (current_user_can('edit_posts') && current_user_can('edit_pages')) {
            register_setting('onwp_settings_group', self::$ONWP_PLUGIN_OPTIONS);  // Registers settings in the DB
            $this->options = get_option(self::$ONWP_PLUGIN_OPTIONS);
            $plugin = plugin_basename(__file__);  // Get the name of the plugin file
            add_filter("plugin_action_links_$plugin", array($this, 'register_settings_page'));
            add_filter('mce_buttons', array($this, 'register_editor_buttons'));  // Register to add buttons
            add_filter('mce_external_plugins', array($this, 'register_editor_buttons_logic'));  // Register to add buttons logic
            
            // If we have a POST with a page ID, it must be the picker signaling a selected page ID.
            // In this case, save the page and write the results into a JavaScript tag which will in turn update
            // the contents of the editor.
            $page = basename($_SERVER['SCRIPT_NAME']);
            $page_id = $_POST['onenote_page_id'];

            add_action('admin_print_scripts', array($this, 'set_client_side_variables'));
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($page_id) && ($page == 'post.php' || $page == 'post-new.php')) {
                require_once('save.php');
                $save = new onwp_save($page_id);
                $this->page_contents = $save->save_page();
                add_action('admin_print_scripts', array($this, 'update_saved_page_content'));
            }
        }
    }

    public function onwp_ajax_api_proxy() {
        require_once('api-proxy.php');
        exit;
    }
    
    // Register our settings page
    public function register_settings_page($links) { 
        $settings_link = '<a href="options-general.php?page=onwp-options">Settings</a>'; 
        array_unshift($links, $settings_link); 
        return $links; 
    }

    // This line adds a linke to the "options" (settings) menu on the admin panel
    public function register_settings_menu() {
        add_options_page(
            'OneNote Publisher for  WordPress Options',
            'OneNote',
            'manage_options',
            'onwp-options',
            array(
                $this,
                'settings_page'
            )
        );
    }

    // Add our button to the HTML editor
    public function register_editor_buttons($buttons) {
        array_push($buttons, 'separator', 'onwp_plugin');  // Add a separater and our button to the HTML editor toolbar
        return $buttons;
    }

    // Inject our JavaScript file to the HTML when the HTML editor is loaded
    public function register_editor_buttons_logic($plugin_array) {
        $plugin_array['onwp_plugin'] = plugins_url('/js/plugin.js', __file__);
        return $plugin_array;
    }
    
    // Draws the settings page
    public function settings_page() {
        if ($_GET['settings-updated'] == 'true' && $_GET['page'] == 'onwp-options') {
            $this->options = get_option(self::$ONWP_PLUGIN_OPTIONS);
            self::set_access_token(null);  // Reset the access token = force a re-login with the new settings
        }

        $options = $this->options;
?>
    <div class="wrap">
            <h2>OneNote Publisher for WordPress Options</h2>
            
            <form method="post" action="options.php">  <!--This is the same for every plugin for saving from the settings form -->

                <?php settings_fields('onwp_settings_group'); ?>
                <h4><?php _e('MSA OAuth Settings', 'onwp_domain'); ?></h4>
                <p>
                    <label class="description" for="onwp_settings[client_id]"><?php _e('MSA ClientID: ', 'onwp_domain'); ?></label>   <!-- This saves the setting in an array -->
                    <input size="17" id="onwp_settings[client_id]" name="onwp_settings[client_id]" type="text" value="<?php echo $options['client_id']; ?>" />
                </p>

                <p>
                    <label class="description" for="onwp_settings[client_secret]"><?php _e('MSA Client Secret: ', 'onwp_domain'); ?></label>   <!-- This saves the setting in an array -->
                    <input size="33" id="onwp_settings[client_secret]" name="onwp_settings[client_secret]" type="text" value="<?php echo $options['client_secret']; ?>" />
                </p>

                <p>(<a href="<?php echo admin_url() . '?' . self::$INSTRUCTIONS_PAGE_QUERY_STRING_FLAG ?>" target="_blank">Click here for instructions to setup your ClientID & Secret</a>)</p>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Options', 'onwp_domain'); ?>" />
                </p>
            </form>
    </div>
<?php
    }
    
    public function set_client_side_variables() {
?>
<script type="text/javascript">

    var onwpPopupUrl = '<?php echo admin_url() . '?' . self::$POPUP_QUERY_STRING_FLAG ?>';
  
</script>
<?php
    }
    
    public function update_saved_page_content() {
?>
<script type="text/javascript">

    var pageData = <?php echo json_encode($this->page_contents); ?>;

    function updateContent() {
        if (typeof jQuery == 'undefined') {
            setTimeout(updateContent, 100);
        }
        else {
            jQuery(document).ready(
                function() {
                    if (typeof window._ONWPPluginImportDone == 'undefined') {
                        setTimeout(updateContent, 100);
                    }
                    else {
                        window._ONWPPluginImportDone(pageData);
                    }
                }
            );
        }
    }

    updateContent();
  
</script>
<?php
    }
}

new wp_onenote_plugin();

?>