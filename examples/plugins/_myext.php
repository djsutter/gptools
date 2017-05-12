<?php

function _myext_pre_run() {
//  gp()->register_cmd_exception_handler('_myext_handle_this');
}

function _myext_handle_this(Exception $e) {
  static $i = 3;
  echo "Caught exception: " . $e->getMessage() . " code=" . $e->getCode() . "\n";
   $i--;
   if ($i > 0) {
     gp()->rerun_cmd = true;
   }
}

// function _myext_pre_init() {
//   echo "my pre init!\n";
// }

// function _myext_post_init() {
//   echo "my post init!\n";
// }
