<?php

/**
 * Class to hold a git project definition
 */
class Project {
  public $application;
  public $name;
  public $desc;
  public $origin;
  public $dir;
  public $local_branches = array();
  public $remote_branches = array();
  public $cur_branch;

  function __construct($application=null, $name=null, $data=null) {
    $this->application = $application;
    $this->name = $name;
    if ($data) {
      $this->desc = $data->desc;
      $this->origin = $data->origin;
      $this->dir = $data->dir;
    }
  }

  /**
   * Replace all branch references (b:) with the actual branch name from the current configuration for this project.
   * @param string $str
   * @return string
   */
  function branch_replace($str) {
    $config = $this->application->config->configurations;
    $proj = $this->name;
    // For every pattern matching (example b:dev b:rel, etc) call an anonymous function to return the actual substitution.
    return preg_replace_callback('/(b:\w+)/', function($matches) use ($config, $proj) {
      $conf = substr($matches[1], 2);
      return ($config->{$conf}->{$proj}->branch);
    }, $str);
  }

  /**
   * Get a list of branches for the current git project
   * @param string $type
   * @return array
   */
  function get_branches($type='') {
    if (empty($this->local_branches)) {
      pushd($this->get_dir());
      foreach (preg_split("/\r?\n/", rtrim(`git branch`)) as $branch) {
        if ($branch[0] == '*') {
          $this->cur_branch = substr($branch, 2);
        }
        $this->local_branches[] = substr($branch, 2);
      }
      foreach (preg_split("/\r?\n/", rtrim(`git branch -r`)) as $branch) {
        $this->remote_branches[] = substr($branch, 2);
      }
      popd();
    }

    if ($type == 'r') {
      return $this->remote_branches;
    }
    else if ($type == 'rs') { // Remote branches with leading "origin/" stripped off
      $remotes = array();
      foreach ($this->remote_branches as $rb) {
        $remotes[] = preg_replace('/^origin\//', '', $rb);
      }
      return $remotes;
    }
    else if ($type == 'a') {
      return array_merge($this->local_branches, $this->remote_branches);
    }
    else {
      if (!empty($this->local_branches)) {
        return $this->local_branches;
      }
    }
  }

  /**
   * Get the full path to the installation directory for this project
   * @return string
   */
  function get_dir() {
    return $this->application->get_full_path($this->dir);
  }

  function update_refs() {
    $branches = $this->get_branches();
    foreach ($refs as $ref => $branch) {
      if (!in_array("$ref -> $branch", $branches)) {
        if (!in_array($branch, $branches)) {
          `git checkout $branch 2>1`;
          `git checkout $this->cur_branch 2>1`;
        }
        `git symbolic-ref refs/heads/$ref refs/heads/$branch`;
      }
    }
  }
}
