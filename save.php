<?php

require_once 'includes/onwp-base.php';
require_once 'includes/QueryPath/qp.php';

class onwp_save extends onwp_base {
    protected static $ONENOTE_API_BASE_URL = 'https://www.onenote.com/api';
    protected static $ONENOTE_API_PRODUCTION_URL = '/v1.0';
    
    protected $access_token;
    protected $page_id;
    
    public function __construct($page_id) {
        parent::__construct();
        $this->page_id = $page_id;
        // Make sure we have an access_token for calling the OneNote API
        $this->access_token = parent::get_access_token();
        set_time_limit(120);
    }
    
	function save_page()
	{
        if ($this->access_token === false) {
            return false;
        }
        
        if (empty($this->page_id)) {
            return false;
        }
		
        $html = $this->api_request(self::$ONENOTE_API_BASE_URL . self::$ONENOTE_API_PRODUCTION_URL . "/pages/{$this->page_id}/content");
        
        if (stripos($html, 'http-equiv="content-type"') === false) {
            $html = str_replace('<head>', '<head><meta http-equiv="content-type" content="text/html; charset=utf-8">', $html);
        }
        
		// Parse the HTML into a qp object
		$qp = qp($html);
        
		// Get rid of the div absolute positioning noise
		$qp->find('div')->css('position', 'default');
		
		// Parse IMG tags
		foreach ($qp->find('img') as $key => $img){ 
			$src = $img->attr('src');
			$content = $this->api_request($src);
			$path = $this->save_image($content);
			$img->attr('src', $path);
		}
		
		// Return results
        
        $result = array(
            'title' => $qp->top('head')->top('title')->text(),
            'date'  => $qp->top('head')->top('meta')->attr('content'),
            'html'  => $qp->top('body')->html()
        );
        
        return $result;
	}
    
    function save_image($file_data) {
        //directory to import to	
        $uploads = wp_upload_dir();
        $imgFolder = 'imported-onenote-images';
        $imgDir = $uploads['basedir'] . DIRECTORY_SEPARATOR . $imgFolder . DIRECTORY_SEPARATOR;
        $imageBaseUrl = "{$uploads['baseurl']}/$imgFolder/";

        //if the directory doesn't exist, create it	
        if(!file_exists($imgDir)) {
            if(!mkdir($imgDir,0777,true)) {
                //Failed to create new directory
                return false;
            }
        }
        
        //rename the file
        $new_filename = 'onenote-'. uniqid('', true);
        $new_file_path = $imgDir . $new_filename;
        $handle = fopen($new_file_path, 'w');
        fwrite($handle, $file_data);
        fclose($handle);
        
        $siteurl = get_option('siteurl');
        $file_info = getimagesize($new_file_path);
        
        $old_file_path  = $new_file_path;
        $extension = image_type_to_extension($file_info[2]);
        $new_file_path .= $extension;
        rename($old_file_path, $new_file_path);
        
        $new_file_url = $imageBaseUrl . $new_filename . $extension;

        //create an array of attachment data to insert into wp_posts table
        $imgdata = array();
        $imgdata = array(
            'post_author' => 1, 
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql'),
            'post_title' => $new_filename, 
            'post_status' => 'inherit',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $new_filename)), 'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql'),
            'post_parent' => 0,
            'post_type' => 'attachment',
            'guid' => $new_file_url,
            'post_mime_type' => $file_info['mime'],
            'post_excerpt' => '',
            'post_content' => ''
        );

        //insert the database record
        $attach_id = wp_insert_attachment($imgdata, $new_file_path);

        //generate metadata and thumbnails
        if ($attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path)) {
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
        
        return $imgdata['guid']; //Returns the URL to the image saved
    }
    
    // Perform an API request
    function api_request($endpoint)
    {
        $request_headers = array(
			'Authorization' => "Bearer {$this->access_token}"
        );

        $request_arguments = array(
            'headers' => $request_headers,
            'httpversion' => '1.1',
            'user-agent' => parent::$USER_AGENT,
            'timeout' => 60
        );

        $response = wp_remote_get($endpoint, $request_arguments);
        return $response['body'];
    }
}
?>