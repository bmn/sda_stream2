<?php

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamJustin extends SDAStream {

  const ttl = 1;

  protected static function fields() {
    return array(
      'user_name'     => "[channel][login]",
      'user_url'      => "[channel][channel_url]",
      'channel_id'    => "[channel][id]",
      'channel_name'  => "[channel][login]",
      'channel_title' => "[channel][title]",
      'channel_url'   => "[channel][channel_url]",
      'embed_stream'  => "[channel][embed_code]",
      'description'   => "[channel][status]",
      'online'        => "[online]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = array();
    
    // Create API URL(s) and make requests
    $keys = array_chunk( parent::array_every($channels, 'channel'), 150, true );
    foreach ($keys as $v) {
      $requests[] = self::api_url( implode(',', $v) );
    }
    $responses = parent::query_api($requests);
        
    // Post-process the results
    $out = $online = array();
    $class = get_called_class();
    foreach ($responses as $response) {
      foreach ($response as $c) {
        $out[] = $class::post_process($c);
        $online[$c['channel']['login']] = true;
      }
    }
    
    // Recreate (or at least try to) the offline channels
    // (Justin's stream search doesn't return offline channels)
    foreach ($channels as $c) {
      if (!isset($online[$c['channel']])) {
        $out[] = $class::post_process(array(
          'channel'  => array(
            'login'       => $c['channel'],
            'channel_url' => "http://www.justin.tv/{$c['channel']}",
            'embed_code'  => self::embed_channel($c['channel']),
          ),
          'online'        => false,
        ));
      }
    }

    return $out;
  }
  
  protected static function api_url($channels) {
    return "http://api.justin.tv/api/stream/list.json?channel=$channels";
  }
  
  protected static function post_process($c) {
    if (!isset($c['online'])) $c['online'] = true;
    $c['channel']['embed_code'] = self::embed_channel($c['channel']['login']);
    return $c;
  }

  public static function embed_channel($c) {
    return <<<HTML
<object type="application/x-shockwave-flash" height="295" width="353" id="live_embed_player_flash" data="http://www.twitch.tv/widgets/live_embed_player.swf?channel=$c" bgcolor="#000000"><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://www.twitch.tv/widgets/live_embed_player.swf" /><param name="flashvars" value="start_volume=25&watermark_position=top_right&channel=$c&auto_play=false" /></object>
HTML;
  }

  public static function embed_chat($c) {
    return <<<HTML
<iframe frameborder="0" scrolling="no" id="chat_embed" src="http://twitch.tv/chat/embed?channel=$c&amp;popout_chat=true" height="301" width="221"></iframe>
HTML;

  }


}