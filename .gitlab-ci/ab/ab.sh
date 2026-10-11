#!/bin/bash
# Concurrent same-node A/B benchmark (#3627452/#3628411, draft MR only).
#
# All arms run AT THE SAME TIME in this job, each on its own copy of the
# codebase ("slot"), its own web server port and CONCURRENCY/N test slots, so
# they share the node, the CPU model and - moment by moment - the neighbours'
# load. Sequential passes on one node are not enough: neighbour load moved a
# repeated identical pass by up to 25% (pilot pipeline 991555).
# Arms rotate over slots between rounds. An arm that finishes first keeps
# running the same shard as unmeasured filler until all arms are done, so the
# load stays symmetric. Per-arm CPU is the rusage of the arm's process tree.
#
# Env:
#   AB_KIND    fc | img | patch | unit
#   AB_ARMS    arm names, e.g. "off on", "base B", "base A B", "ac n137".
#              fc: an arm named on* has the CLI file cache, anything else not.
#              img: the image's php-cli.ini sets the file cache (drupalci
#              images since 2026-10-10). on* = that file unchanged, off* = it
#              plus an empty opcache.file_cache and opcache.record_warnings=0
#              (= the previous image's [opcache] block: enable_cli=1, no file
#              cache, record_warnings default 0), onnorw* = it plus
#              opcache.record_warnings=0. Before every round the image's cache
#              directory is reset to what the job's before_script left outside
#              the checkout, so each run starts as cold as a CI job.
#   AB_ROUNDS  rounds (default: number of arms, so every arm visits every slot)
#   AB_SHARDS  shard index per parallel job, e.g. "2 3"; AB_TOTAL shard total
#   AB_TARGET  optional run-tests.sh selection args instead of a shard
#   AB_WARMUP  comma-separated test files for the unmeasured warm-up
#   AB_MICRO   1 = concurrent wrk microbenchmark of the unit arms first
#   AB_FILLER  0 = no filler (FunctionalJavascript, Nightwatch: killing filler
#              strands Selenium sessions in the shared browser)
#   AB_RUNNER  run-tests (default) | nightwatch: `yarn test:nightwatch` in the
#              slot's core/, --workers=CONCURRENCY/N; AB_TARGET and AB_WARMUP
#              are then nightwatch arguments / one test file.
#              | component: vendor/bin/phpunit on the Component suite with
#              pcov coverage, as `PHPUnit Unit (Component)` runs it on
#              PRIMARY_PHP; AB_WARMUP is then a --filter pattern.
#   AB_USER    user that runs the tests (default www-data; root for Component,
#              which CI runs as root).
#   AB_SERVER  none = no web server (the Unit jobs start none); then
#              SIMPLETEST_DB may be unset and run-tests.sh gets no --dburl.
#   AB_SLOTS   copy = slot 0 is a copy as well, and copies keep .git. Needed
#              for Build tests: copyCodebase() mirrors the whole composer root
#              (ignoreVCS(FALSE): .git too, so the copies cost what the real
#              job's cost; on main ComposerProjectTemplatesTest never runs
#              `git describe`, \Drupal::VERSION '12.0-dev' short-circuits
#              getCoreStability()), so slot 0 = the checkout would also mirror
#              every other slot under .ab/.
#   AB_WARMUP_STRICT 1 = a warm-up that fails in any slot aborts the job
#              (fail fast instead of after the rounds).
#   TESTSUITE, CONCURRENCY, SIMPLETEST_DB, SIMPLETEST_BASE_URL as in CI.
set -uo pipefail
# bash `times` prints a decimal comma in some locales.
export LC_ALL=C
cd "$CI_PROJECT_DIR"
OUT=$CI_PROJECT_DIR/ab-out
mkdir -p "$OUT"
SUM=$OUT/summary.tsv
FAIL=0
read -r -a ARMS <<< "$AB_ARMS"
N=${#ARMS[@]}
ROUNDS=${AB_ROUNDS:-$N}
read -r -a SHARDS <<< "${AB_SHARDS:-1}"
IDX=$(( ${CI_NODE_INDEX:-1} - 1 ))
SHARD=${SHARDS[$(( IDX % ${#SHARDS[@]} ))]}
TOTAL=${AB_TOTAL:-1}
CONC=$(( (CONCURRENCY + N - 1) / N ))
PATCH_FILES=$(for a in "${ARMS[@]}"; do [ -f ".gitlab-ci/ab/$a.patch" ] && sed -n 's#^+++ b/##p' ".gitlab-ci/ab/$a.patch"; done | sort -u)
# Never run the PHP CLI as root with opcache on: the first user to touch a
# file cache owns its 0700 subdirectory. Every root-side probe uses this.
PHPQ="php -d opcache.enable_cli=0"
PHP_INI_DIR_IMG=$($PHPQ -r 'echo PHP_CONFIG_FILE_PATH;')
# The file cache the image's php-cli.ini sets, empty if none.
IMG_FC=$($PHPQ -r 'echo ini_get("opcache.file_cache");')
RUN_USER=${AB_USER:-www-data}
if [ "$AB_KIND" = img ] && [ -z "$IMG_FC" ]; then
  echo "[ab] FATAL: AB_KIND=img, but the image's php-cli.ini sets no opcache.file_cache"; exit 1
fi

calc() { awk "BEGIN {print ($1)}"; }
# Runs a command as the test user, keeping the environment.
as_run() { if [ "$RUN_USER" = root ]; then "$@"; else sudo -u "$RUN_USER" -E -H "$@"; fi; }
log() { echo "[ab $(date -u +%T)] $*"; }
cpu_usage() { awk '/^usage_usec/ {print $2}' /sys/fs/cgroup/cpu.stat; }
host_stat() { awk '/^cpu / {t=0; for (i=2;i<=NF;i++) t+=$i; print t-$5-$6, t}' /proc/stat; }
is_copy() { [ "$1" != 0 ] || [ "${AB_SLOTS:-}" = copy ]; }
slot_dir() { is_copy "$1" && echo "$CI_PROJECT_DIR/.ab/slot$1" || echo "$CI_PROJECT_DIR"; }
docroot() { is_copy "$1" && echo "/var/www/html$1" || echo /var/www/html; }
port() { is_copy "$1" && echo $(( 8080 + $1 )) || echo 80; }
base_url() { is_copy "$1" && echo "http://localhost:$(port "$1")/subdirectory" || echo "$SIMPLETEST_BASE_URL"; }

# CPU seconds of a process and all its descendants, incl. reaped children.
tree_cpu() {
  local tck
  tck=$(getconf CLK_TCK)
  ps -e -o pid=,ppid= | awk -v root="$1" '
    {pp[$1] = $2} END {
      want[root] = 1; changed = 1
      while (changed) { changed = 0; for (p in pp) if (!(p in want) && (pp[p] in want)) {want[p] = 1; changed = 1} }
      for (p in want) print p }' |
  while read -r p; do sed 's/.*) //' "/proc/$p/stat" 2>/dev/null | awk '{print $12 + $13 + $14 + $15}'; done |
  awk -v k="$tck" '{s += $1} END {printf "%.2f", s / k}'
}
# The same for a Unit master, split by role: each child of the master (router,
# controller, the app prototype with its application processes) with its
# whole subtree, plus the master itself.
unit_roles() {
  local p name
  printf 'main:%s,' "$(sed 's/.*) //' "/proc/$1/stat" | awk -v k="$(getconf CLK_TCK)" '{printf "%.2f", ($12 + $13 + $14 + $15) / k}')"
  for p in $(pgrep -P "$1"); do
    name=$(tr '\0' ' ' < "/proc/$p/cmdline" | sed 's/^unit: //; s/ .*//; s/"//g')
    printf '%s:%s,' "$name" "$(tree_cpu "$p")"
  done
}

# ---- slots ---------------------------------------------------------------------
setup_slots() {
  local s d nogit=--exclude=./.git
  [ "${AB_SLOTS:-}" = copy ] && nogit=
  for s in $(seq 0 $((N - 1))); do
    d=$(slot_dir "$s")
    if is_copy "$s"; then
      mkdir -p "$d"
      tar -C "$CI_PROJECT_DIR" $nogit --exclude=./.ab --exclude=./ab-out --exclude=./sites/default/files \
        --exclude=./sites/simpletest --exclude=./nightwatch_output -cf - . | tar -C "$d" -xf -
      mkdir -p "$(docroot "$s")"
      ln -sfn "$d" "$(docroot "$s")/subdirectory"
      sudo -u www-data -H git config --global --add safe.directory "$d"
    fi
    mkdir -p "$d/sites/default/files" "$d/sites/simpletest" "/tmp/ab-tmp-$s"
    chown -R www-data:www-data "$d/sites" "/tmp/ab-tmp-$s"
    : > "$d/.ab-patch"
    if [ "${AB_RUNNER:-}" = nightwatch ]; then
      # As the Nightwatch job writes it, with this slot's server.
      cp "$d/core/.env.example" "$d/core/.env"
      cat >> "$d/core/.env" <<EOF
DRUPAL_TEST_BASE_URL='$(base_url "$s")'
DRUPAL_TEST_CHROMEDRIVER_AUTOSTART=false
DRUPAL_TEST_DB_URL='${SIMPLETEST_DB}'
DRUPAL_TEST_WEBDRIVER_HOSTNAME='${AB_SELENIUM_HOST:-selenium}'
DRUPAL_TEST_WEBDRIVER_CHROME_ARGS='--disable-dev-shm-usage --disable-gpu --headless --dns-prefetch-disable'
DRUPAL_TEST_WEBDRIVER_W3C=true
DRUPAL_TEST_WEBDRIVER_PORT='4444'
DRUPAL_NIGHTWATCH_OUTPUT='../nightwatch_output'
COLUMNS=1000
EOF
      mkdir -p "$d/nightwatch_output" && chown www-data:www-data "$d/nightwatch_output"
    fi
    if [ "$AB_KIND" != unit ] && [ "${AB_SERVER:-}" != none ] && is_copy "$s"; then
      cat > "/etc/apache2/sites-enabled/ab-slot$s.conf" <<EOF
Listen $(port "$s")
<VirtualHost *:$(port "$s")>
  DocumentRoot $(docroot "$s")
  RewriteEngine On
  <Directory $(docroot "$s")>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Order Allow,Deny
    Allow from All
  </Directory>
  ErrorLog /var/log/apache2/ab-slot$s.error.log
  CustomLog /var/log/apache2/ab-slot$s.access.log combined
</VirtualHost>
EOF
    fi
  done
  log "slots ready: $(for s in $(seq 0 $((N - 1))); do printf 'slot%s=%s ' "$s" "$(stat -f -c %T "$(slot_dir "$s")")"; done)"
}

# git in a slot. The copies live inside the job's checkout, and git run from a
# subdirectory of a repository silently skips patch paths outside that
# directory (main run 991786 applied no patch in slots 1-2). Stop repository
# discovery at the slot, so git apply treats it as a plain directory.
slot_git() {
  local d=$1
  shift
  if [ "$d" = "$CI_PROJECT_DIR" ]; then (cd "$d" && git "$@"); else (cd "$d" && GIT_CEILING_DIRECTORIES="$(dirname "$d")" git "$@"); fi
}

# Puts arm $2 into slot $1; sets ARM_ENV[$1] and IDENT[$1].
declare -A ARM_ENV IDENT
set_slot_arm() {
  local s=$1 arm=$2 d cur
  d=$(slot_dir "$s")
  ARM_ENV[$s]=""
  case $AB_KIND in
    fc)
      # Like !17339, the file cache goes into php-cli.ini only: every arm gets
      # PHPRC with copies of the image's php-cli.ini and php.ini, so `php -S`
      # (cli-server SAPI, which reads php.ini, not php-cli.ini) stays without
      # it. A PHP_INI_SCAN_DIR file would reach the built-in servers of Build
      # tests too.
      mkdir -p "/tmp/ab-ini-$arm"
      cp "$PHP_INI_DIR_IMG/php-cli.ini" "$PHP_INI_DIR_IMG/php.ini" "/tmp/ab-ini-$arm/"
      if [[ $arm == on* ]]; then
        mkdir -p "/tmp/opcache-cli-$arm"
        printf '\nopcache.file_cache=/tmp/opcache-cli-%s\n' "$arm" >> "/tmp/ab-ini-$arm/php-cli.ini"
        # Cold, like every real job.
        rm -rf "/tmp/opcache-cli-$arm"/*
        chown www-data:www-data "/tmp/opcache-cli-$arm"
      fi
      ARM_ENV[$s]="PHPRC=/tmp/ab-ini-$arm"
      ;;
    img)
      # Every arm loads copies of the image's php-cli.ini and php.ini through
      # PHPRC, so all arms read their ini the same way; conf.d (pcov) still
      # loads. In an ini file the later directive wins, and an empty
      # opcache.file_cache disables the file cache.
      mkdir -p "/tmp/ab-ini-$arm"
      cp "$PHP_INI_DIR_IMG/php-cli.ini" "$PHP_INI_DIR_IMG/php.ini" "/tmp/ab-ini-$arm/"
      case $arm in
        # The previous image's CLI: opcache without file cache, record_warnings 0.
        off*) printf '\n; ab: off\nopcache.file_cache=\nopcache.record_warnings=0\n' >> "/tmp/ab-ini-$arm/php-cli.ini" ;;
        onnorw*) printf '\n; ab: onnorw\nopcache.record_warnings=0\n' >> "/tmp/ab-ini-$arm/php-cli.ini" ;;
        on*) ;;
        *) log "FATAL: img arm $arm is none of off*, on*, onnorw*"; exit 1 ;;
      esac
      ARM_ENV[$s]="PHPRC=/tmp/ab-ini-$arm"
      ;;
    patch)
      cur=$(cat "$d/.ab-patch")
      if [ "$cur" != "$arm" ]; then
        if [ -n "$cur" ] && [ "$cur" != base ]; then
          slot_git "$d" apply -R "$CI_PROJECT_DIR/.gitlab-ci/ab/$cur.patch" || { log "FATAL: revert $cur in slot $s"; exit 1; }
        fi
        if [ "$arm" != base ]; then
          slot_git "$d" apply "$CI_PROJECT_DIR/.gitlab-ci/ab/$arm.patch" || { log "FATAL: apply $arm in slot $s"; exit 1; }
        fi
        echo "$arm" > "$d/.ab-patch"
      fi
      # Prove it: the arm's patch must be applied, and every other patch not.
      for p in "${ARMS[@]}"; do
        [ "$p" = base ] && continue
        if [ "$p" = "$arm" ]; then
          slot_git "$d" apply --check -R "$CI_PROJECT_DIR/.gitlab-ci/ab/$p.patch" 2>/dev/null || { log "FATAL: $p is not applied in slot $s"; exit 1; }
        else
          slot_git "$d" apply --check "$CI_PROJECT_DIR/.gitlab-ci/ab/$p.patch" 2>/dev/null || { log "FATAL: $p is applied in slot $s (arm $arm)"; exit 1; }
        fi
      done
      # Q3 measures the levers on top of a warm file cache, one per slot.
      mkdir -p "/tmp/ab-ini-slot$s" "/tmp/opcache-cli-slot$s"
      echo "opcache.file_cache=/tmp/opcache-cli-slot$s" > "/tmp/ab-ini-slot$s/fc.ini"
      chown www-data:www-data "/tmp/opcache-cli-slot$s"
      ARM_ENV[$s]="PHP_INI_SCAN_DIR=:/tmp/ab-ini-slot$s"
      ;;
    unit)
      unit_stop "$s"
      unit_start "$s" "$arm" || exit 1
      ;;
  esac
  IDENT[$s]="$(arm_ident "$s")"
  [ "$AB_KIND" = img ] && img_check_ident "$s" "$arm"
}

# What the arm's PHP really runs with, probed as the test user with opcache
# on: `php -r` compiles no file, so the probe writes nothing to the cache.
# st = opcache_get_status()['file_cache'], set only when the cache is active.
IMG_SHA=$(sha1sum < "$PHP_INI_DIR_IMG/php-cli.ini" | cut -c1-10)
img_probe() {
  (cd "$(slot_dir "$1")" && as_run env ${ARM_ENV[$1]} php -r '$st = opcache_get_status(FALSE);
    echo "fc=", ini_get("opcache.file_cache") ?: "none", " st=", $st["file_cache"] ?? "none",
      " rw=", ini_get("opcache.record_warnings"), " pcov=", (int) extension_loaded("pcov"),
      " ini=", php_ini_loaded_file(), " sha=", substr(sha1_file(php_ini_loaded_file()), 0, 10);')
}
img_check_ident() {
  local s=$1 arm=$2 want
  case $arm in
    off*) want="fc=none st=none rw=0" ;;
    onnorw*) want="fc=$IMG_FC st=$IMG_FC rw=0" ;;
    on*) want="fc=$IMG_FC st=$IMG_FC rw=1" ;;
  esac
  if [[ ${IDENT[$s]} != "$want "* ]]; then
    log "FATAL: ASSERT arm $arm in slot $s runs with '${IDENT[$s]}', want '$want ...'"; exit 1
  fi
  # on: the image's php-cli.ini byte for byte.
  if [[ $arm == on* && $arm != onnorw* && ${IDENT[$s]} != *" sha=$IMG_SHA "* ]]; then
    log "FATAL: ASSERT arm $arm in slot $s does not load the image's php-cli.ini (${IDENT[$s]}, image sha=$IMG_SHA)"; exit 1
  fi
}

arm_ident() {
  local s=$1 d fc
  d=$(slot_dir "$s")
  if [ "$AB_KIND" = img ]; then
    printf '%s user=%s' "$(img_probe "$s")" "$RUN_USER"
    return
  fi
  fc=$(cd "$d" && sudo -u www-data -E -H env ${ARM_ENV[$s]} $PHPQ -r 'echo ini_get("opcache.file_cache") ?: "none", " ini=", php_ini_loaded_file();')
  printf 'fc=%s' "$fc"
  if [ "$AB_KIND" = patch ]; then
    # Content hash of every file any arm's patch touches: equal across slots
    # for the same arm, whatever the slot carried before.
    printf ' patch=%s content=%s' "$(cat "$d/.ab-patch")" \
      "$(cd "$d" && for p in $PATCH_FILES; do echo "$p $(sha1sum < "$p" 2>/dev/null || echo missing)"; done | sha1sum | cut -c1-10)"
  fi
  [ "$AB_KIND" = unit ] && printf ' %s' "${UNIT_IDENT[$s]}"
}

declare -A UNIT_IDENT
unit_stop() {
  local pid f=/var/run/unit.$1.pid
  [ -s "$f" ] || return 0
  pid=$(cat "$f")
  kill -QUIT "$pid" 2>/dev/null
  for _ in $(seq 1 100); do kill -0 "$pid" 2>/dev/null || break; sleep 0.1; done
  kill -0 "$pid" 2>/dev/null && { kill -9 "$pid"; sleep 0.5; }
  rm -f "$f" "/var/run/control.unit.$1.sock"
}
unit_start() {
  local s=$1 p=/opt/u-$2 sock=/var/run/control.unit.$1.sock conf=/tmp/unit.$1.json
  rm -rf "/var/lib/ab-unit-$s" && mkdir -p "/var/lib/ab-unit-$s"
  sed -e "s#\"\\*:80\"#\"*:$(port "$s")\"#" -e "s#/var/www/html#$(docroot "$s")#g" \
    -e "s#/var/log/unit-access.log#/var/log/unit-access.$s.log#" .gitlab-ci/unit.json > "$conf"
  : > "/var/log/unit-access.$s.log"
  "$p/sbin/unitd" --statedir "/var/lib/ab-unit-$s" --control "unix:$sock" \
    --pid "/var/run/unit.$s.pid" --log "/var/log/unit.$s.log"
  for _ in $(seq 1 100); do [ -S "$sock" ] && break; sleep 0.1; done
  curl -sS --fail-with-body -X PUT --data-binary "@$conf" --unix-socket "$sock" http://localhost/config >/dev/null \
    || { log "FATAL: unit config rejected in slot $s"; return 1; }
  UNIT_IDENT[$s]="unit=$("$p/sbin/unitd" --version 2>&1 | head -1 | awk '{print $3}') unitd=$(sha256sum "$p/sbin/unitd" | cut -c1-10) mod=$(sha256sum "$p/modules/php.unit.so" | cut -c1-10) port=$(port "$s")"
}

restart_servers() {
  [ "$AB_KIND" = unit ] || [ "${AB_SERVER:-}" = none ] && return 0
  local out
  out=$(service apache2 restart 2>&1) || { log "FATAL: apache restart: $out"; tail -5 /var/log/apache2/error.log; exit 1; }
}

# ---- running ----------------------------------------------------------------------
if [ -n "${AB_TARGET:-}" ]; then
  read -r -a TARGET <<< "$AB_TARGET"
elif [ "${AB_RUNNER:-}" = nightwatch ] || [ "${AB_RUNNER:-}" = component ]; then
  TARGET=()
else
  TARGET=(--types "$TESTSUITE" --all --ci-parallel-node-index "$SHARD" --ci-parallel-node-total "$TOTAL")
fi

# run_tests <slot> <args...>: run-tests.sh (or Nightwatch, or the Component
# PHPUnit run) in the slot as the test user with the arm env.
run_tests() {
  local s=$1
  shift
  if [ "${AB_RUNNER:-}" = nightwatch ]; then
    # As the Nightwatch job runs it; test-site.php inherits the arm env.
    cd "$(slot_dir "$s")/core" && sudo -u www-data -E -H env ${ARM_ENV[$s]} TMPDIR="/tmp/ab-tmp-$s" \
      yarn run test:nightwatch --workers="$CONC" "$@"
    return
  fi
  if [ "${AB_RUNNER:-}" = component ]; then
    # As `PHPUnit Unit (Component)` runs it on PRIMARY_PHP: as root, with pcov
    # coverage (enabled by the job's before_script) and
    # --fail-on-phpunit-deprecation (PHPUNIT_FAIL_ON_PHPUNIT_DEPRECATION).
    # Reports go to /tmp/ab-cov-<slot>; the round copies them when the arm ends.
    rm -rf "/tmp/ab-cov-$s" && mkdir -p "/tmp/ab-cov-$s"
    cd "$(slot_dir "$s")" && as_run env ${ARM_ENV[$s]} TMPDIR="/tmp/ab-tmp-$s" \
      vendor/bin/phpunit -c core/tests/Drupal/Tests/Component --testsuite unit-component --colors=always --testdox \
      --log-junit "/tmp/ab-cov-$s/junit.xml" --fail-on-deprecation --fail-on-phpunit-deprecation \
      --coverage-text="/tmp/ab-cov-$s/component-coverage-report.txt" \
      --coverage-cobertura="/tmp/ab-cov-$s/component-coverage-cobertura.xml" "$@"
    return
  fi
  # The Unit jobs pass no --dburl/--url (no database, no web server).
  local -a db=()
  [ -n "${SIMPLETEST_DB:-}" ] && db=(--dburl "$SIMPLETEST_DB" --url "$(base_url "$s")")
  # run-tests.sh probes symlinks at a fixed $TMPDIR/test_symlink, so runs that
  # start together collide; each slot gets its own TMPDIR.
  cd "$(slot_dir "$s")" && as_run env ${ARM_ENV[$s]} TMPDIR="/tmp/ab-tmp-$s" SIMPLETEST_BASE_URL="$(base_url "$s")" \
    php ./core/scripts/run-tests.sh --phpunit-configuration "$(slot_dir "$s")/core" --color --keep-results \
    --concurrency "$CONC" --repeat 1 "${db[@]}" --verbose --non-html "$@"
}

clean_tests() {
  local s d
  # Opcache off: a www-data run here would fill an image file cache with the
  # checkout's (slot 0's) scripts before the measured runs.
  [ -n "${SIMPLETEST_DB:-}" ] && (cd "$CI_PROJECT_DIR" && sudo -u www-data -E -H $PHPQ ./core/scripts/run-tests.sh --clean --dburl "$SIMPLETEST_DB" >/dev/null 2>&1)
  for s in $(seq 0 $((N - 1))); do
    d=$(slot_dir "$s")
    # PHPUnit's code coverage cache (cacheDirectory=".phpunit.cache" in the
    # Component phpunit.xml.dist): a CI job starts without it.
    rm -rf "$d/core/tests/Drupal/Tests/Component/.phpunit.cache" "$d/core/.phpunit.cache"
    rm -rf "$d/sites/default/files/simpletest" "$d/sites/simpletest" "$d/nightwatch_output"
    mkdir -p "$d/sites/default/files/simpletest" "$d/sites/simpletest" "$d/nightwatch_output"
    chown -R www-data:www-data "$d/sites/default/files" "$d/sites/simpletest" "$d/nightwatch_output"
    # Build workspaces and port locks left by a killed filler.
    rm -rf "/tmp/ab-tmp-$s" && mkdir -p "/tmp/ab-tmp-$s" && chown www-data:www-data "/tmp/ab-tmp-$s"
  done
}

# Measured run of one arm; writes rc, times (bash `times`: children's rusage,
# which includes every waited-for descendant) and wall into $dir.
measured() {
  local s=$1 dir=$2 t0
  t0=$(date +%s.%N)
  ( run_tests "$s" "${TARGET[@]}" > "$dir/run-tests.log" 2>&1; echo $? > "$dir/rc"; times > "$dir/times" )
  calc "$(date +%s.%N) - $t0" > "$dir/wall"
}
# user/sys of the children, e.g. 412.31/180.02: Build is sys-heavy (codebase copies).
times_us() { sed -n 2p "$1" | awk '{for (i=1;i<=2;i++) {split($i, a, /[ms]/); v[i] = a[1]*60 + a[2]} printf "%.2f/%.2f", v[1], v[2]}'; }
times_cpu() { sed -n 2p "$1" | awk '{n=0; for (i=1;i<=2;i++) {split($i, a, /[ms]/); n += a[1]*60 + a[2]} printf "%.2f", n}'; }

# Unmeasured filler: the same shard again and again, in its own session.
filler() {
  local s=$1
  setsid bash -c "$(declare -f run_tests is_copy slot_dir base_url port as_run); declare -A ARM_ENV; ARM_ENV[$s]='${ARM_ENV[$s]}'; CONC=$CONC; RUN_USER=$RUN_USER; \
    while :; do run_tests $s ${TARGET[*]@Q} >/dev/null 2>&1; done" < /dev/null > /dev/null 2>&1 &
  echo $!
}

run_round() {
  local r=$1 i j s arm dir c0 c1 h0 ht0 h1 ht1 n_done alive srvtot srv req fcd fcf
  local -a pids=() fill=()
  clean_tests
  for i in $(seq 0 $((N - 1))); do
    s=$(( (i + r) % N ))
    set_slot_arm "$s" "${ARMS[$i]}"
  done
  restart_servers
  sleep 1
  img_reset
  c0=$(cpu_usage); read -r h0 ht0 <<< "$(host_stat)"
  for i in $(seq 0 $((N - 1))); do
    s=$(( (i + r) % N )); arm=${ARMS[$i]}
    dir=$OUT/round-$r-$arm; mkdir -p "$dir"
    log "round $r arm=$arm slot=$s ${IDENT[$s]}"
    measured "$s" "$dir" &
    pids[$i]=$!
    fill[$i]=""
  done
  # Keep the load symmetric: an arm that is done runs filler until all are.
  n_done=0
  while [ "$n_done" -lt "$N" ]; do
    sleep 2
    n_done=0
    for i in $(seq 0 $((N - 1))); do
      [ -e "$OUT/round-$r-${ARMS[$i]}/wall" ] || continue
      n_done=$((n_done + 1))
      [ -n "${fill[$i]}" ] && continue
      s=$(( (i + r) % N )); dir=$OUT/round-$r-${ARMS[$i]}
      cp "$(slot_dir "$s")"/sites/default/files/simpletest/phpunit-*.xml "$dir/" 2>/dev/null
      [ "${AB_RUNNER:-}" = component ] && cp "/tmp/ab-cov-$s"/* "$dir/" 2>/dev/null
      if [ "$AB_KIND" = unit ] && [ -s "/var/run/unit.$s.pid" ]; then
        unit_roles "$(cat "/var/run/unit.$s.pid")" > "$dir/srv"
        cp "/var/log/unit-access.$s.log" "$dir/"
        awk '{n++; t+=$NF} END {printf "n=%d,time_s=%.1f", n, t}' "/var/log/unit-access.$s.log" > "$dir/req"
      fi
      alive=0
      for j in $(seq 0 $((N - 1))); do [ -e "$OUT/round-$r-${ARMS[$j]}/wall" ] || alive=1; done
      if [ "$alive" = 1 ] && [ "${AB_FILLER:-1}" = 1 ]; then
        fill[$i]=$(filler "$s")
        log "round $r arm=${ARMS[$i]} finished; filler pgid ${fill[$i]}"
      else
        fill[$i]=none
      fi
    done
  done
  wait "${pids[@]}" 2>/dev/null
  c1=$(cpu_usage); read -r h1 ht1 <<< "$(host_stat)"
  srvtot=-
  [ "$AB_KIND" = unit ] || [ "${AB_SERVER:-}" = none ] || srvtot=$(tree_cpu "$(cat /var/run/apache2/apache2.pid 2>/dev/null || echo 1)")
  for i in $(seq 0 $((N - 1))); do
    [[ ${fill[$i]} =~ ^[0-9]+$ ]] && kill -TERM -- "-${fill[$i]}" 2>/dev/null
  done
  sleep 2
  pkill -9 -u "$RUN_USER" -x php 2>/dev/null
  [ "$AB_KIND" = img ] && img_round_check "$r"
  for i in $(seq 0 $((N - 1))); do
    s=$(( (i + r) % N )); arm=${ARMS[$i]}; dir=$OUT/round-$r-$arm
    cov=-
    if [ "${AB_RUNNER:-}" = nightwatch ]; then
      # One JUnit file per test module.
      cp -r "$(slot_dir "$s")/nightwatch_output" "$dir/" 2>/dev/null
      find "$dir/nightwatch_output" -name '*.xml' > "$dir/classes.txt" 2>/dev/null
    elif [ "${AB_RUNNER:-}" = component ]; then
      # Per-class time from the JUnit report, in run-tests.sh's format.
      $PHPQ -r '$x = @simplexml_load_file($argv[1]); if ($x) foreach ($x->xpath("//testsuite[@file]") as $t)
        if (!str_contains((string) $t["name"], "::")) printf("  %.3fs %s \n", (float) $t["time"], $t["name"]);' "$dir/junit.xml" > "$dir/classes.txt"
      # Lines covered/total: pcov must see every script, also one that opcache
      # loads from the file cache, so all arms must report the same.
      cov=$(grep -m1 -A3 'Summary:' "$dir/component-coverage-report.txt" 2>/dev/null | sed -n 's/.*Lines: *[0-9.]*% (\([0-9]*\/[0-9]*\)).*/\1/p')
      if [ -z "$cov" ]; then
        cov=none; log "ASSERT: no coverage report in arm $arm"; FAIL=1
      elif [ -z "${COV0:-}" ]; then
        COV0=$cov
      elif [ "$cov" != "$COV0" ]; then
        log "ASSERT: coverage $cov in arm $arm round $r differs from $COV0"; FAIL=1
      fi
    else
      sed 's/\x1b\[[0-9;]*m//g' "$dir/run-tests.log" | grep -E '^ +[0-9.]+s [A-Z]' > "$dir/classes.txt"
    fi
    srv=-; req=-; fcf=-
    [ -s "$dir/srv" ] && srv=$(cat "$dir/srv")
    [ -s "$dir/req" ] && req=$(cat "$dir/req")
    if [ "$AB_KIND" = img ]; then
      img_assert "$s" "$arm"
    else
      fcd=$(cd "$(slot_dir "$s")" && sudo -u www-data -E -H env ${ARM_ENV[$s]} $PHPQ -r 'echo ini_get("opcache.file_cache");')
      if [ "$AB_KIND" = fc ] && [[ $arm != on* ]] && [ -n "$fcd" ]; then
        log "ASSERT: file cache $fcd in arm $arm"; FAIL=1
      fi
      if [ -n "$fcd" ] && [ -d "$fcd" ]; then
        fcf="$(find "$fcd" -type f | wc -l):$(find "$fcd" -mindepth 1 -maxdepth 1 -printf '%u\n' | sort -u | tr '\n' '+'):$(du -sk "$fcd" | cut -f1)k"
        if [[ $fcf == 0:* || $fcf != *:www-data+:* ]]; then log "ASSERT: file cache not used by www-data in $arm ($fcf)"; FAIL=1; fi
      elif [[ $AB_KIND == patch || $arm == on* ]]; then
        log "ASSERT: file cache missing in $arm"; FAIL=1
      fi
    fi
    printf 'AB\t%s\t%s\tshard=%s/%s\tround=%s\tslot=%s\tarm=%s\tcpu_s=%s\tcpu_us=%s\twall_s=%.2f\trc=%s\tclasses=%s\tround_job_cpu_s=%.2f\tround_host_busy=%.3f\tload=%s\tsrv=%s\tround_srv_total=%s\treq=%s\tfc=%s\tcov=%s\tid=%s\n' \
      "$CI_JOB_ID" "$CI_JOB_NAME" "$SHARD" "$TOTAL" "$r" "$s" "$arm" "$(times_cpu "$dir/times")" "$(times_us "$dir/times")" "$(cat "$dir/wall")" \
      "$(cat "$dir/rc")" "$(wc -l < "$dir/classes.txt")" "$(calc "($c1 - $c0) / 1000000")" \
      "$(calc "($h1 - $h0) / ($ht1 - $ht0)")" "$(cut -d' ' -f1 /proc/loadavg)" "$srv" "$srvtot" "$req" "$fcf" "$cov" "${IDENT[$s]}" | tee -a "$SUM"
  done
}

# ---- image file cache (img) -------------------------------------------------------
# Puts the image's file cache back to what the job's before_script left
# outside the checkout (IMG_SEED), keeping the directory itself (1777; root's in
# the image, www-data's after the Unit Core chown): every measured run starts
# as cold as in a CI job. Runs while no test process is alive: before the
# round's runs, after the last round's filler was killed.
img_reset() {
  [ "$AB_KIND" = img ] || return 0
  find "$IMG_FC" -mindepth 1 -delete
  [ -z "$(find "$IMG_FC" -mindepth 1 -print -quit)" ] || { log "FATAL: could not empty $IMG_FC"; exit 1; }
  tar -C "$IMG_FC" -xpf "$IMG_SEED"
}
# Cache entries (<cache>/<system id><full path>.bin) of the scripts in slot $1
# or its TMPDIR (tests may include PHP they write there), extra find args after
# it. Slot 0 is the checkout, which holds the copies in .ab/. Slots have
# distinct paths and the cache is emptied every round, so an entry under a
# slot was written by that round's arm in that slot.
img_files() {
  local s=$1 d
  shift
  d=$(slot_dir "$s")
  if is_copy "$s"; then
    find "$IMG_FC" -type f \( -path "*$d/*" -o -path "*/tmp/ab-tmp-$s/*" \) "$@"
  else
    find "$IMG_FC" -type f \( \( -path "*$d/*" ! -path "*$CI_PROJECT_DIR/.ab/*" \) -o -path "*/tmp/ab-tmp-$s/*" \) "$@"
  fi
}
# Sets fcf = entries:owners+:size of slot $1; on* arms must have written
# entries, as the test user; off* arms none.
img_assert() {
  local s=$1 arm=$2 n owners kb
  n=$(img_files "$s" | wc -l)
  owners=$(img_files "$s" -printf '%u\n' | sort -u | tr '\n' '+')
  kb=$(img_files "$s" -printf '%s\n' | awk '{t += $1} END {printf "%d", t / 1024}')
  fcf="$n:$owners:${kb}k"
  case $arm in
    off*) [ "$n" = 0 ] || { log "ASSERT: off arm $arm (slot $s) left cache entries ($fcf)"; FAIL=1; } ;;
    *) if [ "$n" = 0 ] || [ "$owners" != "$RUN_USER+" ]; then
         log "ASSERT: arm $arm (slot $s) cache entries $fcf, want >0 owned by $RUN_USER only"; FAIL=1
       fi ;;
  esac
}
# Whole-cache view of round $1: the system-id directories must belong to the
# test user, and every entry must belong to some slot.
img_round_check() {
  local r=$1 total=0 slots=0 s owners shared
  total=$(find "$IMG_FC" -type f | wc -l)
  for s in $(seq 0 $((N - 1))); do slots=$(( slots + $(img_files "$s" | wc -l) )); done
  owners=$(find "$IMG_FC" -mindepth 1 -maxdepth 1 -printf '%u:%m:%f\n' | sort | tr '\n' ' ')
  # A new entry outside the slots has a path every slot shares, so one on arm
  # may have used an entry another wrote. Composer's phar is expected: unit
  # tests run composer and load phar classes the before_script's run did not
  # (34 in a local run); that saves at most one compile per file per round.
  find "$IMG_FC" -type f ! -path "*$CI_PROJECT_DIR/*" ! -path "*/tmp/ab-tmp-*" | sort | comm -13 "$IMG_SEED.list" - > "$OUT/round-$r-shared.txt"
  shared=$(grep -c "/usr/local/bin/composer" "$OUT/round-$r-shared.txt")
  log "round $r image cache: entries=$total in-slots=$slots seed=$SEED_N shared-composer=$shared dirs=${owners:-none}"
  if grep -qv "/usr/local/bin/composer" "$OUT/round-$r-shared.txt"; then
    log "ASSERT: cache entries outside the slots, e.g. $(grep -v /usr/local/bin/composer "$OUT/round-$r-shared.txt" | head -3 | tr '\n' ' ')"; FAIL=1
  fi
  if find "$IMG_FC" -mindepth 1 -maxdepth 1 ! -user "$RUN_USER" | grep -q .; then
    log "ASSERT: cache directories not owned by $RUN_USER: $owners"; FAIL=1
  fi
}

# ---- microbenchmark (unit): all arms at once, each against its own instance --------
micro() {
  local r i s arm url pid u0 u1 rps
  local -A U0
  for s in $(seq 0 $((N - 1))); do echo '<?php echo "ok";' > "$(slot_dir "$s")/core/ab-hello.php"; done
  for r in $(seq 0 $(( ${AB_MICRO_ROUNDS:-4} - 1 ))); do
    for i in $(seq 0 $((N - 1))); do s=$(( (i + r) % N )); set_slot_arm "$s" "${ARMS[$i]}"; done
    for url in core/ab-hello.php core/misc/druplicon.png; do
      for s in $(seq 0 $((N - 1))); do wrk -t1 -c4 -d2s "$(base_url "$s")/$url" >/dev/null 2>&1 & done
      wait
      for s in $(seq 0 $((N - 1))); do
        pid=$(pgrep -P "$(cat "/var/run/unit.$s.pid")" -f '^unit: router')
        U0[$s]="$pid $(sed 's/.*) //' "/proc/$pid/stat" | awk '{print $12 + $13}')"
        wrk -t1 -c4 -d8s "$(base_url "$s")/$url" > "$OUT/wrk-$r-$s.txt" 2>&1 &
      done
      wait
      for i in $(seq 0 $((N - 1))); do
        s=$(( (i + r) % N )); arm=${ARMS[$i]}
        read -r pid u0 <<< "${U0[$s]}"
        u1=$(sed 's/.*) //' "/proc/$pid/stat" | awk '{print $12 + $13}')
        rps=$(awk '/Requests\/sec/ {print $2}' "$OUT/wrk-$r-$s.txt")
        printf 'MICRO\t%s\tround=%s\tslot=%s\tarm=%s\turl=%s\trps=%s\trouter_us_per_req=%.1f\n' "$CI_JOB_ID" "$r" "$s" "$arm" "$url" "${rps:-0}" \
          "$(calc "${rps:-0} > 0 ? ($u1 - $u0) * 1000000 / $(getconf CLK_TCK) / (${rps:-1} * 8) : -1")" | tee -a "$SUM"
      done
    done
  done
  for s in $(seq 0 $((N - 1))); do rm -f "$(slot_dir "$s")/core/ab-hello.php"; done
}

# ---- main -------------------------------------------------------------------------
log "job=$CI_JOB_NAME kind=$AB_KIND arms=${ARMS[*]} rounds=$ROUNDS shard=$SHARD/$TOTAL conc=$CONC x $N"
log "cpu_model=$(awk -F': ' '/^model name/ {print $2; exit}' /proc/cpuinfo) nproc=$(nproc) cpu.max=$(cat /sys/fs/cgroup/cpu.max) node=$HOSTNAME"
log "php=$($PHPQ -v | head -1) built=$($PHPQ -i | awk -F'=> ' '/^Build Date/ {print $2; exit}')"
git config --global --add safe.directory "$CI_PROJECT_DIR"

if [ "$AB_KIND" = img ]; then
  # What the job's before_script left: Composer ran as root, then (Unit Core)
  # the cache was handed to www-data as !17339 does. A system-id directory not
  # owned by the test user would keep it from writing, silently.
  owners=$(find "$IMG_FC" -mindepth 1 -maxdepth 1 -printf '%u:%m:%f\n' | sort | tr '\n' ' ')
  log "image file cache $IMG_FC ($(stat -c '%U:%a' "$IMG_FC")) php-cli.ini sha=$IMG_SHA user=$RUN_USER; after before_script: entries=$(find "$IMG_FC" -type f | wc -l) dirs=${owners:-none}"
  if find "$IMG_FC" -mindepth 1 -maxdepth 1 ! -user "$RUN_USER" | grep -q .; then
    log "ASSERT: before_script left cache directories the test user ($RUN_USER) cannot write: $owners"; FAIL=1
  fi
  # Keep the entries outside the checkout: Composer's phar, which some unit
  # tests run. A CI job's tests find them warm, and with them in place no arm
  # writes an entry whose path other slots share. Directories go in with their
  # owners (--no-recursion): tar would create missing parents as root 0755.
  IMG_SEED=/tmp/ab-img-seed.tar
  (cd "$IMG_FC" && find . -mindepth 1 ! -path "*$CI_PROJECT_DIR/*" -print0 | tar --null --no-recursion -T - -cpf "$IMG_SEED")
  find "$IMG_FC" -type f ! -path "*$CI_PROJECT_DIR/*" | sort > "$IMG_SEED.list"
  SEED_N=$(wc -l < "$IMG_SEED.list")
  log "image file cache seed: $SEED_N entries outside $CI_PROJECT_DIR kept for every round"
fi

if [ "$AB_KIND" = unit ]; then
  # Replace the image's unitd (started by server-setup.sh) by one instance per
  # slot; hand the image's CLI file cache back to www-data in case a root
  # probe claimed it.
  d=$($PHPQ -r 'echo ini_get("opcache.file_cache");')
  [ -n "$d" ] && rm -rf "${d:?}"/* && chown www-data:www-data "$d"
  [ -s /var/run/unit.pid ] && { kill -QUIT "$(cat /var/run/unit.pid)"; sleep 2; }
  pgrep -x unitd >/dev/null && { pkill -9 -x unitd; sleep 1; }
  rm -f /var/run/control.unit.sock /var/run/unit.pid
fi
setup_slots

if [ -n "${AB_WARMUP:-}" ]; then
  log "warm-up: $AB_WARMUP"
  find core vendor -name '*.php' -print0 | xargs -0 cat > /dev/null
  for i in $(seq 0 $((N - 1))); do set_slot_arm "$i" "${ARMS[$i]}"; done
  restart_servers
  clean_tests
  for s in $(seq 0 $((N - 1))); do
    if [ "${AB_RUNNER:-}" = nightwatch ]; then
      (run_tests "$s" "$AB_WARMUP" > "$OUT/warmup-$s.log" 2>&1; rc=$?; echo $rc > "$OUT/warmup-$s.rc"; log "warm-up slot $s rc=$rc") &
    elif [ "${AB_RUNNER:-}" = component ]; then
      (run_tests "$s" --filter "$AB_WARMUP" > "$OUT/warmup-$s.log" 2>&1; rc=$?; echo $rc > "$OUT/warmup-$s.rc"; log "warm-up slot $s rc=$rc") &
    else
      (run_tests "$s" --file "$AB_WARMUP" > "$OUT/warmup-$s.log" 2>&1; rc=$?; echo $rc > "$OUT/warmup-$s.rc"; log "warm-up slot $s rc=$rc") &
    fi
  done
  wait
  if [ "${AB_WARMUP_STRICT:-0}" = 1 ]; then
    for s in $(seq 0 $((N - 1))); do
      [ "$(cat "$OUT/warmup-$s.rc" 2>/dev/null)" = 0 ] || { log "FATAL: warm-up failed in slot $s (see ab-out/warmup-$s.log)"; exit 1; }
    done
  fi
fi

[ "${AB_MICRO:-0}" = 1 ] && micro

for r in $(seq 0 $((ROUNDS - 1))); do
  run_round "$r"
done

if [ "$AB_KIND" = unit ]; then for s in $(seq 0 $((N - 1))); do unit_stop "$s"; done; fi
if [ "$AB_KIND" = patch ]; then for s in $(seq 0 $((N - 1))); do set_slot_arm "$s" base; done; fi
log "done; summary:"
cat "$SUM"
exit $FAIL
