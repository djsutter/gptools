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
   * List the merge status of all projects
   * @param array $options
   */
  function branchcompare($args, $options=array()) {
    $maxw = $this->_longest_project_name();
    $show_log = isset($options['log']);
    $verbose = (isset($options['v']) OR isset($options['verbose']));

    $cmp1 = isset($args[0]) ? $args[0] : 'origin/develop';
    $cmp2 = isset($args[1]) ? $args[1] : 'origin/master';

    $projs_not_listed = array();

    foreach ($this->_get_project_list($options) as $project) {
      chdir($project->get_dir());
      $branches = $project->get_branches('a');

      $branch1 = $project->branch_replace($cmp1);
      $branch2 = $project->branch_replace($cmp2);

      if (! in_array($branch1, $branches) OR ! in_array($branch2, $branches)) {
        $projs_not_listed[] = $project->name;
        continue;
      }

      $commits_ahead = $this->_git_branch_compare($branch1, $branch2, $verbose);
      $commits_behind = $this->_git_branch_compare($branch2, $branch1, $verbose);
      echo sprintf(hl('%-'.($maxw+2).'s', 'lightcyan') . "%-12s is %2d commits ahead, %2d commits behind $branch2\n", $project->name, $branch1, count($commits_ahead), count($commits_behind));

      if ($show_log) {
        foreach ($commits_ahead as $txt) {
          echo "  > $txt\n";
        }
        if (count($commits_ahead) > 0 && count($commits_behind) > 0) {
          echo "  ---------\n";
        }
        foreach ($commits_behind as $txt) {
          echo "  < $txt\n";
        }
      }
    }

    if (! empty($projs_not_listed)) {
      echo hl("\nWarning, the following projects were omitted because they are missing one or both branches, or might be missing from a configuration:\n", 'yellow');
      echo hl(join(', ', $projs_not_listed), 'lightcyan') . "\n";
    }
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
    global $mydir;
    $this->config->directories->this = $mydir;

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
   * List the projects in this application
   * @param array $options
   */
  function list_projects($args, $options=array()) {
    // The -p option prints the current project
    if (isset($options['p'])) {
      $loc = `git config --local remote.origin.url`;
      $proj = preg_replace('/.*\/(.*)\..*$/', '$1', $loc);
      echo "$proj\n";
      return;
    }

    $maxw = $this->_longest_project_name();
    $show_branch = isset($options['b']);
    $show_dir = isset($options['d']);

    if (!empty($args)) {
      $name = $args[0];
      foreach ($this->projects as $project) {
        if ($project->name == $name) {
          echo $project->get_dir(). "\n";
          return;
        }
      }
      echo "Could not find project $name\n";
      return;
    }

    foreach ($this->_get_project_list($options) as $project) {
      $dir = $project->get_dir();
      if (!is_dir($dir)) {
        if (isset($options['clone'])) {
          $dirparts = explode('/', $dir);
          $proj_dir = array_pop($dirparts);
          $inst_dir = join('/', $dirparts);
          if (!is_dir($inst_dir)) {
            mkdir($inst_dir, 0777, true);
          }
          chdir($inst_dir);
          $cmd = 'git clone '.$project->origin.' '.$proj_dir;
          echo hl("$cmd\n", 'lightgreen');
          `$cmd`;
          chdir($proj_dir);
          `git config credential.helper store`;
        }
        else {
          echo hl("Project '".$project->name."' does not exist. Maybe use --clone option\n", 'red');
          continue;
        }
      }
      printf(hl('%-'.($maxw+2).'s', 'lightcyan'), $project->name);
      if ($show_branch) {
        $project->get_branches();
        printf('%-10s', $project->cur_branch);
      }
      if ($show_dir) {
        echo " $dir";
      }
      echo "\n";
    }
  }

  /**
   * Removes local branches that are fully merged and where there is no corresponding remote-tracking branch.
   * NOTE: Use with care! This will permanently delete a local branch. It is a good idea to do a git fetch first to ensure
   * that all remote branches are known.
   */
  function localbranchclean($args, $options=array()) {
    $dry_run = isset($options['dry-run']);
    $force = isset($options['force']);

    foreach ($this->_get_project_list($options) as $project) {
      printf(hl('%s', 'lightcyan', 'underline') . "\n", $project->name);
      chdir($project->get_dir());
      $local_branches = $project->get_branches();
      $remote_branches = $project->get_branches('rs');
      foreach ($local_branches as $branch) {
        if (! in_array($branch, $remote_branches)) {
          $cmd = 'git branch' . ($force ? ' -D ' : ' -d ') . $branch;
          echo "$cmd\n";
          if (! $dry_run) {
            `$cmd`;
          }
        }
      }
      echo "\n";
    }
  }

  /**
   * Chdir into each project directory and run a command.
   * @param string $cmd
   */
  function run($cmd, $options) {
    $verbose = (isset($options['v']) OR isset($options['verbose']));
    // Break the command into segments and determine if any of the segments contain a substitution string (e.g. "b:")
    $cmdsegs = explode(' ', $cmd);

    // Handle any number of "branch segments" - these have a b: prefix
    $bsegs = array();     // segment number
    $bconfigs = array();  // config data

    for ($i = 0; $i < count($cmdsegs); $i++) {
      if (substr($cmdsegs[$i], 0, 2) == 'b:') {
        $bsegs[] = $i;
        $configuration = substr($cmdsegs[$i], 2);
        if (! isset($this->config->configurations->$configuration)) {
          echo "Sorry, can't find a configuration called \"$configuration\"\n";
          exit;
        }
        $bconfigs[] = $this->config->configurations->$configuration;
      }
    }

    // Now run the commands in each project directory
    $i = 0;
    foreach ($this->_get_project_list($options) as $project) {
      if ($i > 0) echo "\n";
      echo hl($project->name, 'lightcyan', 'underline') . "\n";
      if (! @chdir($project->get_dir())) {
        echo hl("Directory does not exist: " . $project->get_dir() . "\n", 'lightred');
        echo hl("Project not installed?\n", 'lightred');
        continue;
      }
      // Perform branch-segment substitutions
      foreach ($bsegs as $i => $bseg) {
        $cmdsegs[$bseg] = $bconfigs[$i]->{$project->name}->branch;
        $cmd = join(' ', $cmdsegs);
      }
      if ($verbose) {
        echo hl("$cmd\n", 'green');
      }
      echo `$cmd`;
      $i++;
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
  private function _get_project_list($options=array()) {
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
  private function _git_branch_compare($branch1, $branch2, $verbose) {
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
  private function _longest_project_name() {
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
