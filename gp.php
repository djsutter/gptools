<?php
/**
 * GitPproject Tool Suite for Applications
 *
 * Copyright (C) 2016 Duncan Sutter
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once "gp_utils.php";
require_once "Application.php";

// Get the directory that this script is running from, and make the drive letter (if any) lowercase
$mydir = dirname($argv[0]);
$mydir = preg_replace_callback('/^([A-Z]):/', function($match) { return strtolower($match[1]).':'; }, $mydir);

// Get the program args, which may come in two parts (separated by --)
// The first part contains options which apply either to this program, or to the program being run in each of the project folders.
// The second part, if any, applies only to the program being run in each of the project folders.

$args = array();
$set = 0;
$i = 0;
for ($a = 1; $a < $argc; $a++) {
  if ($argv[$a] == '--') {
    $set = 1;
    $i = 0;
    continue;
  }
  $args[$set][$i++] = $argv[$a];
}

// Initialize an array to hold command-args
$cmdargs = array();

// Look for the command (first thing that isn't an option). Default is "list"
// If the second arg list is empty then parse the first list for a command
$command = '';
if (empty($args[1])) {
  if (!empty($args[0])) {
    foreach ($args[0] as $a) {
      if (substr($a, 0, 1) != '-') {
        if ($command) {
          $cmdargs[] = $a;
        }
        else {
          $command = $a;
        }
      }
    }
  }
}

// Get the options for this program using getopt()
// Note that it automatically terminates at '--'
$options = getopt('h', array('help'));

// For now, there's only one help command
if ((isset($options['h']) OR isset($options['help'])) && !$command) {
  show_help();
  return;
}

if (empty($args[1]) && !$command) {
  $command = 'list';
}

class GP {
  public $gpdir = '';
  public $app = null;

  function __construct() {
    $this->gpdir = dirname($_SERVER['PHP_SELF']);
  }

  // Run gp
  function run($command, $cmdargs, $options) {
    // Enumerate all the plugins
    $plugins = array();
    $d = dir($this->gpdir . '/plugins');
    while (($entry = $d->read()) !== false) {
      if (!preg_match('/\.php$/', $entry)) continue;
      require_once $this->gpdir . '/plugins/' . $entry;
      $pclass = str_replace('.php', '', $entry);
      $plugins[$pclass] = new $pclass();
    }
    $d->close();

    // Gather the aliases as defined by the plugins
    $aliases = array();
    foreach ($plugins as $pclass => $plugin) {
      if (method_exists($plugin, 'settings')) {
        $settings = $plugin->settings();
        if (!empty($settings->aliases)) {
          foreach ($settings->aliases as $alias) {
            $aliases[$alias] = $pclass;
          }
        }
      }
    }

    // Match the command with an alias, if exists
    if (isset($aliases[$command])) {
      $command = $aliases[$command];
    }

    $plugin = null;
    $settings = null;

    // Select the plugin that we're going to run and load the settings
    $pclass = ucfirst($command);
    if (isset($plugins[$pclass])) {
      $plugin = $plugins[$pclass];
      if (method_exists($plugin, 'settings')) {
        $settings = $plugin->settings();
      }
    }

    // Initialize the gp application
    $this->app = new Application();
    if (empty($settings->no_gp_init)) {
      $this->app->init();
    }

    // Run the plugin, if defined
    if ($plugin) {
      $plugin->run($cmdargs);
      return;
    }

    // Process commands
    switch ($command) {
      case 'help':
        show_help();
        break;

      default:
        global $args;
        $cmd = empty($args[1]) ? join(' ', $args[0]) : join(' ', $args[1]);
        require_once $this->gpdir . '/plugins/defaultcmd.php';
        $cmd_class = new Defaultcmd();
        $cmd_class->run($cmd);
    }
  }
}

$gpobj = new GP();
$gpobj->run($command, $cmdargs, $options);

function gp() {
  global $gpobj;
  return $gpobj;
}

function show_help() {
  echo <<<ENDHELP
GitProject - A program for managing applications built from multiple git sources.

usage: gp [options] <command>

Built-in commands:

TODO: Enumerate the plugins

Global options: These are applicable to all (or most) commands
--exclude=<comma separate list of project names>
--include=<comma separate list of project names>

Usage notes:
  1) Running a command with exclude. You need to do it like this:
     gp --exclude=project_c -- git pull
  2) Or using include:
     gp --include=project_a,project_b -- git pull

ENDHELP;
}
