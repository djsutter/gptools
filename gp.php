<?php
/**
 * GitPerfect for Applications
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

$app = new Application();
$app->init();

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
if (empty($args[1])) {
  $command = '';
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
  if ($command == '') {
    $command = 'list';
  }
}
else { // The second arg list is to be run in each project
  $command = '';
}

// Get the options for this program using getopt()
// Note that it automatically terminates at '--'
$options = getopt('bdhpv', array('clone', 'dry-run', 'force', 'log', 'help', 'exclude:', 'include:', 'ignore:', 'verbose'));

// For now, there's only one help command
if (isset($options['h']) OR isset($options['help'])) {
  $command = 'help';
}

// This one is different. If the -d option is specified, then it's a list command. We need to move the contents of $command back into $cmdargs
if (isset($options['d']) && $command != 'list') {
  array_unshift($cmdargs, $command);
  $command = 'list';
}

if (isset($options['ignore'])) {
  echo "--ignore? You mean --exclude?\n";
  exit;
}

// Process commands
switch ($command) {
  case 'list':
    $app->list_projects($cmdargs, $options);
    break;

  case 'help':
    show_help();
    break;

  case 'branchcompare':
  case 'bc':
  case 'mergestatus':
    $app->branchcompare($cmdargs, $options);
    break;

  case 'localbranchclean':
    $app->localbranchclean($cmdargs, $options);
    break;

  default:
    $cmd = empty($args[1]) ? join(' ', $args[0]) : join(' ', $args[1]);
    $app->run($cmd, $options);
}

function show_help() {
  echo <<<ENDHELP
GitProject - A program for managing applications built from multiple git sources.

usage: gp [options] <command>

Built-in commands:

list           List the git projects which make up the application.
               -b  Show current branch which is checked out
               -d  Show directories

branchcompare  List the projects, showing the merge status between the develop and master branches
               Alias: bc
               --log show the logs

localbranchclean Deletes local branches that are fully merged and that have no corresponding remote branch.
                 Use with caution! Suggest that you use "git fetch -p" prior to using this command.
                 --dry-run  Show what branches would be deleted without taking any action
                 --force    Force deletion even if not merged

--clone          clone projects that were added to the config.json, so if you add a new project, it will get cloned.

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
