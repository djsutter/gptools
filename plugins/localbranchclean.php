<?php

/**
 * Removes local branches that are fully merged and where there is no corresponding remote-tracking branch.
 * NOTE: Use with care! This will permanently delete a local branch. It is a good idea to do a git fetch first to ensure
 * that all remote branches are known.
 */

class Localbranchclean {
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

    $dry_run = isset($options['dry-run']);
    $force = isset($options['force']);

    foreach ($this->get_project_list($options) as $project) {
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

  function help() {
    echo <<<ENDHELP
localbranchclean Deletes local branches that are fully merged and that have no corresponding remote branch.
                 Use with caution! Suggest that you use "git fetch -p" prior to using this command.
                 --dry-run  Show what branches would be deleted without taking any action
                 --force    Force deletion even if not merged
ENDHELP;
  }
}
