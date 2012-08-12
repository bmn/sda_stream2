<?php

class SDAExceptions {
  private static $instance;
  public $exceptions = array(), $last = 0, $error_level = E_USER_WARNING;

  private function __construct() {
    $this->last = microtime(true);
  }

  public static function getInstance() {
    if (!self::$instance) self::$instance = new self();
    return self::$instance;
  }
  
  public static function set_error_level($level) {
    $out = self::getInstance();
    if (is_int($level)) $out->error_level = $level;
    return $out;
  }
  
  public static function exceptions($function = null, $level = null) {
    $instance = self::getInstance();
    if (!is_int($level)) $level = $instance->error_level;
    $out = array();
    foreach ($instance->exceptions as $e) {
      if ($e->code <= $level) {
        $out[] = (is_callable($function)) ? call_user_func($function, $e) : $e->__toString();
      }
    }
    return $out;
  }
  
  public function __clone() {
    return self::getInstance();
  }
}

function SDAExceptions() {
  return SDAExceptions::getInstance();
}

class SDAException extends Exception {
  public $time, $return, $code, $message, $since_previous;

  public function __construct($message, $code = E_USER_WARNING, $return = null) {
    $obj = SDAExceptions::getInstance();
    $this->time = microtime(true);
    $this->code = $code;
    $this->message = $message;
    $this->return = $return;
    $this->since_previous = $this->time - $obj->last;
    parent::__construct($message, $code);
    if ($code <= $obj->error_level) {
      $obj->exceptions[] = $this;
      $obj->last = $this->time;
    }
    return $return;
  }
  
  public function since_previous($dp = 3) {
    return sprintf('%+0.3f', round($this->since_previous, $dp));
  }

  public function __toString() {
    return $this->since_previous() . ": [{$this->code}]: {$this->message}\n";
  }
}

class SDANotice extends SDAException {
  public function __construct($message, $return = null) {
    return parent::__construct($message, E_USER_NOTICE, $return);
  }
}

class SDAWarning extends SDAException {
  public function __construct($message, $return = null) {
    return parent::__construct($message, E_USER_WARNING, $return);
  }
}
