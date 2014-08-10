<?php

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamHitbox extends SDAStream {

  const ttl = 1;

  protected static function fields() {
    return array(
      'user_name'     => "[media_user_name]",
      'user_url'      => "[channel][channel_link]",
      'channel_id'    => "[media_id]",
      'channel_name'  => "[media_user_name]",
      //'channel_title' => "[channel][title]",
      'channel_url'   => "[channel][channel_link]",
      'embed_stream'  => "[channel][embed_code]",
      'description'   => "[media_description]",
      'online'        => "[media_is_live]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = array();
    
    // Create API URL and make request
    $keys = parent::array_every($channels, 'channel');
    $url = array( self::api_url( implode(',', $keys) ) );
    $responses = parent::query_api($url);
        
    // Post-process the results
    $out = $online = array();
    $class = get_called_class();
    foreach ($responses as $response) {
      foreach ($response['livestream'] as $c) {
        $out[] = $class::post_process($c);
      }
    }

    return $out;
  }
  
  protected static function api_url($channels) {
    return "http://api.hitbox.tv/media/live/$channels";
  }
  
  protected static function post_process($c) {
    $c['channel']['embed_code'] = self::embed_channel($c['media_user_name']);
    $c['media_is_live'] = ($c['media_is_live']);
    return $c;
  }

  public static function embed_channel($c) {
    return <<<HTML
<iframe width="393" height="295" src="http://hitbox.tv/#!/embed/$c" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
HTML;
  }

  public static function embed_chat($c) {
    return <<<HTML
<iframe width="221" height="301" src="http://www.hitbox.tv/embedchat/$c" frameborder="0" allowfullscreen></iframe>

HTML;

  }

  
}
