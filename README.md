# gptools

Extended functionality for applications based on multiple git projects.

At the core of the gptools project is the gp command, which originally stood for _git project_ but now it's just _gp_.
The main function of the gp command is simply to iterate through a bunch of git projects and run a command.
However, some commands have complexities that require programming, so gp is designed to be easily extended
through the use of plug-ins and extensions, which are discussed below.

## Configuration

For gp to work requires some initial configuration. When you type the gp command, it will search up through the
directory hierarchy until it finds a file called build/config.json. When it does, it will read that file and use it to
navigate through the application's git projects.

Therefore, you currently need to have a folder called 'build' as close as possible to the root directory of your application,
and it must contain the config.json file.

## Usage examples

<dl>
  <dt>gp</dt>
  <dd>By itself, gp will simply list the git repositories that make up this application.</dd>
  
  <dt>gp -d</dt>
  <dd>List each project and show the absolute path to the directory.</dd>
  
  <dt>gp -b</dt>
  <dd>List each project and show the current branch.</dd>
  
  <dt>gp -bd</dt>
  <dd>You can combine some options. This will list each project and show the current branch and diretory.</dd>
  
  <dt>gp git pull</dt>
  <dd>Visit each git repository and run git pull.</dd>
  
  <dt>gp --exclude=drupal,sitefiles -- git pull</dt>
  <dd>Visit each git repository, except for drupal and sitefiles, and run git pull. Note the --, which is required to
  separate the gp options from the command to be run.</dd>
    
  <dt>gp --exclude=drupal -- git checkout develop</dt>
  <dd>Visit each git repository, except for drupal, and checkout the develop branch.</dd>

  <dt>gp --exclude=drupal --log bc origin/develop origin/master</dt>
  <dd>Visit each git repository, except for drupal, and do a branch-compare (bc) between origin/develop and origin/master,
  showing the log message for each commit that exists in one branch and not the other.</dd>
</dl>

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
