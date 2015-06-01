<?php

require_once 'includes/html-base.php';

class onwp_msa_instructions extends onwp_html_base {
    public function __construct() {
        parent::__construct();
        $this->title = 'Instructions for Acquiring MSA ClientID & Client Secret';
        $this->draw();
    }
    
    public function body_content() {
        $images_path = plugins_url('images/', __file__);
    
?>
<h1>Instructions for Acquiring MSA ClientID & Client Secret</h1>

<ol>
    <li><p>Go to <a href="https://account.live.com/developers/applications/" target="_blank">https://account.live.com/developers/applications/</a></p></li>
    <li><p>Sign in with your Microsoft Account (or create a new one)</p></li>
    <li><p>Click "<b>Create application</b>"<br /><br /><img src="<?php echo $images_path ?>msa-instructions/step3.png" border="2"/></p></li>
    <li><p>Enter any title for your "<b>Application Name</b>" and select a language then click "<b>I Accept</b>"<br /><br /><img src="<?php echo $images_path ?>msa-instructions/step4.png" border="2"/></p></li>
    <li><p>Select "<b>API Settings</b>" on the left.<br /><br /><img src="<?php echo $images_path ?>msa-instructions/step5.png" border="2"/></p></li>
    <li><p>On the "<b>API Settings</b>" page:</p></li>
        <ul>
            <li><p>Set "<b>Restrict JWT issuing:</b>" to "<b>NO</b>"</p></li>
    <li><p>Set the "<b>Redirect URL</b>" to <b><?php echo admin_url(); ?></b></p></li>
    <li><p>Click "<b>Save</b>"<br /><br /><img src="<?php echo $images_path ?>msa-instructions/step6.png" border="2"/></p></li>
            
        </ul>
    
    <li><p>Select "<b>App Settings</b>" on the left</p></li>
    <li><p>Copy and pase the <b>Client ID</b> and <b>Client Secret</b> into the Wordpress settings and click "<b>Save Options</b>"<br /><br /><img src="<?php echo $images_path ?>msa-instructions/step8.png" border="2"/></p></li>


</ol>
<?php
    }
}

new onwp_msa_instructions();
