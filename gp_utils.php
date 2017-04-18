<?php

// For reference on colour formatting, see http://misc.flogisoft.com/bash/tip_colors_and_formatting

$gp_term = getenv('TERM');

$formats = array(
  'reset'        => "\033[0m",
  'bold'         => "\033[1m",
  'dim'          => "\033[2m",
  'underline'    => "\033[4m",
  'reverse'      => "\033[7m",
  'black'        => "\033[30m",
  'red'          => "\033[31m",
  'green'        => "\033[32m",
  'yellow'       => "\033[33m",
  'blue'         => "\033[34m",
  'magenta'      => "\033[35m",
  'cyan'         => "\033[36m",
  'lightgray'    => "\033[37m",
  'darkgray'     => "\033[90m",
  'lightred'     => "\033[91m",
  'lightgreen'   => "\033[92m",
  'lightyellow'  => "\033[93m",
  'lightblue'    => "\033[94m",
  'lightmagenta' => "\033[95m",
  'lightcyan'    => "\033[96m",
  'white'        => "\033[97m",
);

function exit_error($msg) {
  echo hl("ERROR: $msg\n", "red");
  exit;
}

function hl($s, $f1, $f2=null, $f3=null) {
  global $gp_term;
  if ($gp_term != 'xterm') return $s;
  global $formats;
  return $formats[$f1] . ($f2?$formats[$f2]:'') . ($f3?$formats[$f3]:'') . $s . $formats['reset'];
}

// Convert a gitbash-style path to dos-style. E.g. /d/documents becomes d:/documents
function dospath($path) {
  if (preg_match('/^\/[a-z]\//', $path)) {
    $path = preg_replace('/^\/([a-z])\//', '$1:/', $path);
  }
  return $path;
}

// Test if a path is absolute - starting with either a drive letter or a forward-slash
function is_absolute_path($path) {
  return (preg_match('/^[a-zA-Z]:/', $path) OR preg_match('/^\//', $path)) ? true : false;
}

function get_network_drives() {
  $netdrives = array();
  if (os_type() == 'Msys') { // Git Bash
    $map = `net use`;
    foreach (explode("\n", $map) as $m) {
      preg_match('/.*([a-zA-Z]):\s+(\S+).*/', $m, $matches);
      if (count($matches) >= 3) {
        $netdrives[strtolower($matches[1])] = str_replace('\\', '/', $matches[2]);
      }
    }
  }
  return $netdrives;
}

function os_type() {
  // Msys (Git Bash), GNU/Linux, Darwin (OSX), Freebsd, etc...
  return trim(`uname -o`);
}

$_bu_dirstack = array();

function popd() {
  global $_bu_dirstack;
  if ($dir = array_pop($_bu_dirstack)) {
    chdir($dir);
  }
}

function pushd($dir) {
  global $_bu_dirstack;
  array_push($_bu_dirstack, dospath(trim(`pwd`)));
  chdir($dir);
}
