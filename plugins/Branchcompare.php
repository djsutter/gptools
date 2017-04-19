<?php

/**
 * List the merge status of all projects
 */
class Branchcompare {
  /**
   * Run this plug-in
   * @param array $args
   */
  function run($args) {
    $options = getopt('h', array('help', 'log'));

    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }

    $maxw = gp()->app->longest_project_name();
    $show_log = isset($options['log']);
    $verbose = (isset($options['v']) OR isset($options['verbose']));

    $cmp1 = isset($args[0]) ? $args[0] : 'origin/develop';
    $cmp2 = isset($args[1]) ? $args[1] : 'origin/master';

    $projs_not_listed = array();

    foreach (gp()->app->get_project_list($options) as $project) {
      chdir($project->get_dir());
      $branches = $project->get_branches('a');

      $branch1 = $project->branch_replace($cmp1);
      $branch2 = $project->branch_replace($cmp2);

      if (! in_array($branch1, $branches) OR ! in_array($branch2, $branches)) {
        $projs_not_listed[] = $project->name;
        continue;
      }

      $commits_ahead = gp()->app->git_branch_compare($branch1, $branch2, $verbose);
      $commits_behind = gp()->app->git_branch_compare($branch2, $branch1, $verbose);
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

  function help() {
    echo <<<ENDHELP
bc (branch-compare) List the projects, showing the merge status between two branches
         --log show the logs
ENDHELP;
  }
}
