<?php

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamTwitch extends SDAStream {

  const ttl = 1;
  const limit = 100;

  protected static function fields() {
    return array(
      'user_name'     => "[channel][name]",
      'user_url'      => "[channel][url]",
      'channel_id'    => "[channel][_id]",
      'channel_name'  => "[channel][name]",
      //'channel_title' => "[channel][title]",
      'channel_url'   => "[channel][url]",
      'embed_stream'  => "[channel][embed_code]",
      'description'   => "[channel][status]",
      'online'        => "[online]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = array();
    
    // Create API URL and make first request
    $keys = parent::array_every($channels, 'channel');
    $url = array( self::api_url( implode(',', $keys) ) );
    $responses = parent::query_api($url);
    
    // Get the number of results and (if necessary) create later requests
    $total = $responses[0]['_total'];
    if ( (is_int($total)) && ($total > self::limit) ) {
      $requests = array();
      $ct = ceil($total / self::limit);
      for ($i = 1; $i <= $ct; $i++) {
        $requests[] = $url . '&offset=' . ($i * self::limit);
      }
      $responses = array_merge($responses, parent::query_api($requests));
    }
        
    // Post-process the results
    $out = $online = array();
    $class = get_called_class();
    foreach ($responses as $response) {
      foreach ($response['streams'] as $c) {
        $out[] = $class::post_process($c);
        $online[$c['channel']['name']] = $c['online'] = true;
        
      }
    }
    
    // Recreate (or at least try to) the offline channels
    // (Twitch's stream search doesn't return offline channels)
    foreach ($channels as $c) {
      if (!isset($online[$c['channel']])) {
        $out[] = $class::post_process(array(
          'channel'  => array(
            'name'        => $c['channel'],
            'url'         => "http://www.twitch.tv/{$c['channel']}",
            'embed_code'  => self::embed_channel($c['channel']),
          ),
          'online'        => false,
        ));
      }
    }

    return $out;
  }
  
  protected static function api_url($channels) {
    return "https://api.twitch.tv/kraken/streams?channel=$channels&limit=" . self::limit;
  }
  
  protected static function post_process($c) {
    if (!isset($c['online'])) $c['online'] = true;
    $c['channel']['embed_code'] = self::embed_channel($c['channel']['name']);
    return $c;
  }

  public static function embed_channel($c) {
    return <<<HTML
<object type="application/x-shockwave-flash" height="295" width="353" id="live_embed_player_flash" data="http://www.twitch.tv/widgets/live_embed_player.swf?channel=$c" bgcolor="#000000"><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://www.twitch.tv/widgets/live_embed_player.swf" /><param name="flashvars" value="start_volume=25&hostname=www.twitch.tv&channel=$c&auto_play=false" /></object>
HTML;
  }

  public static function embed_chat($c) {
    return <<<HTML
<iframe frameborder="0" scrolling="no" id="chat_embed" src="http://twitch.tv/chat/embed?channel=$c&amp;popout_chat=true" height="301" width="221"></iframe>
HTML;

  }

  
}
