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
    $options = getopt('', array('help', 'dir:', 'uri:'));

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

    gp()->debug('chdir into '.dospath($options['dir']));
    chdir($options['dir']);
    gp()->init();

    foreach (gp()->get_project_list() as $project) {
      $dir = $project->get_dir();
      if (is_dir("$dir/.git")) {
          echo hl("Project '".$project->name."' already exists. Skipping...\n", 'red');
          continue;
      }

      if (is_dir($dir)) {
        chdir($dir);
        $cmd = 'git init';
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
        $cmd = 'git remote add origin '.$project->origin;
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
        $cmd = 'git pull origin master';
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
      }
      else if (0) {
        // We need to determine the directory where the project will be installed, and the name of the directory to clone into
        $dirparts = explode('/', $dir);
        $proj_dir = array_pop($dirparts);
        $inst_dir = join('/', $dirparts);
        if (!is_dir($inst_dir)) {
          mkdir($inst_dir, 0777, true);
        }
        chdir($inst_dir);
        $cmd = 'git clone '.$project->origin.' '.$proj_dir;
        echo hl("$cmd\n", 'lightgreen');
      }
//    `$cmd`;
//    chdir($proj_dir);
//    `git config credential.helper store`;
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

Usage: gp --dir=</path/to/build> --uri=<http://path-to-git-build-project>

Example: gp --dir=/d/myproject/build --uri=http://my.git.repo/myproject_build.git
ENDHELP;
  }
}
