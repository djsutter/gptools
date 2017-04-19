<?php

class Listproj {
  /**
   * Define settings for this plug-in
   * @return StdClass
   */
  function settings() {
    return (object) array(
      'aliases' => array('list'),
    );
  }

  /**
   * List the projects in this application
   * @param array $args
   */
  function run($args) {
    $options = getopt('bdhp', array('help', 'clone'));

    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }

    // The -p option prints the current project
    if (isset($options['p'])) {
      $loc = `git config --local remote.origin.url`;
      $proj = preg_replace('/.*\/(.*)\..*$/', '$1', $loc);
      echo "$proj\n";
      return;
    }

    $maxw = gp()->app->longest_project_name();
    $show_branch = isset($options['b']);
    $show_dir = isset($options['d']);

    if (!empty($args)) {
      $name = $args[0];
      foreach (gp()->app->projects as $project) {
        if ($project->name == $name) {
          echo $project->get_dir(). "\n";
          return;
        }
      }
      echo "Could not find project $name\n";
      return;
    }

    foreach (gp()->app->get_project_list() as $project) {
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

  function help() {
    echo <<<ENDHELP
Help for listproj.
ENDHELP;
  }
}
