# gptools

Extended functionality for applications based on multiple git projects.

## Plugins

Plugins are used to implement gp commands. They are PHP classes, and go in the plugins directory.

## Extensions

Extensions are used to extend gp base functionality. They are PHP files, and go in the plugins directory. The name of
an extension must start with an underscore; this differentiates them from plugins.

Extensions are "hooks" into various points in the exection of gp. A hook is a function which is a combination of the
extension name and the hook name. As an example, if your extension is called _myext and you want to hook the pre_run event,
then your function would be called _myext_pre_run() 

Here is a more complete example, of how to add an exception handler:

```
<?php

function _myext_pre_run() {
  gp()->register_cmd_exception_handler('_myext_handle_this');
}

function _myext_handle_this(Exception $e) {
  static $i = 3;
  echo "Caught exception: " . $e->getMessage() . " code=" . $e->getCode() . "\n";
   $i--;
   if ($i > 0) {
     gp()->rerun_cmd = true;
   }
}
```
