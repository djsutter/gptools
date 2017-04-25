<?php

echo "ready\n";

function gp_ext_pre_run() {
  echo "pre run!\n";
}

function gp_ext_pre_init() {
  echo "pre init!\n";
}

function gp_ext_post_init() {
  echo "post init!\n";
}
