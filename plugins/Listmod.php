<?php

/**
 * List Drupal modules
 */
class Listmod {
  private $module_list = array();
  private $options = array();

  /**
   * Run this plug-in
   */
  function run() {
    $options = getopt('h', array('md5', 'help'));

    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }

    $this->options = $options;

    // The results go here
    $module_list = array();

    // Iterate through the module directories and collect information
    foreach (gp()->app->config->module_directories as $dir) {
      $this->find_modules(gp()->app->get_full_path($dir));
    }

    ksort($this->module_list);

    // Find the longest module name
    $mlen = 0;
    foreach (array_keys($this->module_list) as $module) {
      if (($len = strlen($module)) > $mlen) {
        $mlen = $len;
      }
    }

    // Output the results
    foreach ($this->module_list as $module => $locations) {
      $cnt = count($locations);
      foreach ($locations as $loc) {
        $parts = explode(' ', $loc);
        $path = $parts[0];
        $hash = count($parts) >= 2 ? $parts[1] : '';
        $out = sprintf("%-".$mlen."s %-100s %s", $module, $path, $hash);
        if ($cnt > 1) {
          echo hl($out, 'red')."\n";
        }
        else {
          echo "$out\n";
        }
      }
    }
  }

  /**
   * Gather module information from a directory
   * @param string $dir
   */
  function find_modules($dir) {
    $info_files = preg_split('/\r?\n/', trim(`find $dir -name "*.info"`));
    foreach ($info_files as $f) {
      $module = preg_replace('/.*\/(.*)\.info/', '$1', $f);
      $loc = preg_replace("/$module\.info$/", '', $f);
      if (isset($this->options['md5'])) {
        list ($md5, $p) = explode(' ', `md5sum $f`);
        $loc .= " $md5";
      }
      $this->module_list[$module][] = $loc;
    }
  }

  function help() {
    echo <<<ENDHELP
listmod - A plugin for listing Drupal modules. This program is a part of the gp suite of commands,
      as it uses common libraries

usage: gp [options] listmod

Options:

md5 Print the md5sum of each module info file

Usage notes:
  1) Duplicate modules will be listed in red. The basis for comparison is the name of the .info file, so this can
     produce false positives. If you see duplicates, you might try the md5 option to see if they have the same md5sum.
ENDHELP;
  }
}
