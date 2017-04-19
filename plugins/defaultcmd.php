<?php

/**
 * Chdir into each project directory and run a command.
 */

class Defaultcmd {
  /**
   * Run this plug-in
   * @param array $args
   */
  function run($cmd) {
    $options = getopt('hv', array('help', 'verbose'));

    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }
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
        if (! isset(gp()->app->config->configurations->$configuration)) {
          echo "Sorry, can't find a configuration called \"$configuration\"\n";
          exit;
        }
        $bconfigs[] = gp()->app->config->configurations->$configuration;
      }
    }

    // Now run the commands in each project directory
    $i = 0;
    foreach (gp()->app->get_project_list($options) as $project) {
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

  function help() {
    echo <<<ENDHELP
bc (branch-compare) List the projects, showing the merge status between two branches
         --log show the logs
ENDHELP;
  }
}