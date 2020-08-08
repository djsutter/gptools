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
    $uinput = '';
    if (empty($options['dir'])) {
      $usage = "\nexample: gp --dir=/var/www/clients/client3/web47/web/drupal8/folder --uri=https://gitlab.com/GROUP/PROJECT/build.git install\n";
      echo $usage;
      $prompt = "A required parameter is missing: --dir\nPlease enter a full path destination directory for this installation :";
      //readline is not available on WINNT
      if (PHP_OS == 'WINNT') {
        echo $prompt;
        $fp = fopen("php://stdin","r");
        $uinput = rtrim(fgets($fp, 1024));
      } else {
        echo "A required parameter is missing: --dir\n";
        $prompt = "Please enter a full path destination directory for this installation :";
        $uinput = readline($prompt);
      }
      $options['dir'] = dospath($uinput);
    }
    if (empty($options['uri'])) {
      $usage = "\nexample: gp --dir=/var/www/clients/client3/web47/web/drupal8/folder --uri=https://gitlab.com/GROUP/PROJECT/build.git install\n";
      echo $usage;
      $prompt = "A required parameter is missing: --uri\nPlease enter a git uri that contains your config.json ;\nex: http://gitlab.example.com/group/build.git :";
      //readline is not available on WINNT
      if (PHP_OS == 'WINNT') {
        echo $prompt;
        $fp = fopen("php://stdin","r");
        $uinput = rtrim(fgets($fp, 1024));
      } else {
        echo "A required parameter is missing: --uri\n";
        $prompt = "Please enter a git uri that contains your config.json; ex: http://gitlab.example.com/group/build.git :";
        $uinput = readline($prompt);
      }
      $options['uri'] = $uinput;
    }
    if (empty($options['branch'])) {
      $prompt = "A required parameter is missing: --branch\nPlease enter a branch to use for this installation;\nex: develop :";
      //readline is not available on WINNT
      if (PHP_OS == 'WINNT') {
        echo $prompt;
        $fp = fopen("php://stdin","r");
        $uinput = rtrim(fgets($fp, 1024));
      } else {
        $uinput = readline($prompt);
      }
      $options['branch'] = $uinput;
    }
    // If the installation directory does not exist, then create it
    $installdir = dirname($options['dir']);
    if (!is_dir($installdir)) {
      mkdir($installdir, 0777, true);
    }

    // Clone the git project into the installation directory
    system('git clone ' . $options['uri'] . ' ' . $options['dir']);

    // Checkout the requested branch
    chdir($options['dir']);
    if (isset($options['branch'])) {
      system('git checkout ' . $options['branch']);
    }

    // Initialize gp, which reads in the config file
    gp()->init();

    // Find the project that we just installed and see if it's in the right place.
    // If not, then move it to where it's supposed to go.
    foreach (gp()->get_project_list() as $project) {
      if ($project->origin == $options['uri']) {
        $projdir = $project->get_dir();
        if ($projdir != dospath(trim(`pwd`))) {
          // It needs to move - so get a list of files, create the directory and move the files in there.
          $files = explode("\n", trim(`ls -a1`));
          mkdir($projdir, 0777, true);
          foreach ($files as $f) {
            if ($f == '.' OR $f == '..') continue;
            rename($f, "$projdir/$f");
          }
          // Now we need to re-initialize gp with the new config location
          gp()->init();
        }
        break;
      }
    }

    // Install the remaining projects as defined in the config
    foreach (gp()->get_project_list() as $project) {
      // Skip over the build project - already got it!
      if ($project->origin == $options['uri']) continue;

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
      $cmd = 'git remote add origin ' . $project->origin;
      echo hl("$cmd\n", 'lightgreen');
      system($cmd);
      $cmd = 'git fetch';
      echo hl("$cmd\n", 'lightgreen');
      system($cmd);

      $project->update_refs();

      if (isset($options['branch'])) {
        $cmd = 'git checkout ' . $options['branch'];
        echo hl("$cmd\n", 'lightgreen');
        system($cmd);
      }
    }
  }

  function help() {
    echo <<<ENDHELP

gp install function
-------------------

Use this to install a new application instance. Since all gp applications require a "build" project which contains
a config.json, it is necessary to provide both the install directory location and the git uri download the build project.

Usage: gp --dir=</basedir> --uri=<http://path-to-git-build-project> --branch=<branch>

Example: gp --dir=/d/myproject --uri=http://my.git.repo/myproject_build.git --branch=develop install
ENDHELP;
  }
}
