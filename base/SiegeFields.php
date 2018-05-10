<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

type SiegeField = int;
final class SiegeFields {
  const SiegeField DATE_TIME = 0;
  const SiegeField TRANSACTIONS = 1;
  const SiegeField ELAPSED_TIME = 2;
  const SiegeField DATA_TRANSFERRED = 3;
  const SiegeField RESPONSE_TIME = 4;
  const SiegeField TRANSACTION_RATE = 5;
  const SiegeField THROUGHPUT = 6;
  const SiegeField CONCURRENCY = 7;
  const SiegeField OKAY = 8;
  const SiegeField FAILED = 9;

}
