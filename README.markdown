sda\_stream2
============

sda\_stream2 is a set of PHP classes for collating and organising stream channel information from online streaming sites. Currently sda\_stream2 supports Ustream.tv and Justin.tv.

These sites' APIs offer challenges in collecting and collating data. Apart from the obvious differences in how they work, Ustream's channel API allows only 10 channels per request, and fails if any channels at all are invalid, and Justin's stream API doesn't return any data about channels that aren't online at the time. sda\_stream2's job is to deal with these challenges, and format the results into a single feed with matching field names so it can be used easily in your code.

sda\_stream was original developed for video game speedrunning site [Speed Demos Archive](http://speeddemosarchive.com), hence the name.

Installation
------------
Clone this project into any directory:

    git clone git://github.com/bmn/sda_stream2.git

Then include `sda_stream.php` in your PHP code.

The basics
----------
    <?php
      require '/path/to/sda_stream.php';
      $streams = SDAStream::get(
        array(
          'channels' => array( 'channel1', 'channel2', 'channel3' ),
          'api' => 'justin'
        )
      );
      foreach ($streams->results as $entry) {
        $status = ($entry['online'] === true) ? 'Online' : 'Offline';
        print <<<HTML
          <a href="{$entry[channel_url]}">{$entry[user_name]}</a> is $status.<br/>
    HTML;
      }
    ?>

    
Global Configuration
--------------------
`SDAStream::get()` takes one argument, an array of options.

`include`: Array of strings - the field names you want to keep in the feed.
These are sda\_stream's custom field names, so they are identical between APIs.
Valid fields are `user_id, user_name, user_url, channel_id, channel_name, channel_url, embed_stream, embed_chat, description, online, last_online, api`.
By default, all fields will be included.

`api`: String - the name of the API to use when an API is not explicitly stated.
Accepts `ustream` and `justin`. The default value is `ustream`.

`callback`: String - the name to use for cache files and JSON callbacks.
The default value is `sda_stream`.

`ttl`: Integer/Float - the number of minutes to store a set of cached results for.
By default, no cache is made.

`single`: Boolean - return a single channel on its own, instead of in an array.
Use when you expect to receive only one channel's data. If more than one channel is received, this option is ignored.
The default value is `false`.

`raw`: Boolean - return the API's response inside the `raw` array index.
Use if you require specific data from the site's API that isn't included by sda\_stream.
The full response for each channel is stored as an array under `raw`.
The default value is `false`.

`output`: Array - an array of data to include in the cache.
Use if you are sending this data to a web application through JSON.

`error_level`: Integer - the E\_USER\_\* error level to observe.
sda\_stream includes an error handling class called SDAExceptions. If `E_USER_NOTICE` is specified, extended notes about the running of the script are logged.
The default value is `E_USER_WARNING`.

`post`: Function - a function to run after collating channel data.
This function runs after filtering the data, but before caching it and returning the instance.
The function receives one argument, the SDAStream instance, which should normally be received as a reference.

`channels`: A list of channels to get results for. See Channel Configuration.

`apis`: A list of channels to get results for, organised by API. See Channel Configuration.

Channel Configuration
---------------------
Use one of the `channels` or `apis` options to define which channels to get results for. There are a number of data formats you can use, depending on your requirements.

### Full syntax
The "full" syntax to represent a channel is an array of options. The options are:

`channel`: String - The channel name to search for.

`api`: String - The name of the API to search within. Possible values `ustream` or `justin`. The value of the global option `api` is used by default.

`add`: Array - An array of data to add to the channel data. Use when you have data gathered from elsewhere you want to include in the feed.

`default`: Array - An array of data to add to the channel data. Unlike `add`, these do not overwrite existing indexes.

    'channels' => array(
      array( 'channel' => 'channel1', 'api' => 'justin' ... )
    )

### Basic syntax
To use the basic syntax, use the channel name as a string, instead of having an array of options. All other options will use defaults.

    'channels' => array( 'channel1', 'channel2', 'channel3' ... )

### Key as channel
As a shortcut, if you use the channel name as the key, you don't need to set it inside the full array.

    'channels' => array(
      array( 'channel' => 'channel1', 'api' => 'justin' ),
      'channel2' => array( 'api' => 'justin' ),
    )

### Synopsis
If you use a key => string format to represent the channel, the string will be added to a new index called `synopsis`.

    'channels' => array(
      'channel1' => 'The first channel'
    )

### Single channel
If you have only one channel to search for, you don't need to enclose it in an array.

    'channels' => array( 'channel' => 'channel1', 'api' => 'justin' )

### Organise by API
If you have one set of channels on Ustream, and another on Justin, you can separate them by using the `apis` option instead of `channels`.

The key of each index should be the name of the API, `ustream` or `justin`. The value of that index is the same array or value you would normally use in `channels`.

    'apis' => array(
      'ustream' => array( 'channel1', 'channel2' ... ),
      'justin'  => array( 'channel3', 'channel4' ... )
    )
    
Advanced usage
--------------
SDAStream::get() returns an SDAStream instance containing all the requested data. An array of channel results is stored in the instance variable `$results`.

    $streams = SDAStream::get($options);
    foreach ($streams->results as $entry) ...

There are also instance functions to help organise the data:

    SDAStream->set_embed_dimensions( integer $width, integer $height )
Changes the width and height of every channel's Flash embed, and returns the SDAStream instance.

    SDAStream->sort( function $callback, boolean $return_array = false )
Sorts the results array using $callback (see the PHP documentation for [usort](http://php.net/usort)), and returns the SDAStream instance.
If `$return_array` is true, returns the results array instead of the SDAStream instance.

    SDAStream->filter( function $callback )
Returns an array containing only the channels matched by the $callback function.
`$callback` receives a single option, an array containing one channel's data. If `$callback` returns true, the channel is included in the results.

sda_stream.php as JSON proxy
----------------------------
Calling sda_stream.php from a web browser will cause the script to attempt to load configuration in ../config.php, run SDAStream::get(), and return the results in JSON format. This is useful for features such as automatic updates.

../config.php should be a PHP script that does one of three things:

* Store a series of variables corresponding to the indexes of the options array - `channels` becomes `$channels`, and so on.
* Store an array called `$config`, which corresponds to the options array.
* Return an array corresponding to the options array.
