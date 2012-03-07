<?php

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamUstream extends SDAStream {

  const ttl = 0;
  const max_per_request = 10;
  const error_channel = 'ERR102';

  protected static function fields() {
    return array(
      'user_id'       => "[user][id]",
      'user_name'     => "[user][userName]",
      'user_url'      => "[user][url]",
      'channel_id'    => "[id]",
      'channel_name'  => "[urlTitleName]",
      'channel_url'   => "[url]",
      'embed_stream'  => "[embedTag]",
      'embed_chat'    => "[chat][embedTag]",
      'description'   => "[description]",
      'online'        => "[status]",
      'last_online'   => "[lastStreamedAt]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = array();
    
    // Create API URLs and make requests
    $total = count($channels);
    for ($i = 0; $i < $total; $i += 10) {
      $sets[] = parent::array_every(array_slice($channels, $i, 10, true), 'channel');
      $requests[] = self::api_url( implode(';', end($sets) ) );
    }
    $responses = parent::query_api($requests);
    
    // Create backup requests for errors
    foreach ($responses as $k => $c) {
      if ($c['error'] == self::error_channel) $s_sets[] = $sets[$k];
      else $results[] = $c;
      
      if (count($s_sets)) {
        $s_sets = parent::array_flatten($s_sets);
        foreach ($s_sets as $k => $c) $s_requests[$k] = self::api_url($c);
      }
    }
    
    // Handle backup responses
    $fails = ceil(count($s_requests) / 10);
    if ($fails) {
      new SDAWarning($fails .' Ustream request'. (($fails != 1) ? 's' : '') .' failed.');
      $s_responses = parent::query_api($s_requests);
      foreach ($s_responses as $k => $c) {
        if ($c['error'] == self::error_channel) new SDAWarning("Ustream channel {$s_sets[$k]} does not exist and should be removed.");
        else $results[] = $c;
      }
    }
    
    // Flatten the results and post-process
    $out = array();
    foreach ($results as $r) {
      if (is_array($r['results'][0])) {
        // We got multiple records in this response
        foreach ($r['results'] as $c) $out[] = self::post_process($c['result']);
      } else $out[] = self::post_process($r['results']); // Only 1 record
    }
    
    return $out;
  }
  
  private static function api_url($channels) {
    return "http://api.ustream.tv/json/channel/$channels/getInfo";
  }
  
  private static function post_process($c) {
    $c['status'] = ($c['status'] == 'live');
    return $c;
  }

  public static function embed_channel($c) {
    return <<<HTML
<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" width="353" height="295" id="utv817863"><param name="flashvars" value="autoplay=false&amp;brand=embed&amp;cid=$c&amp;v3=1"/><param name="allowfullscreen" value="true"/><param name="allowscriptaccess" value="always"/><param name="movie" value="http://www.ustream.tv/flash/viewer.swf"/><embed flashvars="autoplay=false&amp;brand=embed&amp;cid=$c&amp;v3=1" width="480" height="296" allowfullscreen="true" allowscriptaccess="always" id="utv817863" name="utv_n_441672" src="http://www.ustream.tv/flash/viewer.swf" type="application/x-shockwave-flash" /></object>
HTML;
  }

  public static function embed_chat($c) {
    return <<<HTML
<iframe width="468" scrolling="no" height="586" frameborder="0" style="border: 0px none transparent;" src="http://www.ustream.tv/socialstream/$c"></iframe>
HTML;

  }

}