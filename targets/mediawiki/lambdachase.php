<?hh // strict
class Lambdas {
  const NUM_LAMBDAS = 20000;


  public static function getLambdas(): dict<string, mixed> {
    $m = dict[];
    for ($i = 0; $i < self::NUM_LAMBDAS-1; $i++) {
      $key = "lambda".\strval($i);
      $next = "lambda".\strval($i+1);
      $m[$key] = self::lambda($next);
      $m["num".\strval($i)] = vec[$i];
    }
    $key = "lambda".\strval(self::NUM_LAMBDAS - 1);
    $next = "lambda0";
    $m[$key] = self::lambda($next);
    return $m;
  }

  public static function lambda(string $s): (function (string): string) {
    return function(string $x): string use ($s) { return $s; };
  }

}

class LambdaChase {
  const CHASE_COUNT = 40000;

  public function helloWorld(): string {
    return 'Hello, world!';
  }

  public function go(): int {
    $count = 0;
    $next_f = "lambda0";
    $lambdas = Lambdas::getLambdas();
    for ($i = 1; $i < LambdaChase::CHASE_COUNT; ++$i) {
      $f = $lambdas[$next_f];
      $next_f = $f($next_f);
      $f = $lambdas[$next_f];
      $next_f = $f($next_f);
      $f = $lambdas[$next_f];
      $next_f = $f($next_f);
      $count += 3;
    }
    return $count;
  }
}

<<__EntryPoint>>
async function main(): Awaitable<void> {
  $chase = new LambdaChase();
  printf("%s\n", $chase->helloWorld());
  printf("%d\n", $chase->go());
  exit(0);
}
