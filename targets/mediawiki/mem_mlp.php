<?hh // strict

ini_set('memory_limit', '1024M');


class MemMLPChase {
  const ARRAY_LENGTH = 1 << 22;

  async public function mlp(array $bigArray, int $hits): Awaitable<string> {
    $val1 = 'index1';
    $val2 = 'index2';
    $val3 = 'index3';
    $val4 = 'index4';

    $hits_per_lane = $hits/5;
    for ($i = 0; $i < $hits_per_lane; $i++) {
      $val1 = $bigArray[$val1];
      $val2 = $bigArray[$val2];
      $val3 = $bigArray[$val3];
      $val4 = $bigArray[$val4];

    }
    return $val1.$val2.$val3.$val4;
  }

}


<<__EntryPoint>>
async function main(): Awaitable<void> {
  $hits = 1 * 4 * 5 * 6 * 7 * 8 * 9 * 6;
  $arrayIdx = rand(1, 30);
  $arrayName = "big-array-".\strval($arrayIdx);
  printf("%s\n",$arrayName);
  $bigArray = \apc_fetch($arrayName);
  if (!$bigArray) {
    $tagName = "array-tag-".\strval($arrayIdx);
    $arrayTag = \apc_fetch($tagName);
    if ($arrayTag) {
      exit(0);
    } else {
      $backoffTime = 1000 * rand(1, 1000);
      usleep($backoffTime);
      $arrayTag = \apc_fetch($tagName);
      if ($arrayTag) {
        exit(0);
      }
    }
    \apc_add($tagName, $arrayName);
    $a = [];
    for ($i = 0; $i < MemMLPChase::ARRAY_LENGTH-1; $i++) {
      $i_next = $i+1;
      $a['index'.\strval($i)] = 'index'.\strval($i_next);
    }
    $keys = \array_keys($a);
    \shuffle(&$keys);
    foreach ($keys as $key) {
      $bigArray[$key] = $a[$key];
    }
    $bigArray['index'.\strval(MemMLPChase::ARRAY_LENGTH-1)] = 'index0';
    \apc_add($arrayName, $bigArray);
  }

  $chase = new MemMLPChase();
  $count = await $chase->mlp(
	$bigArray,
	$hits,
  );
  printf("%s\n",$count);
  exit(0);
}
