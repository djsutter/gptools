<?php

require_once "Project.php";

/**
 * Class to hold an application, which consists of git projects
 */
class Application {
  public $config;                           // Configuration from appdata.json
  public $projects = array();               // List of Projects (objs) for this application
  public $root = null;                      // Root directory for this application
  public $gitconfig = null;                 // Git config file for whatever project the user is in
  public $projectdir = null;                // Directory containing the project where the git config was found
  public $installation = null;              // Name of the installation, if any
  public $gpdir = null;                     // The directory where gp is installed
  public $plugin = null;                    // The selected plugin to run
  public $plugins = array();                // List of installed plugins (name => plugin)
  public $extensions = null;                // Extensions filenames start with underscore, locate in plugin folder but not a plugin
  public $plugin_aliases = null;            // List of plugin aliases (alias => plugin)
  public $cmd_exception_handlers = array(); // List of registered functions to handle exceptions when running system commands
  public $rerun_cmd = false;                // Flag to re-run a command afer handling an exception
  public $run_command = '';                 // Name of system-command being run
  public $run_cmdargs = array();            // List of args for command
  public $run_args = array();               // List of args for the run() function
  public $debug_mode = false;               // Flag to print debug info

  function __construct() {
    $options = getopt(null, array('debug'));
    if (isset($options['debug'])) $this->debug_mode = true;
    $this->debug('Debug mode enabled.');
    $this->gpdir = dirname($_SERVER['PHP_SELF']);
    $this->debug('gpdir='.$this->gpdir);
    $this->init_extensions();//start extensions early on so they can run pre_init and pre_run hooks.
  }

  function debug($str) {
    if (!$this->debug_mode) return;
    echo hl($str, 'darkgray')."\n";
  }
  /**
   * Given a directory which comes from the JSON config, convert it to a full path.
   * The $dir may contain symbolic references using [] notation.
   * @param string $dir
   * @return string
   */
  function get_full_path($dir) {
    $this->debug("Get full path for $dir");
    // Perform any substitutions inside square brackets
    if (preg_match('/\[(.*?)\]/', $dir, $matches)) {
      if (isset($this->config->directories->{$matches[1]})) {
        $sub = $this->config->directories->{$matches[1]};
        if ($sub) $sub .= '/';
        $dir = preg_replace('/\[.*?\]\/?/', $sub, $dir);
      }
    }

    // Make it an absolute path if not already
    if (! is_absolute_path($dir)) {
      $dstr = "Make absolute path for $dir: ";
      $dir = $this->root . '/' . $dir;
      $this->debug($dstr.$dir);
    }

    // As long as $dir is not '/' then remove any trailing slash
    if ($dir != '/') {
      $dir = preg_replace('/\/$/', '', $dir);
    }

    $this->debug("--> $dir");
    return $dir;
  }

  function init() {
    $this->debug('Application init()');

    // Get the application configuration
    if (! $this->_get_appconfig()) {
      exit_error("Cannot find build/config.json in your directory hierarchy.");
    }

    // Get the nearest git config so that we can establish the current application
    if (! $this->_get_gitconfig()) {
      exit_error("Cannot find a git project in your directory hierarchy.");
    }

    // Create Project instances
    foreach ($this->config->git_projects as $name => $data) {
      $this->projects[] = new Project($this, $name, $data);
    }

    // Determine if we are running on a network drive. Start by getting a list of network drives
    $netdrives = get_network_drives();

    // Get "my" drive from the current working directory
    $cwd = dospath(trim(`pwd`));
    $mydrive = preg_replace('/:.*/', '', $cwd);

    // If "my" drive is actually on the network, then see if we can determine the installation name.
    // Otherwise, treat this as a local installation.
    if (isset($netdrives[$mydrive]) && isset($this->config->installations)) {
      // Make a UNC version of the current working directory
      $udir = preg_replace("/^$mydrive:/", $netdrives[$mydrive], $cwd);

      // Try to match the current location with a network drive, and determine the installation name from that.
      // This method iterates up through the directories until a match is found, or we're at the root.
      while ($udir != '/' && $udir != '\\') {
        foreach ($this->config->installations as $inst => $inst_dirs) {
          foreach ($inst_dirs as $unc) {
            // Compare the drive UNC against the install dir UNC.
            // The path must either match the entire UNC or be followed by a slash and more characters
            if ($udir == $unc) {
              $this->installation = $inst;
              break 3; // Stop searching
            }
          }
        }
        $udir = dirname($udir);
      }

      // If we found an installation, then configure our directory paths using drive letters
      if ($this->installation) {
        // Iterate through the installation directories
        foreach ($this->config->installations->{$this->installation} as $name => $path) {
          foreach ($netdrives as $drive => $unc) {
            $esc_unc = str_replace('/', '\\/', $unc);
            if (preg_match("/^$esc_unc(\/.+)?$/", $path)) {
              $subpath = preg_replace("/^$esc_unc/", '', $path);
              $this->config->directories->$name = $drive . ':' . $subpath;
            }
          }
        }
      }
    }

    // Now that we have found the installation config (or not), we can set the application root directory
    if (! $this->_get_root_dir()) {
      exit_error("Cannot determine the application root directory.");
    }
  }

  function init_plugins() {
    // Enumerate all the plugins
    $this->plugins = array();
    $d = dir($this->gpdir . '/plugins');
    while (($entry = $d->read()) !== false) {
      if ($this->substr_startswith($entry, '_') OR !preg_match('/\.php$/', $entry)) continue;
      require_once $this->gpdir . '/plugins/' . $entry;
      $pclass = str_replace('.php', '', $entry);
      $this->plugins[$pclass] = new $pclass();
    }
    $d->close();

    // Gather the aliases as defined by the plugins
    $this->plugin_aliases = array();
    foreach ($this->plugins as $pclass => $plugin) {
      if (method_exists($plugin, 'settings')) {
        $settings = $plugin->settings();
        if (!empty($settings->aliases)) {
          foreach ($settings->aliases as $alias) {
            $this->plugin_aliases[$alias] = $pclass;
          }
        }
      }
    }
  }

  /**
   * Initializes all extensions starting with _ ending with .php found in the plugins folder.
   */
  function init_extensions() {
    // Enumerate all the extensions
    $this->extensions = array();
    $d = dir($this->gpdir . '/plugins');
    while (($entry = $d->read()) !== false) {
      if (!$this->substr_startswith($entry, '_') OR !preg_match('/\.php$/', $entry)) continue;
      require_once $this->gpdir . '/plugins/' . $entry;
      $extname = str_replace('.php', '', $entry);
      $this->extensions[] = $extname;
    }
    $d->close();
  }

  /**
   * Starting with the current working directory, search up to find the application build directory and
   * load the config.json file.
   */
  private function _get_appconfig() {
    global $conf;

    // If we already have it, then return it
    if ($this->config) {
      return $this->config;
    }

    // Allow multiple paths separated by ':'
    $gp_config_paths = explode(':', $conf['gp_config_path']);

    // Search up through the directories, starting from the current working directory
    $dir = dospath(trim(`pwd`));
    $this->debug("CWD is $dir");
    while (1) {
      foreach ($gp_config_paths as $config_path) {
        $config_file = "$dir/$config_path";
        if (is_readable($config_file)) {
          $this->config = json_decode(file_get_contents($config_file));
          if (empty($this->config)) {
            echo hl("Cannot read $config_file - ".json_last_error_msg()."\n", 'red');
          }
          break 2;
        }
      }
      // Go up another level. We're at the top when $p == $path
      $p = dirname($dir);
      if ($p == $dir) {
        break;
      }
      $dir = $p;
    }

    return $this->config;
  }

  /**
   * Starting with the current working directory, search up to find the nearest git project and load the config.
   */
  private function _get_gitconfig() {
    // If we already have it, then return it
    if ($this->gitconfig) {
      return $this->gitconfig;
    }

    // Find the nearest git project
    $path = dospath(trim(`pwd`));
    while (1) {
      if (is_dir($path . '/.git')) {
        $this->gitconfig = parse_ini_file($path . '/.git/config', true);
        $this->projectdir = $path;
        $this->debug('Using .git/config in '.$this->projectdir);
        break;
      }
      // Go up another level. We're at the top when $p == $path
      $p = dirname($path);
      if ($p == $path) {
        break;
      }
      $path = $p;
    }

    return $this->gitconfig;
  }

  /**
   * Get a list of projects, optionally filtered based on $options
   * @param string $proj_name
   * @return Project|null
   */
   public function get_project($proj_name) {
    foreach ($this->projects as $project) {
      if ($project->name == $proj_name) {
        return $project;
      }
    }
    return null;
  }

  /**
   * Get a list of projects, optionally filtered based on $options
   */
  public function get_project_list() {
    $projects = array();
    $opts = getopt('', array('exclude:', 'include:'));
    $include_projects = isset($opts['include']) ? explode(',', $opts['include']) : array();
    $exclude_projects = isset($opts['exclude']) ? explode(',', $opts['exclude']) : array();
    foreach ($this->projects as $project) {
      if (!empty($include_projects)) {
        if (in_array($project->name, $include_projects)) {
          $projects[] = $project;
        }
        continue;
      }
      if (in_array($project->name, $exclude_projects)) continue;
      $projects[] = $project;
    }
    return $projects;
  }

  /**
   * Get the root directory for this application
   */
  private function _get_root_dir() {
    $this->root = null;

    // If the installation has specified an approot, then use it as the application root directory and return
    if (isset($this->config->directories->approot)) {
      $this->root = $this->config->directories->approot;
      return $this->root;
    }

    // See if our git project matches one in the project config.
    // If so, then determine the application root directory based on the location of this project
    $origin = $this->gitconfig['remote origin']['url'];
    // Remove username if it exists
    $origin = preg_replace('://.*@:', '//', $origin);
    foreach ($this->projects as $project) {
      // Remove username if it exists
      $project_orign = preg_replace('://.*@:', '//', $project->origin);
      if ($project->origin == $origin) {
        $dir = $project->dir;
        if (preg_match('/\[(.*)\]/', $dir, $matches)) {
          if (isset($this->config->directories->{$matches[1]})) {
            $dir = preg_replace('/\[.*\]/', $this->config->directories->{$matches[1]}, $dir);
          }
        }
        $pdir = '\\/'.str_replace('/', '\\/', $dir);
        $this->root = preg_replace('/'.$pdir.'$/', '', $this->projectdir);
        break;
      }
    }

    $this->root = $this->_resolve_links_in_path($this->root);

    return $this->root;
  }

  /**
   * Determine the length of the longest project name
   * @return int
   */
  public function longest_project_name() {
    static $longest = 0;
    if ($longest == 0) {
      foreach ($this->projects as $project) {
        if (($len = strlen($project->name)) > $longest) {
          $longest = $len;
        }
      }
    }
    return $longest;
  }

  /**
   * Resolve all symlinks in a path.
   * @param string $path
   */
  private function _resolve_links_in_path($path) {
    $segs = explode('/', $path);
    for ($i = 0; $i < count($segs); $i++) {
      $d = join('/', array_slice($segs, 0, $i+1));
      if (is_link($d)) {
        $nsegs = explode('\\', readlink($d));
        $segs[$i] = $nsegs[$i];
      }
    }
    return join('/', $segs);
  }

  private function _call_ext($ext, $data=null) {
    foreach ($this->extensions as &$extension) {
      if (function_exists($extension . '_' . $ext)) {
        $func = $extension . '_' . $ext;
        $func($data);
      }
    }
  }

  /**
   * Register a callback function to be notified of an exception when running a system command.
   * @param string $func
   */
  public function register_cmd_exception_handler($func) {
    $this->cmd_exception_handlers[] = $func;
  }

  /**
   * Run a gp command
   * @param string $command
   * @param array $cmdargs
   * @param array $args
   */
  public function run($command, $cmdargs=array(), $args=array()) {
    $this->run_command = $command;
    $this->run_cmdargs = $cmdargs;
    $this->run_args = $args;

    $this->_call_ext('pre_run');
    $this->init_plugins();
    $this->_call_ext('post_init_plugins');

    // Match the command with an alias, if exists
    if (isset($this->plugin_aliases[$command])) {
      $command = $this->plugin_aliases[$command];
    }

    // Select the plugin that we're going to run and load the settings
    $pclass = ucfirst($command);
    $plugin_settings = null;
    if (isset($this->plugins[$pclass])) {
      $this->plugin = $this->plugins[$pclass];
      if (method_exists($this->plugin, 'settings')) {
        $plugin_settings = $this->plugin->settings();
      }
    }

    // Initialize the gp application
    if (empty($plugin_settings->no_gp_init)) {
      $this->_call_ext('pre_init');
      $this->init();
      $this->_call_ext('post_init');
    }

    // Run the plugin, if defined
    if ($this->plugin) {
      $this->plugin->run($cmdargs);
      return;
    }

    // Process commands
    switch ($command) {
      case 'help':
        $this->show_help();
        break;

      default:
        $cmd = empty($args[1]) ? join(' ', $args[0]) : join(' ', $args[1]);
        require_once $this->gpdir . '/plugins/defaultcmd.php';
        $cmd_class = new Defaultcmd();
        $cmd_class->run($cmd);
    }
  }

  public function substr_startswith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
  }

  public function show_help() {
    echo "GP Helpn";
  }
}
