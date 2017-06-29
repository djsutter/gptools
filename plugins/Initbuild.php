<?php
// options:
// --path=<project base folder to initialize>
class Initbuild {
  /**
   * Define settings for this plug-in
   * @return StdClass
   */
  function settings() {
    return (object) array(
      'no_gp_init' => true,
    );
  }

  /**
   * List the projects in this application
   * @param array $args
   */
  private $project_list = array();
  private $options = array();
  function run($args) {
    $options = getopt('hp::', array('help','path::'));
    if (isset($options['h']) OR isset($options['help'])) {
      $this->help();
      return;
    }

//  if (empty(gp()->config)) {
//  }

    // The --initbuild=path option says, init the build config.
    $path = '';
    if (isset($options['path'])) {
      $path = $options['path'];
    }

    if (empty($path)) {
      $path = dospath(trim(`pwd`));
    }
    $base_project = basename($path);
    //echo $path;
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
    foreach($objects as $name => $object){
      if (substr($object, -2) == "\.") {
        $this->find_projects(substr($object, 0, -2), $base_project);
      }
    }
    if (!file_exists($path . '/build/config.json') || !file_exists($path . '/build')) {
      if (!isset($this->project_list[$base_project]['git_projects']['build']) && mkdir($path . '/build')) {
        $this->project_list[$base_project]['git_projects']['build']['dir'] = $path . '/build';
        $this->project_list[$base_project]['git_projects']['build']['desc'] = 'build project, contains gp project config.json and you can put your build scripts here as well.';
        $this->project_list[$base_project]['git_projects']['build']['origin'] = '';
        $this->project_list[$base_project]['configurations']['prod']['build']['branch'] = 'master';
        $this->project_list[$base_project]['configurations']['dev']['build']['branch'] = 'develop';
        $fpath = $path . '/build/config.json';
        print_r($this->project_list);
        echo "\n";
        $input = $this->readinput("config.json will look similar to this, is this ok? y=write to $path/build/config.json n=cancel : (y/n)");
        if ($input == 'y' || $input == 'Y') {
          if ($fp = fopen($fpath, 'w')) {
            fwrite($fp, json_encode($this->project_list, JSON_PRETTY_PRINT));
            fclose($fp);
            echo "project build config data saved in $fpath";
          } else {
            rmdir($path . '/build');
            throw new Exception( "ERROR: Could not open $fpath");
          }
        }
        else {
          rmdir($path . '/build');
          echo "Cancelled write by request AND do cleanup.";
        }
      }
    }
  }

  function readinput( $prompt = '' )
  {
    echo $prompt;
    return rtrim( fgets( STDIN ), "\n" );
  }
  /**
   * Gather project information from a directory, but only if it is a git project.
   * @param string $dir
   */
  function find_projects($dir, $base_project) {
    if (file_exists ( $dir . '/.git/config')) {
      $config = $this->gitconfig = parse_ini_file($dir . '/.git/config', true);
      $project_name = basename($dir);
      $desc = '';
      if (file_exists( $dir . '/' . $project_name . '.info')) {
        $desc = $project_name . ' module';
      }
      $origin = $config['remote origin']['url'];
      if (file_exists ( $dir . '/robots.txt') && file_exists ( $dir . '/cron.php') && file_exists ( $dir . '/includes/ajax.inc') ) {
        //project is Drupal 7 and has a .git directory (meaning it is under source control.
        $desc = $project_name . ' (Drupal 7 core)';
      }
      $this->project_list[$base_project]['git_projects'][$project_name]['dir'] = $dir;
      $this->project_list[$base_project]['git_projects'][$project_name]['desc'] = $desc;
      $this->project_list[$base_project]['git_projects'][$project_name]['origin'] = $origin;
      foreach (preg_split("/\r?\n/", rtrim(`git -C $dir branch`)) as $branch) {
        if ($branch[0] == '*') {
          $this->cur_branch = substr($branch, 2);
          $this->project_list[$base_project]['configurations']['dev'][$project_name]['branch'] = substr($branch, 2);
        }
        $this->local_branches[] = substr($branch, 2);
        if (file_exists ( $dir . '/robots.txt') && file_exists ( $dir . '/cron.php') && file_exists ( $dir . '/includes/ajax.inc') ) {
          //project is Drupal 7 and has a .git directory (meaning it is under source control.
          $desc = $project_name . ' (Drupal 7 core)';
          $this->project_list[$base_project]['configurations']['prod'][$project_name]['branch'] = substr($branch, 2);
          $this->project_list[$base_project]['configurations']['rc'][$project_name]['branch'] = substr($branch, 2);
        }
        else {
          $this->project_list[$base_project]['configurations']['rc'][$project_name]['branch'] = 'rc';
          $this->project_list[$base_project]['configurations']['prod'][$project_name]['branch'] = 'master';
        }
      }
    }
  }

  function help() {
    echo <<<ENDHELP
Help for Initbuild, if your project has not yet been initialized for gp and has no build config, gp will create the build config for you.\n
Usage: gp --path=/path/to/project_base_folder initbuild
ENDHELP;
  }
}
