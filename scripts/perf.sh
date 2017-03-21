#!/bin/sh

set -xe

# Switch to scripts dir.
cd "$(dirname "$0")"
ARGS="$@"

echo "Running as $(whoami)"

HHVM_PID="$(pgrep -xn 'hhvm')"
OSS_DIR=$(pwd)

echo "The first arg is the output directory."
echo "The remaining args are passed along to perf record."
echo "Running perf on HHVM pid: $HHVM_PID"


# Go to repo root.
cd "$OSS_DIR/.."
sudo nohup sh -xec "timeout --signal INT 30s \
  perf record -a -g -D 5000 $ARGS -p $HHVM_PID" >nohup.out &
