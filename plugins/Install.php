<?php

class Install {
  /**
   * Define settings for this plug-in
   * @return StdClass
   */
  function settings() {
    return (object) array(
      'no_gp_init' => true,
    );
  }

  /**
   * List the projects in this application
   * @param array $args
   */
  function run($args) {
    $options = getopt('', array('help', 'dir:', 'uri:', 'branch:'));

    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }

    if (empty($options['dir'])) {
      echo "A required parameter is missing: --dir\n";
      return;
    }
    if (empty($options['uri'])) {
      echo "A required parameter is missing: --uri\n";
      return;
    }

    $buildprojdir = dirname($options['dir']);
    if (!is_dir($buildprojdir)) {
      mkdir($buildprojdir, 0777, true);
    }

    system('git clone ' . $options['uri'] . ' ' . $options['dir']);

    gp()->debug('chdir into ' . dospath($options['dir']));
    chdir($options['dir']);
    if (isset($options['branch'])) {
      system('git checkout ' . $options['branch']);
    }
    gp()->init();

    foreach (gp()->get_project_list() as $project) {
      $dir = $project->get_dir();
      if (is_dir("$dir/.git")) {
          echo hl("Project '" . $project->name . "' already exists. Skipping...\n", 'red');
          continue;
      }

      if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
      }

      chdir($dir);
      $cmd = 'git init';
      echo hl("$cmd\n", 'lightgreen');
      system($cmd);
      $cmd = 'git remote add origin '.$project->origin;
      echo hl("$cmd\n", 'lightgreen');
      system($cmd);
      $cmd = 'git fetch';
      echo hl("$cmd\n", 'lightgreen');
      system($cmd);

      $project->update_refs();
      //Lets get a list of the branches that are in the remote origin:
      $cmd = 'git for-each-ref --sort=-committerdate refs/remotes/origin --format="%(refname)" | sed -n "s/refs\/remotes\/origin\///p"';
      echo hl("$cmd\n", 'lightgreen');
      $result = trim(`$cmd`);
      $remote_branches_newest_descending = preg_split("/\r?\n/", $result);
      if (isset($options['branch']) && in_array($options['branch'], $remote_branches_newest_descending)) {
        $cmd = 'git checkout ' . $options['branch'];
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
      }
      else {
        //$options['branch'] was not found in $remote_branches_newest_descending
        $cmd = 'git checkout ' . $remote_branches_newest_descending[0];//grab the branch containing the most recent commit.
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
      }
    }

    //     system('php d:/git/gptools/gp.php --clone');
  }

  function help() {
    echo <<<ENDHELP

gp install function
-------------------

Use this to install a new application instance. Since all gp applications require a "build" project which contains
a config.json, it is necessary to provide both the directory location and the git uri download the build project.
The URI should specify the location of a git project which contains build information. NOTE that it will be cloned as "build"

Usage: gp --dir=</path/to/build> --uri=<http://path-to-git-build-project> --branch=<branch>

Example: gp --dir=/d/myproject/build --uri=http://my.git.repo/myproject_build.git --branch=develop install
ENDHELP;
  }
}
