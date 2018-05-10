<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

newtype Sample = shape(
  'framework' => string,
  'runtime' => string,
  'rps' => float,
);

newtype Row = shape(
  'framework' => string,
  'runtime' => string,
  'rps_samples' => Vector<float>,
  'rps_mean' => ?float,
  'rps_sd' => ?float,
);

function load_file(string $file): Vector<Sample> {
  $json = file_get_contents($file);
  $data = json_decode($json, /* as associative array = */ true);
  
  $results = Vector {};
  foreach ($data as $framework => $framework_data) {
    $results->addAll(load_framework($framework, $framework_data));
  }

  return $results;
}

function load_framework(string $framework, $data): Vector<Sample> {
  $results = Vector {};
  foreach ($data as $runtime => $runtime_data) {
    $results[] = load_run($framework, $runtime, $runtime_data['Combined']);
  }
  return $results;
}

function load_run(string $framework, string $runtime, $data): Sample {
  return shape(
    'framework' => $framework,
    'runtime' => $runtime,
    'rps' => $data['Siege RPS'],
  );
}

function load_files(Vector<string> $files): Vector<Row> {
  $rows_by_key = Map {};
  foreach ($files as $file) {
    $samples = load_file($file);
    foreach ($samples as $sample) {
      $key = $sample['framework']."\0".$sample['runtime'];
      if (!$rows_by_key->containsKey($key)) {
        $rows_by_key[$key] = shape(
          'framework' => $sample['framework'],
          'runtime' => $sample['runtime'],
          'rps_samples' => Vector { },
          'rps_mean' => null,
          'rps_sd' => null,
        );
      }
      $rows_by_key[$key]['rps_samples'][] = $sample['rps'];
    }
  }

  foreach ($rows_by_key as $key => $row) {
    $samples = $row['rps_samples'];
    $count = count($samples);

    // toArray(): https://github.com/facebook/hhvm/issues/5454
    $mean = (float) array_sum($samples->toArray()) / $count;
    $variance = array_sum(
      $samples->map($x ==> pow($mean - $x, 2))->toArray()
    ) / $count;
    $sd = sqrt($variance);

    $row['rps_mean'] = $mean;
    $row['rps_sd'] =  $sd;

    $rows_by_key[$key] = $row;
  }

  return $rows_by_key->values();
}

function dump_csv(Vector<Row> $rows): void {
  $header = Vector {
    'Framework',
    'Runtime',
    'Mean RPS',
    'RPS Standard Deviation',
  };

  $max_sample_count = max($rows->map($row ==> count($row['rps_samples'])));
  for ($i = 1; $i <= $max_sample_count; ++$i) {
    $header[] = 'Sample '.$i.' RPS';
  }

  fputcsv(STDOUT, $header);
  foreach ($rows as $row) {
    $out = Vector {
      $row['framework'],
      $row['runtime'],
      $row['rps_mean'],
      $row['rps_sd'],
    };
    $out->addAll($row['rps_samples']);
    fputcsv(STDOUT, $out);
  }
}

function main(Vector<string> $argv) {
  $files = clone $argv;
  $files->removeKey(0);
  if ($files->isEmpty()) {
    fprintf(STDERR, "Usage: %s results.json [results2.json ...]\n", $argv[0]);
    exit(1);
  }
  dump_csv(load_files($files));
}

main(new Vector($argv));
