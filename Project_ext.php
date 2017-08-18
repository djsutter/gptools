<?php

//require_once Project;
/**
 * Class to hold a git project definition
 */
class Project_ext extends Project {
  public $version_or_hash;
  public $local_version;
  public $patches = array();
  public $current_hash;
  function __construct($application=null, $name=null, $data=null) {
    parent::__construct($application, $name, $data);

    if ($data) {
      $this->version_or_hash = $data->version_or_hash;
      if (isset($data->patches)) {
        $this->patches = $data->patches;
      }
      $this->get_current_version_or_hash();
    }
  }

  function get_current_version_or_hash() {
    try {
      $rc = 0;
      gp()->debug('TRY get current version or hash...');
      pushd($this->get_dir());
      $this->local_version = $this->get_current_tagged_version();
      gp()->debug('get current hash...');
      ob_start();
      $temp_hash = system('git rev-parse --verify HEAD', $rc);
      ob_end_clean();
      if ($rc != 0) {
        echo hl("***** ERROR RC=$rc on command 'git rev-parse' in '" . $this->get_dir() . "' for Project_ext->get_current_version_or_hash() *****\n", 'lightred');
        $this->current_hash = $temp_hash;
      }
      else {
        throw(new Exception('System command failed', $rc));
      }
    }
    catch (Exception $e) {
      foreach (gp()->cmd_exception_handlers as $handler) {
        $handler($e);
      }
    }
  }
/*
 *  @return String (git tag or version) or false if not on an exact tagged release.
 */
  function get_current_tagged_version() {
    $success = false;
    $rc = 0;
    //assuming $this->get_dir()
    gp()->debug('get current tagged version...');
    ob_start();
    $cwd = system('pwd', $rc);
    ob_end_clean();
    if ($rc != 0 || $this->get_dir() != $cwd = dospath($cwd)) {
      gp()->debug('get current tagged version needed a pushd in get_current_tagged_version() ...');
      pushd($this->get_dir());
      echo hl("***** ERROR pushd(" . $this->get_dir() . ") for Project_ext->get_current_tagged_version() *****\n", 'lightred');
      //TODO: need to clone the missing project, improve this logic here too, cleanup.
      throw(new Exception('Cannot find project in filesystem, project name:' . $this->name . ' expected folder:' . dospath($this->dir)));
    }

    $cmd = 'git describe --exact-match --tags';
    ob_start();
    $temp_local_version = system($cmd, $rc);
    ob_end_clean();
    if ($rc != 0) {
      return false;
    }
    return $temp_local_version;
  }

}
