<?php

/* Beam.pro module for sda_stream2
 * 
 * This module suggests that you provide a username that is following all desired channels.
 * If the username is not provided, the module will run a separate request for each channel,
 *   which can be very poor for performance if you have more than a few channels.
 */

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamBeam extends SDAStream {

  const ttl = 1;

  protected static function fields() {
    return array(
      'user_name'     => "[user][username]",
      'user_url'      => "[url]",
      'channel_id'    => "[id]",
      'channel_name'  => "[user][username]",
      //'channel_title' => "[channel][title]",
      'channel_url'   => "[url]",
      'embed_stream'  => "[embed_code]",
      'description'   => "[name]",
      'online'        => "[online]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = $urls = array();
    
    // (TODO) See which mode we're in
    
    // (TODO) Following mode
    
    // Individual channels mode
    $ct = count($channels);
    if ($ct > 1) {
      new SDANotice("You're requesting $ct Beam channels. We recommend using the Following feature instead to minimise requests.");
    }
    
    // Create API URLs and make requests
    $keys = parent::array_every($channels, 'channel');
    foreach ($keys as $k) {
      $urls[] = self::api_url($k);
    }
    $responses = parent::query_api($urls);
    
    // Post-process the results
    $out = array();
    $class = get_called_class();
    foreach ($responses as $c) {
      $out[] = $class::post_process($c);
    }

    return $out;
  }
  
  protected static function api_url($c, $mode = 'channel') {
    return "https://beam.pro/api/v1/channels/$c";
  }
  
  protected static function post_process($c) {
    $c['embed_code'] = self::embed_channel($c['token']);
    $c['url'] = "https://beam.pro/{$c['token']}";
    return $c;
  }

  public static function embed_channel($c) {
    return <<<HTML
<iframe height="295" width="353" frameborder="0" scrolling="no" src="https://beam.pro/embed/player/$c"><p>Your browser does not support iframes.</p></iframe>
HTML;
  }

  public static function embed_chat($c) {
    return <<<HTML
<iframe frameborder="0" scrolling="no" id="chat_embed" src="https://beam.pro/embed/chat/$c" height="301" width="221"><p>Your browser does not support iframes.</p></iframe>
HTML;
  }

  
}
