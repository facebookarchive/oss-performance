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

function get_runtime(string $name, array $data): BatchRuntime {
  return shape(
    'name' => $name,
    'type' => $data['type'],
    'bin' => $data['bin'],
    'args' => array_key_exists('args', $data)
      ? new Vector($data['args'])
      : Vector { },
  );
}

function get_target(
  string $name,
  Map<string, BatchRuntime> $runtimes,
  Map<string, Map<string, BatchRuntime>> $overrides,
): BatchTarget {
  $target_overrides = Map { };
  if ($overrides->containsKey($name)) {
    $target_overrides = $overrides[$name];
  }

  $target_runtimes = Vector { };
  foreach ($runtimes as $runtime_name => $runtime) {
    if ($target_overrides->containsKey($runtime_name)) {
      $target_runtimes[] = $target_overrides[$runtime_name];
    } else {
      $target_runtimes[] = $runtime;
    }
  }
  return shape(
    'name' => $name,
    'runtimes' => $target_runtimes,
  );
}

function get_targets(string $json_data): Vector<BatchTarget> {
  $data = json_decode($json_data, true, 512);
  if ($data === null) {
    throw new Exception(
      'Invalid JSON: '.json_last_error_msg()
    );
  }

  $runtimes = Map { };
  foreach ($data['runtimes'] as $name => $runtime_data) {
    $runtimes[$name] = get_runtime($name, $runtime_data);
  }

  $overrides = Map { };
  if (array_key_exists('runtime-overrides', $data)) {
    foreach ($data['runtime-overrides'] as $target => $target_overrides) {
      foreach ($target_overrides as $name => $override_data) {
        invariant(
          $runtimes->containsKey($name),
          'Overriding a non-existing runtime "%s"',
          $name
        );
        $override = $runtimes[$name];
        foreach ($override_data as $key => $value) {
          if ($key === 'bin') {
            $override['bin'] = $value;
            break;
          }
          invariant_violation("Can't override '%s'", $key);
        }
        if (!$overrides->containsKey($target)) {
          $overrides[$target] = Map { };
        }
        $overrides[$target][$name] = $override;
      }
    }
  }

  $targets = Vector { };
  foreach ($data['targets'] as $target) {
    $targets[] = get_target($target, $runtimes, $overrides);
  }

  return $targets;
}

async function batch_run_single(
  BatchTarget $target,
  BatchRuntime $runtime,
  Vector<string> $base_argv,
): Awaitable<PerfResult> {
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

  // Wait for every $options->validate() to finish until we start executing
  await RescheduleWaitHandle::create(
    RescheduleWaitHandle::QUEUE_DEFAULT,
    0,
  );

  // Wait a while to let ports free up
  Process::cleanupAll();
  return PerfRunner::RunWithOptions($options);
}

async function batch_run_target(
  BatchTarget $target,
  Vector<string> $argv,
): Awaitable<Map<string, PerfResult>> {
  $handles = Map { };
  foreach ($target['runtimes'] as $runtime) {
    $handles[$runtime['name']] =
      batch_run_single($target, $runtime, $argv)->getWaitHandle();
  }
  await AwaitAllWaitHandle::fromMap($handles);
  return $handles->map($handle ==> $handle->getWaitHandle()->result());
}

async function batch_run_all(
  Vector<BatchTarget> $targets,
  Vector<string> $argv,
): Awaitable<Map<string, Map<string, PerfResult>>> {
  $handles = Map { };
  foreach ($targets as $target) {
    $handles[$target['name']] =
      batch_run_target($target, $argv)->getWaitHandle();
  }
  await AwaitAllWaitHandle::fromMap($handles);
  return $handles->map($handle ==> $handle->getWaitHandle()->result());
}

function batch_main(Vector<string> $argv): void {
  $json_config = file_get_contents('php://stdin');
  $targets = get_targets($json_config);

  $results = batch_run_all($targets, $argv)->getWaitHandle()->join();
  print(json_encode($results, JSON_PRETTY_PRINT)."\n");
}

require_once('base/cli-init.php');
batch_main(new Vector($argv));
