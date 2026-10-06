#!/bin/bash
# Same-node A/B benchmark (#3627452/#3628411, draft MR only, never merge).
#
# Runs ONE run-tests.sh shard several times in this job, switching only the
# arm between passes, so both arms share the node, CPU model and neighbours.
#
# Env:
#   AB_KIND    fc | patch | unit
#   AB_ARMS    arm names, e.g. "off on", "base B", "base A B", "ac n137"
#   AB_SEQS    pass orders as arm indexes, one per job, e.g. "0110;1001";
#              picked by CI_NODE_INDEX, so parallel jobs are counterbalanced
#   AB_SHARDS  shard index per parallel job, e.g. "2 3"; AB_TOTAL shard total
#   AB_WARMUP  comma-separated test files for the unmeasured warm-up pass
#   AB_MICRO   1 = wrk microbenchmark of the unit arms first (unit only)
#   AB_TARGET  optional: run-tests.sh selection args instead of a shard,
#              e.g. "--types kernel --module package_manager"
#   TESTSUITE, CONCURRENCY, SIMPLETEST_DB, SIMPLETEST_BASE_URL as in CI.
set -uo pipefail
cd "$CI_PROJECT_DIR"
OUT=$CI_PROJECT_DIR/ab-out
mkdir -p "$OUT"
SUM=$OUT/summary.tsv
FAIL=0
read -r -a ARMS <<< "$AB_ARMS"
IFS=';' read -r -a SEQS <<< "${AB_SEQS:-0110}"
read -r -a SHARDS <<< "${AB_SHARDS:-1}"
IDX=$(( ${CI_NODE_INDEX:-1} - 1 ))
SEQ=${SEQS[$(( IDX % ${#SEQS[@]} ))]}
SHARD=${SHARDS[$(( IDX % ${#SHARDS[@]} ))]}
TOTAL=${AB_TOTAL:-1}
# Never run the PHP CLI as root with opcache on: the first user to touch the
# file cache owns its 0700 subdirectory, which silently disables it for
# www-data. Every root-side probe goes through this.
PHPQ="php -d opcache.enable_cli=0"
CLI_INI="$($PHPQ -r 'echo PHP_CONFIG_FILE_PATH;')/php-cli.ini"
FC_TAG="; ab-fc"

calc() { awk "BEGIN {print ($1)}"; }
log() { echo "[ab $(date -u +%T)] $*"; }

cpu_usage() { awk '/^usage_usec/ {print $2}' /sys/fs/cgroup/cpu.stat; }
psi_some() { awk -F'total=' '/^some/ {print $2}' /sys/fs/cgroup/cpu.pressure 2>/dev/null || echo 0; }
# Node-wide jiffies: "busy total" (neighbours' load indicator).
host_stat() { awk '/^cpu / {t=0; for (i=2;i<=NF;i++) t+=$i; print t-$5-$6, t}' /proc/stat; }

# CPU seconds of the web server tree by role, incl. reaped children.
server_cpu() {
  local tck p name t
  tck=$(getconf CLK_TCK)
  for p in $(pgrep -x 'unitd|apache2'); do
    name=$(tr '\0' ' ' < /proc/$p/cmdline | sed 's/^unit: //; s/ \[.*//; s/ v[0-9.]*$//; s/  *$//; s/ .*//' | cut -c1-20)
    t=$(sed 's/.*) //' /proc/$p/stat | awk '{print $12 + $13 + $14 + $15}')
    echo "$name $t"
  done | awk -v k="$tck" '{c[$1] += $2} END {for (i in c) printf "%s:%.2f,", i, c[i] / k}'
}

fc_dir() { $PHPQ -r 'echo ini_get("opcache.file_cache");'; }

# ---- arms ------------------------------------------------------------------
APPLIED=""
set_patch_arm() {
  local want=$1 p
  for p in $APPLIED; do
    [ "$p" = "$want" ] && continue
    git apply -R ".gitlab-ci/ab/$p.patch" || { log "FATAL: cannot revert $p"; exit 1; }
    APPLIED=$(echo " $APPLIED " | sed "s/ $p / /; s/^ *//; s/ *$//")
  done
  if [ "$want" != base ] && ! echo " $APPLIED " | grep -q " $want "; then
    git apply ".gitlab-ci/ab/$want.patch" || { log "FATAL: cannot apply $want"; exit 1; }
    APPLIED="$APPLIED $want"
  fi
  IDENT="patches=[${APPLIED# }] diff=$(git diff -- core | sha1sum | cut -c1-10)"
}

set_fc_arm() {
  sed -i "/$FC_TAG\$/d" "$CLI_INI"
  if [ "$1" = on ]; then
    mkdir -p /tmp/opcache-cli && chown www-data:www-data /tmp/opcache-cli
    echo "opcache.file_cache=/tmp/opcache-cli $FC_TAG" >> "$CLI_INI"
  fi
  IDENT="file_cache=$(fc_dir)"
}

unit_stop() {
  local pid
  [ -s /var/run/unit.pid ] || return 0
  pid=$(cat /var/run/unit.pid)
  kill -QUIT "$pid" 2>/dev/null
  for _ in $(seq 1 100); do kill -0 "$pid" 2>/dev/null || break; sleep 0.1; done
  pgrep -x unitd >/dev/null && { pkill -9 -x unitd; sleep 0.5; }
  rm -f /var/run/unit.pid /var/run/control.unit.sock
}

unit_start() {
  local p=/opt/u-$1
  rm -rf "$p/state" && mkdir -p "$p/state"
  "$p/sbin/unitd" --statedir "$p/state" --control unix:/var/run/control.unit.sock \
    --pid /var/run/unit.pid --log /var/log/unit.log
  for _ in $(seq 1 100); do [ -S /var/run/control.unit.sock ] && break; sleep 0.1; done
  curl -sS --fail-with-body -X PUT --data-binary @.gitlab-ci/unit.json \
    --unix-socket /var/run/control.unit.sock http://localhost/config >/dev/null \
    || { log "FATAL: unit config rejected"; exit 1; }
  IDENT="unit=$("$p/sbin/unitd" --version 2>&1 | head -1 | awk '{print $3}') unitd=$(sha256sum "$p/sbin/unitd" | cut -c1-10) mod=$(sha256sum "$p/modules/php.unit.so" | cut -c1-10)"
}

# Puts the arm in place and restarts the web server, so its SHM opcache and
# APCu start cold in every pass.
set_arm() {
  case $AB_KIND in
    fc) set_fc_arm "$1"; service apache2 restart >/dev/null 2>&1 ;;
    patch) set_patch_arm "$1"; service apache2 restart >/dev/null 2>&1 ;;
    unit) unit_stop; : > /var/log/unit-access.log; chown www-data /var/log/unit-access.log 2>/dev/null; unit_start "$1" ;;
  esac
}

# ---- one pass ---------------------------------------------------------------
TESTS_CMD=(sudo -u www-data -E -H php ./core/scripts/run-tests.sh
  --phpunit-configuration "$PHPUNIT_CONFIGURATION_FILE_PATH" --color --keep-results
  --concurrency "$CONCURRENCY" --repeat 1 --dburl "$SIMPLETEST_DB" --url "$SIMPLETEST_BASE_URL"
  --verbose --non-html)
if [ -n "${AB_TARGET:-}" ]; then
  read -r -a TARGET <<< "$AB_TARGET"
else
  TARGET=(--debug-discovery --types "$TESTSUITE" --all --ci-parallel-node-index "$SHARD" --ci-parallel-node-total "$TOTAL")
fi

clean_tests() {
  sudo -u www-data -E -H php ./core/scripts/run-tests.sh --clean --dburl "$SIMPLETEST_DB" >/dev/null 2>&1
  rm -rf ./sites/default/files/simpletest ./sites/simpletest/browser_output
  mkdir -p ./sites/default/files/simpletest ./sites/simpletest
  chown -R www-data:www-data ./sites/default/files ./sites/simpletest
}

run_pass() {
  local n=$1 arm=$2 ident c0 c1 p0 p1 h0 h1 t0 t1 rc dir files owners srv req
  dir=$OUT/pass-$n-$arm
  mkdir -p "$dir"
  set_arm "$arm"; ident=$IDENT
  clean_tests
  # Q2's fc-on arm measures a cold cache, like every real job: wipe it after
  # the clean step (which already ran PHP as www-data).
  if [ "$AB_KIND" = fc ] && [ "$arm" = on ]; then rm -rf /tmp/opcache-cli/*; fi
  sleep 1
  log "pass $n arm=$arm $ident"
  c0=$(cpu_usage); p0=$(psi_some); read -r h0 ht0 <<< "$(host_stat)"; t0=$(date +%s.%N)
  "${TESTS_CMD[@]}" "${TARGET[@]}" > "$dir/run-tests.log" 2>&1
  rc=$?
  t1=$(date +%s.%N); c1=$(cpu_usage); p1=$(psi_some); read -r h1 ht1 <<< "$(host_stat)"
  srv=$(server_cpu)
  cp ./sites/default/files/simpletest/phpunit-*.xml "$dir/" 2>/dev/null
  sed -i "s#$CI_PROJECT_DIR/##" "$dir"/phpunit-*.xml 2>/dev/null
  sed 's/\x1b\[[0-9;]*m//g' "$dir/run-tests.log" | grep -E '^ +[0-9.]+s [A-Z]' > "$dir/classes.txt"
  files=-; owners=-
  fc=$(fc_dir)
  if [ -n "$fc" ] && [ -d "$fc" ]; then
    files=$(find "$fc" -type f | wc -l)
    owners=$(find "$fc" -mindepth 1 -maxdepth 1 -printf '%u\n' | sort -u | tr '\n' '+')
    # The cache must really be in use by www-data whenever it is on.
    if [ "$AB_KIND" != fc ] || [ "$arm" = on ]; then
      if [ "$files" -eq 0 ] || [ "$owners" != "www-data+" ]; then
        log "ASSERT: file cache not used by www-data (files=$files owners=$owners)"; FAIL=1
      fi
    fi
  fi
  req=-
  if [ -s /var/log/unit-access.log ]; then
    cp /var/log/unit-access.log "$dir/"
    req=$(awk '{n++; s+=$NF} END {printf "n=%d,time_s=%.1f", n, s}' /var/log/unit-access.log)
  fi
  printf 'AB\t%s\t%s\tshard=%s/%s\tpass=%s\tarm=%s\tcpu_s=%.2f\twall_s=%.2f\trc=%s\tclasses=%s\thost_busy=%.3f\tpsi_s=%.2f\tload=%s\tsrv=%s\treq=%s\tfc_files=%s\tfc_owner=%s\tid=%s\n' \
    "$CI_JOB_ID" "$CI_JOB_NAME" "$SHARD" "$TOTAL" "$n" "$arm" \
    "$(calc "($c1 - $c0) / 1000000")" "$(calc "$t1 - $t0")" "$rc" \
    "$(wc -l < "$dir/classes.txt")" "$(calc "($h1 - $h0) / ($ht1 - $ht0)")" \
    "$(calc "($p1 - $p0) / 1000000")" "$(cut -d' ' -f1 /proc/loadavg)" \
    "$srv" "$req" "$files" "$owners" "$ident" | tee -a "$SUM"
}

# ---- microbenchmark (unit) -----------------------------------------------------
micro() {
  local r arm url u0 u1 rps
  echo '<?php echo "ok";' > ./core/ab-hello.php
  for r in $(seq 1 "${AB_MICRO_ROUNDS:-3}"); do
    for i in $(echo "$SEQ" | fold -w1); do
      arm=${ARMS[$i]}
      set_arm "$arm"
      for url in core/ab-hello.php core/misc/druplicon.png; do
        wrk -t2 -c6 -d2s "$SIMPLETEST_BASE_URL/$url" >/dev/null 2>&1
        u0=$(router_jiffies)
        rps=$(wrk -t2 -c6 -d8s "$SIMPLETEST_BASE_URL/$url" 2>&1 | tee -a "$OUT/wrk.log" | awk '/Requests\/sec/ {print $2}')
        u1=$(router_jiffies)
        printf 'MICRO\t%s\tround=%s\tarm=%s\turl=%s\trps=%s\trouter_us_per_req=%.1f\n' "$CI_JOB_ID" "$r" "$arm" "$url" "$rps" \
          "$(calc "${rps:-0} > 0 ? ($u1 - $u0) * 1000000 / $(getconf CLK_TCK) / (${rps:-1} * 8) : -1")" | tee -a "$SUM"
      done
    done
  done
  rm -f ./core/ab-hello.php
}
router_jiffies() {
  local p
  p=$(pgrep -f '^unit: router' | head -1)
  sed 's/.*) //' /proc/$p/stat | awk '{print $12 + $13}'
}

# ---- main ---------------------------------------------------------------------
log "job=$CI_JOB_NAME kind=$AB_KIND arms=${ARMS[*]} seq=$SEQ shard=$SHARD/$TOTAL conc=$CONCURRENCY"
log "cpu_model=$(awk -F': ' '/^model name/ {print $2; exit}' /proc/cpuinfo) nproc=$(nproc) cpu.max=$(cat /sys/fs/cgroup/cpu.max) node=$HOSTNAME"
log "php=$($PHPQ -v | head -1) built=$($PHPQ -i | awk -F'=> ' '/^Build Date/ {print $2; exit}')"
git config --global --add safe.directory "$CI_PROJECT_DIR"

case $AB_KIND in
  unit)
    # The image's own unitd was started by server-setup.sh; the arms replace it.
    # The image's php-cli.ini already sets a file cache: root probes in
    # server-setup/ci-diag may have claimed it, so hand it back to www-data.
    d=$(fc_dir); [ -n "$d" ] && rm -rf "${d:?}"/* && chown www-data:www-data "$d"
    unit_stop
    ;;
  patch)
    set_fc_arm on
    ;;
esac

if [ -n "${AB_WARMUP:-}" ]; then
  set_arm "${ARMS[${SEQ:0:1}]}"
  clean_tests
  log "warm-up: $AB_WARMUP"
  find core vendor -name '*.php' -print0 | xargs -0 cat > /dev/null
  "${TESTS_CMD[@]}" --file "$AB_WARMUP" > "$OUT/warmup.log" 2>&1
  log "warm-up rc=$?"
fi

[ "${AB_MICRO:-0}" = 1 ] && micro

n=0
for i in $(echo "$SEQ" | fold -w1); do
  n=$((n + 1))
  run_pass "$n" "${ARMS[$i]}"
done

[ "$AB_KIND" = unit ] && unit_stop
[ "$AB_KIND" = patch ] && set_patch_arm base
log "done; summary:"
cat "$SUM"
exit $FAIL
