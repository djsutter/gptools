<?php

class Updaterefs {
  /**
   * Define settings for this plug-in
   * @return StdClass
   */
  function settings() {
    return (object) array(
      'aliases' => array('upref'),
    );
  }

  /**
   * Update the git refs in this application
   * @param array $args
   */
  function run($args) {
    /*
     * Here's what we know. Refs are stored under .git/refs
     * Here's an example of settin a symbolic ref:
     *   git symbolic-ref refs/heads/mydev refs/heads/develop
     * This will create a file .git/refs/heads/mydev which contains a symbolic reference
     * But you can't checkout that branch unless there is a full ref containing a hashlink. Basically need the file
     * .git/refs/heads/develop which contains a hash.
     * To get that, you need to do a checkout (or copy the file .git/refs/remotes/origin/develop to .git/refs/heads/develop)
     * Here's what we do now:
     * 1. Use git branch to get a list of all known branches, including the curent one.
     *    $ git branch
     *    * 754
     *      756-boost
     *      master -> 756-boost
     *      mydev -> 755
     * 2. Look at all symbolic names and update according to the current config. For now, just add new ones. We may want a --prune option
     * to remove old ones.
     * 3. Use git checkout to resolve any referenced branches that are unknown (e.g. 755 is not known above)
     * 4. Finally, git checkout the branch that was currently checked out.
     * Alternatively, just copy the files.
     */
    print_r(gp()->config->refs);
    if (!empty(gp()->config->refs)) {
      foreach (gp()->config->refs as $proj_name => $refs) {
        if ($project = gp()->get_project($proj_name)) {
          pushd($project->get_dir());
//          system('git branch');
          popd();
        }
      }
    }
  }

  function help() {
    echo <<<ENDHELP

gp updaterefs (upref)
---------------------

Updates references.

ENDHELP;
  }
}
