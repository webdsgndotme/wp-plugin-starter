<?php

if (!class_exists('webdsgndotme_base')) {

  class webdsgndotme_base {

    protected function add_action($tag, $callback, $priority = 10, $accepted_args = 1 ) {
      return add_action($tag, array( $this, $callback), $priority, $accepted_args);
    }

    protected function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
      return add_filter($tag, array($this, $callback), $priority, $accepted_args);
    }

  }

}

if (!class_exists('webdsgndotme_singleton')) {

  class webdsgndotme_singleton extends webdsgndotme_base {

    private static $instance = array();

    protected function __construct() { }

    public static function get() {
      // TODO: add this function to legacy php versions
      $class = get_called_class();

      if (isset(self::$instance[$class])) {
        return self::$instance[$class];
      }

      $args = func_get_args();
      self::$instance[$class] = new $class($args);

      return self::$instance[$class];
    }

    public function __clone() {
      trigger_error("Cloning Singleton's is not allowed.", E_USER_ERROR );
    }

    public function __wakeup() {
      trigger_error("Unserializing Singleton's is not allowed.", E_USER_ERROR);
    }

  }

}

// see: http://www.php.net/manual/de/function.get-called-class.php#93799
if (!function_exists('get_called_class')) {
    class webdsgndotme_class_tools {
      static $i = 0;
      static $fl = null;
      static function get_called() {
        $bt = debug_backtrace();
        if(self::$fl == $bt[2]['file'].$bt[2]['line']) {
          self::$i++;
        } else {
          self::$i = 0;
          self::$fl = $bt[2]['file'].$bt[2]['line'];
        }

        $lines = file($bt[2]['file']);
        preg_match_all('/([a-zA-Z0-9\_]+)::'.$bt[2]['function'].'/',
                       $lines[$bt[2]['line']-1],
                       $matches);
        return $matches[1][self::$i];
      }
    }

    function get_called_class() {
      return webdsgndotme_class_tools::get_called();
    }
}

if (!class_exists('webdsgndotme_plugin')) {

  class webdsgndotme_plugin extends webdsgndotme_singleton {

    protected $domain = '';

    protected $url = '';
    public function plugin_url() {
      return $this->url;
    }

    protected $path = '';
    public function plugin_path() {
      return $this->path;
    }

    protected $messages = array();
    public function add_message($message, $class = 'updated', $show_on = true) {
      $msg = new stdClass;
      $msg->message = $message;
      $msg->class = $class;
      $msg->show_on = $show_on;
      $this->messages[] = $msg;

      $_SESSION[$this->domain . '_msg'] = $this->messages;
    }


    public function render_messages() {
      $messages = $this->messages;

      if (count($messages) == 0) {
        return;
      }

      $i = 0;
      foreach ($messages as $msg) {
        if ((is_bool($msg->show_on) && $msg->show_on) || (is_callable($msg->show_on) && call_user_func($msg->show_on))) {
          $m = array_splice($messages, $i, 1);
          $m = apply_filters($this->domain . '_admin_message', array_shift($m));
          print '<div class="' . $m->class . '">' . $m->message . '</div>';
        } else {
          $i++;
        }
      }

      $this->messages = $messages;
      $_SESSION[$this->domain . '_msg'] = $messages;
    }

    protected $options;
    protected $current_version;

    protected function __construct($name, $version) {

      //Language Setup
      $locale = get_locale();
      load_textdomain($name, false, 'languages');

      $this->url = plugins_url($name);
      $this->path = WP_PLUGIN_DIR . '/' . $name;

      $this->current_version = $version;
      $this->domain = $name;

      //Initialize the options
      $this->set_options();

      self::add_action('init', 'init');
      self::add_action('admin_init', 'admin_init');
      self::add_action('plugins_loaded', 'loaded');
      self::add_action('admin_notices', 'render_messages');
    }

    protected function webdsgndotme_plugin($name, $version) {
      $this->__construct($name, $version);
    }

    /**
     * Retrieves the plugin options from the database,
     * or set defaults.
     */
    protected function set_options() {
      if ($options = get_option($this->domain)) {
        $this->options = $options;
      }
      return $this->options;
    }

    public function get_option($key, $default = '') {
      if ($this->options && isset($this->options->{$key})) {
        return $this->options->{$key};
      }
      return $default;
    }

    public function get_domain() {
      return $this->domain;
    }

    public function get_version() {
      return $this->current_version;
    }

    protected function get_version_key() {
      return $this->domain . '_version';
    }


    public function init() { }

    public function admin_init() { }

    function loaded() { }

    public function install() {
      $previous = get_option($this->get_version_key());
      if (!empty($previous) && $previous == $this->current_version) {
        return;
      }

      $option_function = !empty($previous) ? 'update_option' : 'add_option';
      add_action($this->domain . '_upgrade', $previous, $this->current_version, $this);
      call_user_func($option_function, $this->current_version);
    }

    public function uninstall() {
      if (get_option($this->get_version_key())) {
        delete_option($this->get_version_key());
      }
    }

  }

}