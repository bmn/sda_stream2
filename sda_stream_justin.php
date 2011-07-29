<?php

require_once( dirname(__FILE__) .'/sda_stream.php' );

class SDAStreamJustin extends SDAStream {

  const ttl = 1;

  protected static function fields() {
    return array(
      'user_name'     => "[channel][login]",
      'user_url'      => "[channel][channel_url]",
      'channel_id'    => "[channel_id]",
      'channel_name'  => "[channel][login]",
      'channel_title' => "[channel][title]",
      'channel_url'   => "[channel][channel_url]",
      'embed_stream'  => "[channel][embed_code]",
      'description'   => "[channel][status]",
      'online'        => "[online]",
      'last_online'   => "[up_time]",
    );
  }
  
  protected static function query($channels, $ttl = null) {
    $requests = $sets = $results = $s_sets = $s_requests = array();
    
    // Create API URL and make requests
    $keys = parent::array_every($channels, 'channel');
    $request = self::api_url( implode(',', $keys) );
    $response = reset(parent::query_api(array($request)));
        
    // Post-process the results
    $out = $online = array();
    $class = get_called_class();
    foreach ($response as $c) {
      $out[] = $class::post_process($c);
      $online[$c['channel']['login']] = true;
    }
    
    // Recreate (or at least try to) the offline channels
    // (Justin's stream search doesn't return offline channels)
    foreach ($channels as $c) {
      if (!$online[$c['channel']]) {
        $out[] = $class::post_process(array(
          'channel'  => array(
            'login'       => $c['channel'],
            'channel_url' => "http://www.justin.tv/{$c['channel']}",
            'embed_code'  => '    <object type="application/x-shockwave-flash" height="295" width="353" id="live_embed_player_flash" data="http://www.justin.tv/widgets/live_embed_player.swf?channel='.$c['channel'].'" bgcolor="#000000"><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://www.justin.tv/widgets/live_embed_player.swf" /><param name="flashvars" value="start_volume=25&watermark_position=top_right&channel='.$c['channel'].'&auto_play=false" /></object>'."\n",
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
    if ($c['online'] !== false) $c['online'] = true;
    return $c;
  }


}