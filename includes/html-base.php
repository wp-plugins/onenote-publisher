<?php

require_once('onwp-base.php');

abstract class onwp_html_base extends onwp_base {
    protected $doc_type = '<!doctype html>';
    protected $headers = array(
        'Content-Type' => 'text/html; charset=utf-8'
    );
    protected $title = '';
    protected $metas = array(
        'content-type' => 'text/html; charset=utf-8'
    );
    protected $css = array();
    protected $scripts = array();
    
    public function draw() {
        $this->headers();
        $this->content();
    }
    
    protected function headers() {
        foreach ($this->headers as $header_name => $header_value) {
            header("$header_name: $header_value");
        }
    }
    
    protected function content() {
        $this->doc_type();
        $this->html_open();
        $this->head();
        $this->body();
        $this->html_close();
    }
    
   protected function doc_type() {
       echo $this->doc_type;
   }
    
    protected function html_open() {
        echo '<html>';
    }
    
    protected function html_close() {
        echo '</html>';
    }
    
    protected function head() {
        $this->head_open();
        $this->head_content();
        $this->head_close();
    }
    
    protected function head_open() {
        echo '<head>';
    }

    protected function head_content() {
        echo '<title>' . htmlentities($this->title) . '</title>';
        
        foreach ($this->metas as $http_equiv => $content) {
            echo "<meta http-equiv=\"$http_equiv\" content=\"$content\"/>";
        }
        
        foreach ($this->css as $css_href) {
            echo "<link rel=\"stylesheet\" href=\"$css_href\"/>";
        }

        foreach ($this->scripts as $script_src) {
            echo "<script src=\"$script_src\"></script>";
        }
    }
    
    protected function head_close() {
        echo '</head>';
    }
    
    protected function body() {
        $this->body_open();
        $this->body_content();
        $this->body_close();
    }
    
    protected function body_open() {
        echo '<body>';
    }
    
    protected abstract function body_content();
    
    protected function body_close() {
        echo '</body>';
    }
}

?>