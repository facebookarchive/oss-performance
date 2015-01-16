<?hh
/*
 *  Copyright (c) 2014, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

enum BatchRuntimeType: string {
  HHVM = 'hhvm';
  PHP_SRC = 'php-src';
};

type BatchRuntime = shape(
  'name' => string,
  'type' => BatchRuntimeType,
  'bin' => string,
  'args' => Vector<string>,
);

type BatchTarget = shape(
  'name' => string,
  'runtimes' => Vector<BatchRuntime>,
);

function batch_get_runtime(string $name, array $data): BatchRuntime {
  return shape(
    'name' => $name,
    'type' => $data['type'],
    'bin' => $data['bin'],
    'args' => array_key_exists('args', $data)
      ? new Vector($data['args'])
      : Vector { },
  );
}

function batch_get_target(
  string $name,
  Map<string, BatchRuntime> $runtimes,
  Map<string, Map<string, ?BatchRuntime>> $overrides,
): BatchTarget {
  $target_overrides = Map { };
  if ($overrides->containsKey($name)) {
    $target_overrides = $overrides[$name];
  }

  $target_runtimes = Vector { };
  foreach ($runtimes as $runtime_name => $runtime) {
    if ($target_overrides->containsKey($runtime_name)) {
      $runtime = $target_overrides[$runtime_name];
    }
    // An override can skip a runtime
    if ($runtime !== null) {
      $target_runtimes[] = $runtime;
    }
  }
  return shape(
    'name' => $name,
    'runtimes' => $target_runtimes,
  );
}

function batch_get_targets(string $json_data): Vector<BatchTarget> {
  $data = json_decode($json_data, true, 512);
  if ($data === null) {
    throw new Exception(
      'Invalid JSON: '.json_last_error_msg()
    );
  }

  $runtimes = Map { };
  foreach ($data['runtimes'] as $name => $runtime_data) {
    $runtimes[$name] = batch_get_runtime($name, $runtime_data);
  }

  $overrides = Map { };
  if (array_key_exists('runtime-overrides', $data)) {
    foreach ($data['runtime-overrides'] as $target => $target_overrides) {
      foreach ($target_overrides as $name => $override_data) {
        if ($name === '__comment') {
          continue;
        }
        $skip = false;
        invariant(
          $runtimes->containsKey($name),
          'Overriding a non-existing runtime "%s"',
          $name
        );
        $override = $runtimes[$name];
        foreach ($override_data as $key => $value) {
          if ($key === 'bin') {
            $override['bin'] = $value;
            continue;
          }
          if ($key === 'skip') {
            $skip = true;
            break;
          }
          invariant_violation("Can't override '%s'", $key);
        }
        if (!$overrides->containsKey($target)) {
          $overrides[$target] = Map { };
        }
        $overrides[$target][$name] = $skip ? null : $override;
      }
    }
  }

  $targets = Vector { };
  foreach ($data['targets'] as $target) {
    $targets[] = batch_get_target($target, $runtimes, $overrides);
  }

  return $targets;
}

function batch_get_single_run(
  BatchTarget $target,
  BatchRuntime $runtime,
  Vector<string> $base_argv,
): PerfOptions {
  $argv = clone $base_argv;
  $argv->addAll($runtime['args']);
  $argv[] = '--'.$target['name'];

  $options = new PerfOptions($argv);
  switch ($runtime['type']) {
    case BatchRuntimeType::HHVM:
      $options->hhvm = $runtime['bin'];
      break;
    case BatchRuntimeType::PHP_SRC:
      $options->php5 = $runtime['bin'];
      break;
  }
  $options->validate();

  return $options;
}

function batch_get_all_runs_for_target(
  BatchTarget $target,
  Vector<string> $argv,
): Map<string, PerfOptions> {
  $options = Map { };
  foreach ($target['runtimes'] as $runtime) {
    $options[$runtime['name']] =
      batch_get_single_run($target, $runtime, $argv);
  }
  return $options;
}

function batch_get_all_runs(
  Vector<BatchTarget> $targets,
  Vector<string> $argv,
): Map<string, Map<string, PerfOptions>> {
  $options = Map { };
  foreach ($targets as $target) {
    $options[$target['name']] =
      batch_get_all_runs_for_target($target, $argv);
  }
  return $options;
}

function batch_main(Vector<string> $argv): void {
  $json_config = file_get_contents('php://stdin');

  $targets = batch_get_targets($json_config);
  $all_runs = batch_get_all_runs($targets, $argv);

  $results = Map { };
  foreach ($all_runs as $target => $target_runs) {
    $results[$target] = Map { };
    foreach ($target_runs as $engine => $run) {
      $results[$target][$engine] = PerfRunner::RunWithOptions($run);
      Process::cleanupAll();
      // Allow some time for things to shut down as we need to immediately
      // re-use the ports.
      sleep(5);
    }
  }
  print(json_encode($results, JSON_PRETTY_PRINT)."\n");
}

require_once('base/cli-init.php');
batch_main(new Vector($argv));
