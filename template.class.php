<?php

/*
    [miniTemplate for SAE	作者：李博]
 */

class template {

    private $cache_enable = true;
    private $rewrite_enable = true;
    private $tplfolder;
    private $tplfile;
    private $objfile;
    private $vars = array();
    private $var_regexp = "\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*";
    private $vtag_regexp = "\<\?=(\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)\?\>";
    private $const_regexp = "\{([\w]+)\}";
    private static $memcache;

    public function __construct() {
        self::$memcache = memcache_init();
        ob_start();
    }

    public function set_base_dir($tplfolder) {
        $this->tplfolder = $tplfolder;
    }

    public function set_cache_status($status) {
        $this->cache_enable = empty($status) ? false : true;
    }

    public function set_rewrite_status($status) {
        $this->rewrite_enable = empty($status) ? false : true;
    }

    public function assign($k, $v) {
        $this->vars[$k] = $v;
    }

    public function display($file) {
        extract($this->vars, EXTR_SKIP);
        $this->gettpl($file);

        eval('?>'.self::$memcache->get($this->objfile));
    }

    private function gettpl($file) {
        $this->objfile = $_SERVER['HTTP_APPVERSION'] . '_' . $this->tplfolder.'_'.$file;
        $this->objfile = md5($this->objfile);

        $this->tplfile = $this->tplfolder.'/'.$file;

        $filetime = filemtime($this->tplfile);
        $time_key = $this->objfile . '_' . 'time';
        $lasttime = self::$memcache->get($time_key);

        if (((empty($lasttime)) || $filetime > $lasttime) || !$this->cache_enable) {
            self::$memcache->set($time_key, $filetime);
            $this->complie();
        }
    }

    private function complie() {
        $template = file_get_contents($this->tplfile);
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);

        $template = preg_replace("/\{($this->var_regexp)\}/", "<?=\\1?>", $template);
        $template = preg_replace("/\{($this->const_regexp)\}/", "<?=\\1?>", $template);
        $template = preg_replace("/(?<!\<\?\=|\\\\)$this->var_regexp/", "<?=\\0?>", $template);


        $template = preg_replace("/\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[\\$\[\]\w]+\])+)\?\>/ies", "self::arrayindex('\\1', '\\2')", $template);

        $template = preg_replace("/\{\{eval (.*?)\}\}/ies", "self::stripvtag('<? \\1?>')", $template);
        $template = preg_replace("/\{eval (.*?)\}/ies", "self::stripvtag('<? \\1?>')", $template);

        $template = preg_replace("/\{elseif\s+(.+?)\}/ies", "self::stripvtag('<? } elseif(\\1) { ?>')", $template);

        for($i=0; $i<2; $i++) {
            $template = preg_replace("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/ies", "self::loopsection('\\1', '\\2', '\\3', '\\4')", $template);
            $template = preg_replace("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/ies", "self::loopsection('\\1', '', '\\2', '\\3')", $template);
        }

        $template = preg_replace("/\{if\s+(.+?)\}/ies", "self::stripvtag('<? if(\\1) { ?>')", $template);

        $template = preg_replace("/\{template\s+(\w+?)\}/is", "<? include self::gettpl('\\1');?>", $template);
        $template = preg_replace("/\{template\s+(.+?)\}/ise", "self::stripvtag('<? include self::gettpl(\\1); ?>')", $template);


        $template = preg_replace("/\{else\}/is", "<? } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/is", "<? } ?>", $template);

        $template = preg_replace("/$this->const_regexp/", "<?=\\1?>", $template);

        $template = preg_replace("/(\\\$[a-zA-Z_]\w+\[)([a-zA-Z_]\w+)\]/i", "\\1'\\2']", $template);

        self::$memcache->set($this->objfile, $template);
    }

    private static function arrayindex($name, $items) {
        $items = preg_replace("/\[([a-zA-Z_]\w*)\]/is", "['\\1']", $items);
        return "<?=$name$items?>";
    }

    private function stripvtag($s) {
        return preg_replace("/$this->vtag_regexp/is", "\\1", str_replace("\\\"", '"', $s));
    }

    private function loopsection($arr, $k, $v, $statement) {
        $arr = self::stripvtag($arr);
        $k = self::stripvtag($k);
        $v = self::stripvtag($v);
        $statement = str_replace("\\\"", '"', $statement);
        return $k ? "<? foreach((array)$arr as $k => $v) {?>$statement<?}?>" : "<? foreach((array)$arr as $v) {?>$statement<? } ?>";
    }

    private static function rewrite($content) {
        //do some replace for rewriting

        return $content;
    }

    public function __destruct() {
        $content = ob_get_contents();
        ob_end_clean();

        if ($this->rewrite_enable) {
            $content = self::rewrite($content);
        }

        echo $content;
    }
}
?>
