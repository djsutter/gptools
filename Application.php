<?php

require_once "Project.php";

/**
 * Class to hold an application, which consists of git projects
 */
class Application {
  public $config;               // Configuration from appdata.json
  public $projects = array();   // List of Projects (objs) for this application
  public $root = null;          // Root directory for this application
  public $cwd = null;           // Current working directory (where the program was started from)
  public $gitconfig = null;     // Git config file for whatever project the user is in
  public $projectdir = null;    // Directory containing the project where the git config was found
  public $installation = null;  // Name of the installation, if any

  function __construct() {
    $this->cwd = dospath(trim(`pwd`));
  }

  /**
   * Given a directory which comes from the JSON config, convert it to a full path.
   * The $dir may contain symbolic references using [] notation.
   * @param string $dir
   * @return string
   */
  function get_full_path($dir) {
    // Perform any substitutions inside square brackets
    if (preg_match('/\[(.*)\]/', $dir, $matches)) {
      if (isset($this->config->directories->$matches[1])) {
        $sub = $this->config->directories->$matches[1];
        if ($sub) $sub .= '/';
        $dir = preg_replace('/\[.*\]\/?/', $sub, $dir);
      }
    }

    // Make it an absolute path if not already
    if (! is_absolute_path($dir)) {
      $dir = $this->root . '/' . $dir;
    }

    // As long as $dir is not '/' then remove any trailing slash
    if ($dir != '/') {
      $dir = preg_replace('/\/$/', '', $dir);
    }

    return $dir;
  }

  function init() {
    // The first step is to get the nearest git config so that we can establish the current application
    if (! $this->_get_gitconfig()) {
      exit_error("Cannot find a git project in your directory hierarchy.");
    }

    // Now get the application configuration
    if (! $this->_get_appconfig()) {
      exit_error("Cannot find build/config.json in your directory hierarchy.");
    }

    // The JSON config can use [this] as a directory location that refers to the location of THIS SCRIPT.
    // Set this location now in the config->directories
    // TODO: This idea is obsolete and should be deprecated. It comes from a time when gp was in /c/documents, and documents
    // was considered a git project as part of the pm site.
    $this->config->directories->this = gp()->gpdir;

    // Create Project instances
    foreach ($this->config->git_projects as $name => $data) {
      $this->projects[] = new Project($this, $name, $data);
    }

    // Determine if we are running on a network drive. Start by getting a list of network drives
    $netdrives = get_network_drives();

    // Get "my" drive from the current working directory
    $mydrive = preg_replace('/:.*/', '', $this->cwd);

    // If "my" drive is actually on the network, then see if we can determine the installation name.
    // Otherwise, treat this as a local installation.
    if (isset($netdrives[$mydrive]) && isset($this->config->installations)) {
      // Make a UNC version of the current working directory
      $udir = preg_replace("/^$mydrive:/", $netdrives[$mydrive], $this->cwd);

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

  /**
   * Starting with the current working directory, search up to find the application build directory and
   * load the config.json file.
   */
  private function _get_appconfig() {
    // If we already have it, then return it
    if ($this->config) {
      return $this->config;
    }

    // Search up through the directories
    // But first see if it's the current directory
    $path = $this->cwd;
    if (preg_match('/\/build$/', $path) && is_readable('config.json')) {
      $this->config = json_decode(file_get_contents('config.json'));
      if (empty($this->config)) {
        echo hl("Cannot read config.json - ".json_last_error_msg()."\n", 'red');
      }
    }
    else {
      while (1) {
        if (is_dir($path . '/build')) {
          if (is_readable($path . '/build/config.json')) {
            $this->config = json_decode(file_get_contents($path . '/build/config.json'));
            if (empty($this->config)) {
              echo hl("Cannot read config.json - ".json_last_error_msg()."\n", 'red');
            }
            break;
          }
          else {
            echo $path . "/build/config.json is not readable.\n";
          }
        }
        // Go up another level. We're at the top when $p == $path
        $p = dirname($path);
        if ($p == $path) {
          break;
        }
        $path = $p;
      }
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
    $path = $this->cwd;
    while (1) {
      if (is_dir($path . '/.git')) {
        $this->gitconfig = parse_ini_file($path . '/.git/config', true);
        $this->projectdir = $path;
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
   * @param array $options
   */
  public function get_project_list($options=array()) {
    $projects = array();
    $include_projects = isset($options['include']) ? explode(',', $options['include']) : array();
    $exclude_projects = isset($options['exclude']) ? explode(',', $options['exclude']) : array();
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
    foreach ($this->projects as $project) {
      if ($project->origin == $origin) {
        $dir = $project->dir;
        if (preg_match('/\[(.*)\]/', $dir, $matches)) {
          if (isset($this->config->directories->$matches[1])) {
            $dir = preg_replace('/\[.*\]/', $this->config->directories->$matches[1], $dir);
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
   * Compare two git branches
   * @param string $branch1
   * @param string $branch2
   */
  public function git_branch_compare($branch1, $branch2, $verbose) {
    // It is necessary to put the branches inside quotes, especially when using the '^' symbol
    $cmd = 'git log --oneline "' . $branch1 . '" "^' . $branch2 . '"';
    if ($verbose) {
      echo hl("$cmd\n", 'green');
    }
    $result = trim(`$cmd`);
    if ($result == '') {
      return array();
    }
    return preg_split("/\r?\n/", $result);
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
}
