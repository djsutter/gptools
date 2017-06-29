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

/**
 * The gp version of the built-in getopt(), to provide required functionality. The problem with the built-in getopt() is that
 * it expects all options to precede the command args, and this won't work with gp. We need to support the syntax:
 * gp <args> <gp-opts> -- <command> <command-opts>
 * The implementation here is similar to getopt() however it cannot support optional parameters for options because it would
 * have no way of knowing whether the arg is an option or a gp command.
 * @param array $options
 * @param array $longopts
 * @return array
 */
function gp_getopt($options, $longopts=array()) {
  // Parse $options into $opts where the key is the option letter and the value is either '' or ':'.
  $opts = array();
  $len = strlen($options);
  $opt = null;
  for ($i = 0; $i < $len; $i++) {
    if ($options[$i] == ':') {
      $opts[$opt] .= ':';
      continue;
    }
    $opt = '-'.$options[$i];
    $opts[$opt] = '';
  }

  // Parse $longopts into $opts where the key is the option string and the value is either '' or ':'.
  $opt = null;
  foreach ($longopts as $opt) {
    if ($p = strpos($opt, ':')) {
      $optval = substr($opt, $p);
      $opt = '--'.substr($opt, 0, $p);
    }
    else {
      $optval = '';
      $opt = '--'.$opt;
    }
    $opts[$opt] = $optval;
  }

  echo "options: $options\n";
  echo "longopts: ".join(' ', $longopts)."\n";
  print_r($opts);

  $result = array();
  $n = 0;
//  echo "args: " . join(' ', $_SERVER['argv']) . "\n";
  $cnt = count($_SERVER['argv']);
  for ($i = 1; $i < $cnt; $i++) {
    $arg = $_SERVER['argv'][$i];
    echo "arg=$arg\n";
    if ($arg == '--') {
      $result['--'] = join(' ', array_slice($_SERVER['argv'], $i+1));
      break;
    }
    if (isset($opts[$arg])) {
      $result[preg_replace('/^--?/', '', $arg)] = '';
    }
    else {
      $result[$n++] = $arg;
    }
  }

  echo "result=".print_r($result, true)."\n";
  return $result;
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
