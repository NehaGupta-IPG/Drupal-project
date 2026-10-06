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
#   AB_KIND    fc | patch | unit
#   AB_ARMS    arm names, e.g. "off on", "base B", "base A B", "ac n137".
#              fc: an arm named on* has the CLI file cache, anything else not.
#   AB_ROUNDS  rounds (default: number of arms, so every arm visits every slot)
#   AB_SHARDS  shard index per parallel job, e.g. "2 3"; AB_TOTAL shard total
#   AB_TARGET  optional run-tests.sh selection args instead of a shard
#   AB_WARMUP  comma-separated test files for the unmeasured warm-up
#   AB_MICRO   1 = concurrent wrk microbenchmark of the unit arms first
#   AB_FILLER  0 = no filler (FunctionalJavascript: killing filler strands
#              Selenium sessions in the shared browser)
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

calc() { awk "BEGIN {print ($1)}"; }
log() { echo "[ab $(date -u +%T)] $*"; }
cpu_usage() { awk '/^usage_usec/ {print $2}' /sys/fs/cgroup/cpu.stat; }
host_stat() { awk '/^cpu / {t=0; for (i=2;i<=NF;i++) t+=$i; print t-$5-$6, t}' /proc/stat; }
slot_dir() { [ "$1" = 0 ] && echo "$CI_PROJECT_DIR" || echo "$CI_PROJECT_DIR/.ab/slot$1"; }
docroot() { [ "$1" = 0 ] && echo /var/www/html || echo "/var/www/html$1"; }
port() { echo $(( $1 == 0 ? 80 : 8080 + $1 )); }
base_url() { [ "$1" = 0 ] && echo "$SIMPLETEST_BASE_URL" || echo "http://localhost:$(port "$1")/subdirectory"; }

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
  local s d
  for s in $(seq 0 $((N - 1))); do
    d=$(slot_dir "$s")
    if [ "$s" != 0 ]; then
      mkdir -p "$d"
      tar -C "$CI_PROJECT_DIR" --exclude=./.git --exclude=./.ab --exclude=./ab-out --exclude=./sites/default/files \
        --exclude=./sites/simpletest -cf - . | tar -C "$d" -xf -
      mkdir -p "$(docroot "$s")"
      ln -sfn "$d" "$(docroot "$s")/subdirectory"
    fi
    mkdir -p "$d/sites/default/files" "$d/sites/simpletest" "/tmp/ab-tmp-$s"
    chown -R www-data:www-data "$d/sites" "/tmp/ab-tmp-$s"
    : > "$d/.ab-patch"
    if [ "$AB_KIND" != unit ] && [ "$s" != 0 ]; then
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
      if [[ $arm == on* ]]; then
        mkdir -p "/tmp/ab-ini-$arm" "/tmp/opcache-cli-$arm"
        echo "opcache.file_cache=/tmp/opcache-cli-$arm" > "/tmp/ab-ini-$arm/fc.ini"
        # Cold, like every real job.
        rm -rf "/tmp/opcache-cli-$arm"/*
        chown www-data:www-data "/tmp/opcache-cli-$arm"
        ARM_ENV[$s]="PHP_INI_SCAN_DIR=:/tmp/ab-ini-$arm"
      fi
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
}

arm_ident() {
  local s=$1 d fc
  d=$(slot_dir "$s")
  fc=$(cd "$d" && sudo -u www-data -E -H env ${ARM_ENV[$s]} $PHPQ -r 'echo ini_get("opcache.file_cache");')
  printf 'fc=%s' "${fc:-none}"
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
  [ "$AB_KIND" = unit ] && return 0
  service apache2 restart >/dev/null 2>&1 || { log "FATAL: apache restart"; tail -5 /var/log/apache2/error.log; exit 1; }
}

# ---- running ----------------------------------------------------------------------
if [ -n "${AB_TARGET:-}" ]; then
  read -r -a TARGET <<< "$AB_TARGET"
else
  TARGET=(--types "$TESTSUITE" --all --ci-parallel-node-index "$SHARD" --ci-parallel-node-total "$TOTAL")
fi

# run_tests <slot> <args...>: run-tests.sh in the slot as www-data with the arm env.
run_tests() {
  local s=$1
  shift
  # run-tests.sh probes symlinks at a fixed $TMPDIR/test_symlink, so runs that
  # start together collide; each slot gets its own TMPDIR.
  cd "$(slot_dir "$s")" && sudo -u www-data -E -H env ${ARM_ENV[$s]} TMPDIR="/tmp/ab-tmp-$s" SIMPLETEST_BASE_URL="$(base_url "$s")" \
    php ./core/scripts/run-tests.sh --phpunit-configuration "$(slot_dir "$s")/core" --color --keep-results \
    --concurrency "$CONC" --repeat 1 --dburl "$SIMPLETEST_DB" --url "$(base_url "$s")" --verbose --non-html "$@"
}

clean_tests() {
  local s d
  (cd "$CI_PROJECT_DIR" && sudo -u www-data -E -H php ./core/scripts/run-tests.sh --clean --dburl "$SIMPLETEST_DB" >/dev/null 2>&1)
  for s in $(seq 0 $((N - 1))); do
    d=$(slot_dir "$s")
    rm -rf "$d/sites/default/files/simpletest" "$d/sites/simpletest"
    mkdir -p "$d/sites/default/files/simpletest" "$d/sites/simpletest"
    chown -R www-data:www-data "$d/sites/default/files" "$d/sites/simpletest"
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
times_cpu() { sed -n 2p "$1" | awk '{n=0; for (i=1;i<=2;i++) {split($i, a, /[ms]/); n += a[1]*60 + a[2]} printf "%.2f", n}'; }

# Unmeasured filler: the same shard again and again, in its own session.
filler() {
  local s=$1
  setsid bash -c "$(declare -f run_tests slot_dir base_url port); declare -A ARM_ENV; ARM_ENV[$s]='${ARM_ENV[$s]}'; CONC=$CONC; \
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
  [ "$AB_KIND" = unit ] || srvtot=$(tree_cpu "$(cat /var/run/apache2/apache2.pid 2>/dev/null || echo 1)")
  for i in $(seq 0 $((N - 1))); do
    [[ ${fill[$i]} =~ ^[0-9]+$ ]] && kill -TERM -- "-${fill[$i]}" 2>/dev/null
  done
  sleep 2
  pkill -9 -u www-data -x php 2>/dev/null
  for i in $(seq 0 $((N - 1))); do
    s=$(( (i + r) % N )); arm=${ARMS[$i]}; dir=$OUT/round-$r-$arm
    sed 's/\x1b\[[0-9;]*m//g' "$dir/run-tests.log" | grep -E '^ +[0-9.]+s [A-Z]' > "$dir/classes.txt"
    srv=-; req=-; fcf=-
    [ -s "$dir/srv" ] && srv=$(cat "$dir/srv")
    [ -s "$dir/req" ] && req=$(cat "$dir/req")
    fcd=$(cd "$(slot_dir "$s")" && sudo -u www-data -E -H env ${ARM_ENV[$s]} $PHPQ -r 'echo ini_get("opcache.file_cache");')
    if [ -n "$fcd" ] && [ -d "$fcd" ]; then
      fcf="$(find "$fcd" -type f | wc -l):$(find "$fcd" -mindepth 1 -maxdepth 1 -printf '%u\n' | sort -u | tr '\n' '+')"
      if [[ $fcf == 0:* || $fcf != *:www-data+ ]]; then log "ASSERT: file cache not used by www-data in $arm ($fcf)"; FAIL=1; fi
    elif [[ $AB_KIND == patch || $arm == on* ]]; then
      log "ASSERT: file cache missing in $arm"; FAIL=1
    fi
    printf 'AB\t%s\t%s\tshard=%s/%s\tround=%s\tslot=%s\tarm=%s\tcpu_s=%s\twall_s=%.2f\trc=%s\tclasses=%s\tround_job_cpu_s=%.2f\tround_host_busy=%.3f\tload=%s\tsrv=%s\tround_srv_total=%s\treq=%s\tfc=%s\tid=%s\n' \
      "$CI_JOB_ID" "$CI_JOB_NAME" "$SHARD" "$TOTAL" "$r" "$s" "$arm" "$(times_cpu "$dir/times")" "$(cat "$dir/wall")" \
      "$(cat "$dir/rc")" "$(wc -l < "$dir/classes.txt")" "$(calc "($c1 - $c0) / 1000000")" \
      "$(calc "($h1 - $h0) / ($ht1 - $ht0)")" "$(cut -d' ' -f1 /proc/loadavg)" "$srv" "$srvtot" "$req" "$fcf" "${IDENT[$s]}" | tee -a "$SUM"
  done
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
    (run_tests "$s" --file "$AB_WARMUP" > "$OUT/warmup-$s.log" 2>&1; log "warm-up slot $s rc=$?") &
  done
  wait
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
