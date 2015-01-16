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

// We output JSON on stdout; this is pretty useless if warnings are mixed in.
// Also, because of reasons^WUnix.
ini_set('display_errors', 'stderr');
// As this is a CLI script, we should use the system timezone. Suppress
// the error.
error_reporting(error_reporting() & ~E_STRICT);

const OSS_PERFORMANCE_ROOT = __DIR__.'/..';
if (!file_exists(OSS_PERFORMANCE_ROOT.'/vendor/autoload.php')) {
  fprintf(
    STDERR, "%s\n",
    'Autoload map not found. Please install composer (see getcomposer.org), '.
    'and run "composer install" from this directory.'
  );
  exit(1);
}

require_once(OSS_PERFORMANCE_ROOT.'/vendor/autoload.php');
