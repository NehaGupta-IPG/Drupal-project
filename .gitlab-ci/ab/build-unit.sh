#!/bin/bash
# Build FreeUnit <ref> into /opt/u-<name> with the image Dockerfile's flags.
# Usage: build-unit.sh <name> <ref> <sha256> [tarball]
set -euo pipefail
name=$1 ref=$2 sum=$3 tb=${4:-/tmp/u-$1.tar.gz}
[ -s "$tb" ] || curl -fsSL -o "$tb" "https://github.com/freeunitorg/freeunit/archive/$ref.tar.gz"
echo "$sum  $tb" | sha256sum -c -
src=/tmp/u-$name-src; rm -rf "$src"; mkdir -p "$src"; tar -xzf "$tb" -C "$src" --strip-components=1
cd "$src"
p=/opt/u-$name
./configure --prefix=$p --sbindir=$p/sbin --statedir=$p/state --control=unix:/var/run/control.unit.sock \
  --runstatedir=/var/run --pid=/var/run/unit.pid --logdir=/var/log --log=/var/log/unit.log --tmpdir=/var/tmp \
  --modulesdir=$p/modules --user=www-data --group=www-data --openssl --zlib --cc-opt="$PHP_CFLAGS" >/dev/null
./configure php --module=php --config=/usr/local/bin/php-config --lib-path=/usr/local/lib >/dev/null
make -j "$(nproc)" unitd php >/dev/null
make unitd-install php-install >/dev/null
mkdir -p $p/state
echo "built $name: $($p/sbin/unitd --version 2>&1 | head -1) unitd=$(sha256sum $p/sbin/unitd | cut -c1-12) php=$(sha256sum $p/modules/php.unit.so | cut -c1-12)"
