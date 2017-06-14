<?php
//
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file defines the interface iUprofilerRuns and also provides a default
// implementation of the interface (class UprofilerRuns).
//

/**
 * iUprofilerRuns interface for getting/saving a uprofiler run.
 *
 * Clients can either use the default implementation,
 * namely UprofilerRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iUprofilerRuns {

  /**
   * Returns uprofiler data given a run id ($run) of a given
   * type ($type).
   *
   * Also, a brief description of the run is returned via the
   * $run_desc out parameter.
   */
  public function get_run($run_id, $type, &$run_desc);

  /**
   * Save uprofiler data for a profiler run of specified type
   * ($type).
   *
   * The caller may optionally pass in run_id (which they
   * promise to be unique). If a run_id is not passed in,
   * the implementation of this method must generated a
   * unique run id for this saved uprofiler run.
   *
   * Returns the run id for the saved uprofiler run.
   *
   */
  public function save_run($uprofiler_data, $type, $run_id = null);
}


/**
 * UprofilerRuns_Default is the default implementation of the
 * iUprofilerRuns interface for saving/fetching uprofiler runs.
 *
 * It stores/retrieves runs to/from a filesystem directory
 * specified by the "uprofiler.output_dir" ini parameter.
 *
 * @author Kannan
 */
class uprofilerRuns_Default implements iUprofilerRuns {

  private $dir = '';
  private $suffix = 'uprofiler';

  private function gen_run_id($type) {
    return uniqid();
  }

  private function file_name($run_id, $type, $for_save = FALSE) {

    $result = '';

    foreach (explode(',', $type) as $current_type){

      $file = "$run_id.$current_type." . $this->suffix;

      if (!empty($this->dir)) {
        $file = $this->dir . "/" . $file;
      }

      if ($for_save) {
        $result = $file;
      } else {
        $result = is_file($file) ? $file : '';
      }

      if ($result) {
        break;
      }

    }

    return $result;
  }

  public function __construct($dir = null) {

    // if user hasn't passed a directory location,
    // we use the uprofiler.output_dir ini setting
    // if specified, else we default to the directory
    // in which the error_log file resides.

    if (empty($dir)) {
	  $loaded_extension = extension_loaded('uprofiler') ? 'uprofiler' : 'xhprof';
	  $dir = ini_get("{$loaded_extension}.output_dir");
      if (empty($dir)) {

        // some default that at least works on unix...
        $dir = "/tmp";

        uprofiler_error("Warning: Must specify directory location for uprofiler runs. ".
                     "Trying {$dir} as default. You can either pass the " .
                     "directory location as an argument to the constructor ".
                     "for UprofilerRuns_Default() or set uprofiler.output_dir ".
                     "ini param.");
      }
    }
    if (!empty($_GET['sub_dir'])) {
        $dir = $dir . '/' . $_GET['sub_dir'];
    }
    if(!is_dir($dir)) {
        mkdir($dir);
    }
    $this->dir = $dir;
  }

  public function get_run($run_id, $type, &$run_desc) {
    $file_name = $this->file_name($run_id, $type);

    if (!file_exists($file_name)) {
      uprofiler_error("Could not find file $file_name");
      $run_desc = "Invalid Run Id = $run_id";
      return null;
    }

    $contents = file_get_contents($file_name);
    $run_desc = "uprofiler Run (Namespace=$type)";
    return unserialize($contents);
  }

  public function save_run($uprofiler_data, $type, $run_id = null) {

    // Use PHP serialize function to store the uprofiler's
    // raw profiler data.
    $uprofiler_data = serialize($uprofiler_data);

    if ($run_id === null) {
      $run_id = $this->gen_run_id($type);
    }

    $file_name = $this->file_name($run_id, $type, TRUE);
    $file = fopen($file_name, 'w');

    if ($file) {
      fwrite($file, $uprofiler_data);
      fclose($file);
    } else {
      uprofiler_error("Could not open $file_name\n");
    }

    // echo "Saved run in {$file_name}.\nRun id = {$run_id}.\n";
    return $run_id;
  }

  function list_runs() {
    if (is_dir($this->dir)) {
      $this->list_dirs();
      $this->list_files();
    }
}

  private function list_files() {
    echo "<hr/>Existing runs:\n<ul>\n";
    $files = glob("{$this->dir}/*.{$this->suffix}");
    usort($files, create_function('$a,$b', 'return filemtime($b) - filemtime($a);'));
    foreach ($files as $file) {
      list($run,$source) = explode('.', basename($file));
      $base_href = htmlentities($_SERVER['SCRIPT_NAME']) . '?run='
        . htmlentities($run) . '&source=' . htmlentities($source);
      $href = (!empty($_GET['sub_dir'])) ? $base_href . '&sub_dir=' . $_GET['sub_dir'] : $base_href;
      echo '<li><a href="' . $href . '">'
        . htmlentities(basename($file)) . "</a><small> "
        . date("Y-m-d H:i:s", filemtime($file)) . "</small></li>\n";
    }
    echo "</ul>\n";
  }

  private function list_dirs() {
    echo "<hr/>Existing dirs:\n<ul>\n";
    $dirs = glob("{$this->dir}/*", GLOB_ONLYDIR);
    usort($dirs, create_function('$a,$b', 'return filemtime($b) - filemtime($a);'));
    foreach ($dirs as $dir) {
      list($run,$source) = explode('.', basename($dir));
        echo '<li><a href="'
            . '?sub_dir=' . htmlentities(basename($dir)) . '">'
            . htmlentities(basename($dir)) . "</a><small> "
            . date("Y-m-d H:i:s", filemtime($dir)) . "</small></li>\n";
    }
    echo "</ul>\n";
  }
}
