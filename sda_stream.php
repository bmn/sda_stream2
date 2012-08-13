<?php
/*
  sda_stream
  Copyright (c) Ian Bennett <webdev at ianbennett dot net> 2011.
  
  sda_stream is licensed under a Creative Commons Attribution-Share Alike 2.0
    UK: England & Wales License.
  <http://creativecommons.org/licenses/by-sa/2.0/uk/>
  
  The latest version of this software is available at:
  <http://github.com/bmn/sda_stream/>
*/

require_once( dirname(__FILE__) . '/sda_exceptions.php' );

class SDAStream {

  private
    $api = null,
    $channels = null,
    $apis = null,
    $default_api = null,
    $single = false,
    $raw = false,
    $include = null,
    $callback = 'sda_stream',
    $ttl = 0,
    $last_error = 0,
    $post = null,
    $output = null,
    $timeout = 30;
  public
    $results = null,
    $errors = array();
  public static
    $options = array('channels', 'apis', 'include', 'api', 'default_api', 'callback', 'ttl', 'single', 'raw', 'post', 'output', 'error_level');
  
  protected static function array_flatten($a) {
    foreach ($a as $k => $v) $a[$k] = (array)$v;
    return call_user_func_array(array_merge, $a);
  }
  
  public static function get_sock($url) {
    /*
    $url_stuff = parse_url($url);
    $port = isset($url_stuff['port']) ? $url_stuff['port'] : 80;
    $fp = fsockopen($url_stuff['host'], $port, $errno, $errstr, 5);
    if (!$fp) {
      // error stuff here
    }
    $query  = 'GET ' . $url_stuff['path'] . " HTTP/1.0\n";
    $query .= 'Host: ' . $url_stuff['host'];
    $query .= "\n\n";
    fwrite($fp, $query);
    while ($tmp = fread($fp, 1024)) { $buffer .= $tmp; }
    return substr($buffer, strrpos($buffer, "\n") + 1); 
    */
    new SDANotice("Requesting URL $url using sockets.");
    return file_get_contents($url);
  }
  
  public static function get_curl($urls) {
    $reqs = $responses = array();
    $req = curl_multi_init();
    new SDANotice('Making '. count($urls) .' request' .((count($urls) == 1) ? '':'s'). ' via CURL.');
    foreach ($urls as $k => $v) {
      new SDANotice("Requesting URL $v using CURL.");
      $reqs[$k] = curl_init($v);
      curl_setopt($reqs[$k], CURLOPT_HEADER, false);
      curl_setopt($reqs[$k], CURLOPT_RETURNTRANSFER, true);
      curl_setopt($reqs[$k], CURLOPT_TIMEOUT, 10);
      curl_multi_add_handle($req, $reqs[$k]);
    }
    $running = null;
    do { $tmp = curl_multi_exec($req, $running); }
    while ($tmp == CURLM_CALL_MULTI_PERFORM);
    while ($running && ($tmp == CURLM_OK)) {
      usleep(10000);
      $numberReady = curl_multi_select($req);
      if ($numberReady != -1) {
        do { $tmp = curl_multi_exec($req, $running); }
        while ($tmp == CURLM_CALL_MULTI_PERFORM);
      }
    }
    foreach ($reqs as $k => $v) {
      $responses[] = curl_multi_getcontent($reqs[$k]);
      curl_multi_remove_handle($req, $reqs[$k]);
      curl_close($reqs[$k]);
    }
    new SDANotice('Received '. count($responses) .' response' .((count($responses) == 1) ? '':'s'). '.');
    curl_multi_close($req);
    return $responses;
  }
  
  public static function query_api($requests, $format = 'json') {
    $responses = array();
    if (function_exists('curl_multi_init')) {
      // Make requests using curl if it's available
      new SDANotice('Making HTTP requests using CURL.');
      $responses = self::get_curl($requests);
    } else {
      // Else use a slower fsockopen-based method
      new SDANotice('CURL not available: Making HTTP requests using sockets.');
      foreach ($requests as $v) $responses[] = self::get_sock($v);
    }
    $out = array();
    foreach ($responses as $r) $out[] = self::unserialize($r);
    return $out;
  }
  
  public function headers() {
    header("Expires: ".date('r', $this->expires));
    header("Last-Modified: ".date('r', time()));
  }
  
  private static function cache_path($file, $format) {
    $ext = ($format == 'jsonp') ? 'js' : $format;
    return dirname(__FILE__)."/cache/$file.$ext";
  }
  
  public static function read_cache($file, $ttl = null, $format = 'jsonp') {
    $path = self::cache_path($file, $format);
    if (!file_exists($path)) {
      new SDANotice("Cache $file is missing");
      return false;
    }
    if (is_numeric($ttl)) {
      $next_update = ( filemtime($path) + ($ttl * 60) );
      $time_left = $next_update - time();
    } else {
      $next_update = time() + 1;
      $time_left = 1;
    }
    if ($time_left > 0) {
      new SDANotice("Cache $file (". number_format(filesize($path)) .'B) next updated at '. date('g:i:s', $next_update) .'.');
      return (is_readable($path)) ?
        self::unserialize(file_get_contents($path), $format, $file) :
        new SDAWarning("$file could not be read.", false);
    } else {
      new SDANotice("Cache $file died at ". date('g:i:s', $next_update) .'.', false);
      return false;
    }
  }
  
  public static function write_cache($file, $data, $format = 'jsonp') {
    $dir = dirname(__FILE__).'/cache/';
    $path = self::cache_path($file, $format);
    $dir_exists = is_dir(dirname($path));
    if (!$dir_exists) {
      // Make the cache directory if it doesn't exist
      if (($dir_exists = mkdir(dirname($path), 0666)) !== false) {
        new SDANotice("Created cache directory $dir.");
      } else return new SDAWarning("Could not create cache directory: $php_errormsg", false);
    }
    if ($dir_exists) {
      if ( (!file_exists($path)) || (is_writable($path)) ) {
        $data = self::serialize($data, $format, $file);
        return file_put_contents($path, $data) ?
          new SDANotice("Wrote cache $file (". number_format(strlen($data)) .'B).') :
          new SDAWarning("Could not write cache $file: $php_errormsg", false);
      } else return new SDAWarning("Cache $file reported as unwritable by the filesystem.", false);
    }
  }
  
  public static function delete_cache($file, $format = 'jsonp') {
    $path = self::cache_path($file, $format);
    if (file_exists($path)) {
      if ( (!is_writable($path)) || (!unlink($path)) )
        return new SDAWarning("Cache $file exists but cannot be deleted.");
      else return new SDANotice("Cache $file has been deleted.");
    } else return new SDANotice("Cache $path does not exist.");
  }
  
  public static function unserialize($c, $format = 'json', $callback = null) {
    if ($callback) $c = substr($c, strlen($callback) + 1, -1);
    switch ($format) {
      case 'xml': $out = xmlrpc_decode($c); break;
      case 'php': $out = unserialize($c); break;
      case false: $out = $c; break;
      default:    $out = json_decode($c, true);
    }
    if ($format) new SDANotice('Unserialized '. number_format(strlen($c)) .'B from '. strtoupper($format) .'.');
    return $out;
  }
  
  public static function serialize($c, $format = 'json', $callback = null) {
    switch ($format) {
      case 'xml': $out = xmlrpc_encode($c); break;
      case 'php': $out = serialize($c); break;
      case false: $out = $c; break;
      default:
        $out = json_encode($c);
        if ($callback) {
          $out = "$callback($out)";
          $format = 'jsonp';
        }
    }
    if ($format) new SDANotice('Serialized to '. strtoupper($format) .' ('. number_format(strlen($out)) .'B).');
    return $out;
  }
  
  private static function process_channels($channels, $api) {
    $out = array();
    if (!is_array($channels)) $channels = array($channels);
    if ($api) {
      foreach ($channels as $k => $c) $out[$api.'_'.$k] = self::process_channel($k, $c);
    } else {
      foreach ($channels as $k => $c) {
        $tmp = self::process_channel($k, $c);
        $out[$tmp['api'].'_'.$tmp['channel']] = $tmp;
      }
    }
    return $out;
  }
  
  private static function process_apis($apis) {
    $out = array();
    foreach ($apis as $api => $a) {
      foreach ($a as $k => $c) $out[$api.'_'.strtolower($k)] = self::process_channel($k, $c, $api);
    }
    return $out;
  }
  
  private static function process_channel($k, $c, $api = false) {
    $lower = strtolower($k);
    if (!is_array($c)) {
      if (!is_string($k)) {
        // Convert basic string value into full format
        $lower = strtolower($c);
        $c = array('channel' => $lower);
      }
      else {
        // Convert channel => synopsis into full format
        $c = array(
          'channel' => $lower,
          'default' => array('synopsis' => $c),
        );
      }
    } elseif (!empty($c['channel'])) $lower = $c['channel'] = strtolower($c['channel']);
    // Set channel and API explicitly if not already set
    if ( (!empty($api)) && (empty($c['api'])) ) $c['api'] = $api;
    if (empty($c['channel'])) $c['channel'] = $lower;
    // Set other fields to null if not set
    foreach (array('add', 'default') as $k) {
      if (!isset($c[$k])) $c[$k] = null;
    }
    return $c;
  }
  
  public static function separate_by(&$channels, $field, $default, $keys = false) {
    $out = array();
    if (is_array($keys)) {
      foreach ($keys as $v) $out[$v] = array();
    }
    foreach ($channels as $c) {
      $ar = ($c[$field]) ? $c[$field] : $default;
      if ( (!isset($out[$ar])) || (!is_array($out[$ar])) ) $out[$ar] = array();
      $out[$ar][] = $c;
    }
    ksort($out);
    return $out;
  }
  
  private static function translate($channel, $api, $raw) {
    $out = array();
    foreach (call_user_func(array("SDAStream$api", 'fields')) as $k => $f) {
      $out[$k] = self::array_val($channel, $f);
    }
    $out['api'] = $api;
    if ($raw) $out['raw'] = $channel;
    return $out;
  }
  
  private static function set_fields(&$channel, $add, $default, $include)
  {
    if ( (is_array($include)) && (count($include)) ) {
      $out = array();
      foreach ($include as $v) $out[$v] = $channel[$v];
      $channel = $out;
    }
    if ( (is_array($add)) && (count($add)) ) {
      foreach ($add as $k => $v) $channel[$k] = $v;
    }
    if ( (is_array($default)) && (count($default)) ) {
      foreach ($default as $k => $v) {
        if (!isset($channel[$k])) $channel[$k] = $v;
      }
    }
  }
  
  private static function array_val($arr, $string) {
    preg_match_all('/\[([^\]]*)\]/', $string, $arr_matches, PREG_PATTERN_ORDER);
    $return = $arr;
    foreach($arr_matches[1] as $dimension) {
      if (isset($return[$dimension])) $return = $return[$dimension];
      else return false;
    }
    return $return;
  }
  
  public function set_embed_dimensions($width, $height) {
    if (!$this) return false;
    foreach ($this->results as &$c) {
      $c['embed_stream'] = preg_replace(
        array('/width="\d+"/', '/height="\d+"/'),
        array('width="'.$width.'"', 'height="'.$height.'"'),
        $c['embed_stream']
      );
    }
    return $this;
  }
  
  public static function array_every($arr, $field) {
    $out = array();
    foreach ($arr as $a) $out[] = $a[$field];
    return $out;
  }
  
  public function filter($function, $arr = null) {
    if ((!isset($arr)) && (isset($this))) $arr = $this->results;
    if (substr($function, -1) != ';') $function .= ';';
    return (is_array($arr)) ? array_filter($arr, create_function('$a', $function)) : false;
  }
  
  public function sort($function, $arr_or_apply = null) {
    $arr = ((!is_array($arr_or_apply)) && (isset($this))) ? $this->results : $arr;
    if (is_array($arr)) {
      if (substr($function, -1) != ';') $function .= ';';
      usort($arr, create_function('$a, $b', $function));
      if ($arr_or_apply == true) {
        $this->results = $arr;
        return $this;
      }
      return $arr;
    }
    return false;
  }
  
  private function combine_data($include = null) {
    $function = create_function('$e', 'return array("level" => $e->getCode(), "message" => $e->getMessage());' );
    if (!is_array($include)) $include = array();
    return array_merge($include, array(
      'results' => $this->results,
      'log'     => SDAExceptions()->exceptions($function),
    ));
  }

  public function user_post_process($post) {
    if (!$post) return false;
    $i = 0;
    if (!is_array($post)) $post = array($post);
    foreach ($post as $p) {
      if (is_callable($p)) {
        $p($this);
        $i++;
      }
    }
    return $i;
  }
  
  public function SDAStream($d = array()) {
    foreach (self::$options as $var) {
      if (!isset($d[$var])) $d[$var] = null;
    }
    if (is_array($d['channels'])) $this->channels = $d['channels'];
    if (is_array($d['apis'])) $this->apis = $d['apis'];
    if (is_array($d['include'])) $this->include = $d['include'];
    if (is_string($d['api'])) $this->api = $d['api'];
    if (is_string($d['default_api'])) $this->default_api = $d['default_api'];
    if (is_string($d['callback'])) $this->callback = $d['callback'];
    if (is_numeric($d['ttl'])) $this->ttl = $d['ttl'];
    if (is_bool($d['single'])) $this->single = $d['single'];
    if (is_bool($d['raw'])) $this->raw = $d['raw'];
    if (is_callable($d['post'])) $this->post = $d['post'];
    if (is_callable($d['output'])) $this->output = $d['output'];
    if (is_int($d['error_level'])) SDAStreamExceptions::set_error_level($d['error_level']);
    return $this;
  }
  
  public function get($d = array()) {
    if (isset($this)) {
      // Return an existing result 
      if (($this->results) && (!$d['refresh'])) return $this->results;
      // Handle settings if called from instance
      $channels = isset($d['channels']) ? $d['channels'] : $this->channels;
      $apis = isset($d['apis']) ? $d['apis'] : $this->apis;
      $include = isset($d['include']) ? $d['include'] : $this->include;
      $api = isset($d['api']) ? $d['api'] : $this->api;
      $single = isset($d['single']) ? $d['single'] : $this->single;
      $raw = isset($d['raw']) ? $d['raw'] : $this->raw;
      $callback = isset($d['callback']) ? $d['callback'] : $this->callback;
      $ttl = isset($d['ttl']) ? $d['ttl'] : $this->ttl;
      $post = isset($d['post']) ? $d['post'] : $this->post;
      $output = isset($d['output']) ? $d['output'] : $this->output;
    } else {
      // Otherwise initialise and return get()
      $out = new SDAStream($d);
      return $out->get();
    }
    
    // Check for a cached version and return it if it's still live
    if ( $callback && $ttl ) {
      // Get the cache
      $cache_out = self::read_cache($callback, $ttl);
      // Make sure another instance isn't already working
      // Return the cache anyway if it is
      if (!$cache_out) {
        $working = $callback.'_working';
        $latest = self::read_cache($working, 'php');
        if ($latest) {
          if ( $latest < (time() - $this->timeout) ) self::delete_cache($working, 'php');
          else $cache_out = self::read_cache($callback);
        }
      }
      if ($cache_out) {
        $this->results = $cache_out['results'];
        return $this;
      }
      // If we're going to work, do what we can to avoid a race condition
      self::write_cache($working, time(), 'php');
    }
    
    // Input process the APIs/channels
    $channels = is_array($apis) ? self::process_apis($apis) : self::process_channels($channels, $api);
    $single = (($single == true) && (count($channels) == 1));
    
    // Query each API
    if ($api) $results[$api] = self::run_api($api, $channels, $ttl, $callback);
    else {
      $api_channels = self::separate_by($channels, 'api', 'ustream');
      foreach ($api_channels as $k => $v) {
        $results[$k] = self::run_api($k, $v, $ttl, $callback);
      }
    }
    
    // Post-process the results
    foreach ($results as $api => $channel) {
      foreach ($channel as $c) {
        $ar = self::translate($c, $api, $raw);
        $ch = $channels[$api.'_'.$ar['channel_name']];
        if (!$ch) $ch = $channels[$api.'_'.$ar['channel_id']];
        self::set_fields($ar, $ch['add'], $ch['default'], $include);
        $out[] = $ar;
      }
    }
    $this->results = $out;
    
    // Run user post-process task(s)
    if ($post) $this->user_post_process($post);
    
    // Save the results
    $this->results = ($single) ? reset($this->results) : $this->results;
    
    // Write the cache if requested
    if ($callback && $ttl) {
      self::write_cache($callback, $this->combine_data($output));
      self::delete_cache($working, 'php');
    }
    
    // Return the instance
    return $this;
  }
  
  private static function run_api($api, &$channels, $ttl, $callback) {
    // Require the API class
    $path = dirname(__FILE__).'/sda_stream_'.$api.'.php';
    if (!is_readable($path)) return false;
    require_once($path);
    // Look for a cached result first
    $api_ttl = constant('SDAStream'.$api.'::ttl');
    if ( ($callback) && ($api_ttl) && ($ttl < $api_ttl) ) $out = self::read_cache($callback.'_'.$api, $api_ttl, 'php');
    // If no cache, call the API and cache if necessary
    if (empty($out)) {
      $out = call_user_func(array('SDAStream'.ucfirst($api), 'query'), $channels, $ttl);
      if ($ttl < $api_ttl) self::write_cache($callback.'_'.$api, $out, 'php');
    }
    return $out;
  }
  
  public function output($format, $include = null) {
    return ($format == 'jsonp') ? self::serialize($this->combine_data($include), 'json', $this->callback) : self::serialize($this->combine_data($include), $format);
  }
  
}

// Return the API in JSON format if this file is being requested, not included
$_included_files = get_included_files();
if (reset($_included_files) == __FILE__) {
  include '../config.php';
  if ( (!is_array($channels)) && (!is_array($apis)) )
    die('Config not provided by config.php');
  $streams = SDAStream::get( array(
    'channels'    => $channels,
    'apis'        => $apis,
    'ttl'         => $ttl,
    'callback'    => $callback,
    'include'     => $include,
    'api'         => $api,
    'default_api' => $default_api,
    'single'      => $single,
    'raw'         => $raw,
    'post'        => $post,
    'output'      => $output,
  ) );
  if (is_callable($run)) $run($streams);
  else echo $streams->output('jsonp', $output);
}
