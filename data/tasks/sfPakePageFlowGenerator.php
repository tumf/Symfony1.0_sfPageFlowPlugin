<?php
/*
  pake_desc('initialize a new pageflow module');
  pake_task('pageflow-init-module','app_exists');
*/

function run_pageflow_init_module($task, $args)
{
  if (count($args) < 2)
  {
    throw new Exception('You must provide your module name.');
  }

  if (count($args) < 3)
  {
    throw new Exception('You must provide your model class name.');
  }

  $app         = $args[0];
  $module      = $args[1];

  try
  {
    $author_name = $task->get_property('author', 'symfony');
  }
  catch (pakeException $e)
  {
    $author_name = 'Your name here';
  }


  
}
