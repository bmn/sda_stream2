<?php

require_once( dirname(__FILE__) .'/sda_stream_justin.php' );

class SDAStreamTwitch extends SDAStreamJustin {

  protected static function post_process($c) {
    foreach ( array('user_url', 'channel_url', 'embed_code') as $k ) {
      if (isset($c['channel'][$k]))
        $c['channel'][$k] = str_replace('http://www.justin.tv/', "http://www.twitch.tv/", $c['channel'][$k]);
    }
    return parent::post_process($c);
  }
  
}
