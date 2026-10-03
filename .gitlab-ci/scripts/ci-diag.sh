#!/bin/bash
# Diagnostics for comparing web servers and PHP settings across CI runs
# (#3627452, draft MR only). Never fails the job.
#   ci-diag.sh start   after the web server started (from server-setup.sh)
#   ci-diag.sh end     from after_script
set +e
cd "${CI_PROJECT_DIR:-.}" || exit 0
LOG="./ci-diag-$(echo "${CI_JOB_NAME:-local}" | tr -c 'A-Za-z0-9_.-' '_' | sed 's/__*/_/g').log"
PROBE=core/ci-diag-probe.php
URL="${SIMPLETEST_BASE_URL:-http://localhost/subdirectory}"
exec >>"$LOG" 2>&1

section() { echo; echo "## $*"; }

# PHP as seen by the web server SAPI, not the CLI.
probe() {
  cat > "$PROBE" <<'PHP'
<?php
$o = function_exists('opcache_get_status') ? opcache_get_status(FALSE) : FALSE;
echo json_encode([
  'sapi' => PHP_SAPI,
  'ini' => php_ini_loaded_file(),
  'memory_limit' => ini_get('memory_limit'),
  'assert.active' => ini_get('assert.active'),
  'zend.assertions' => ini_get('zend.assertions'),
  'output_buffering' => ini_get('output_buffering'),
  'realpath_cache_size' => ini_get('realpath_cache_size'),
  'realpath_cache_used' => realpath_cache_size(),
  'opcache.interned_strings_buffer' => ini_get('opcache.interned_strings_buffer'),
  'opcache.file_cache' => ini_get('opcache.file_cache'),
  'opcache.jit' => ini_get('opcache.jit'),
  'opcache' => $o ? [
    'enabled' => $o['opcache_enabled'],
    'cache_full' => $o['cache_full'],
    'restart_pending' => $o['restart_pending'],
    'memory_used_mb' => round($o['memory_usage']['used_memory'] / 1048576, 1),
    'wasted_pct' => round($o['memory_usage']['current_wasted_percentage'], 2),
    'interned' => $o['interned_strings_usage'] ?? NULL,
    'scripts' => $o['opcache_statistics']['num_cached_scripts'],
    'hits' => $o['opcache_statistics']['hits'],
    'misses' => $o['opcache_statistics']['misses'],
    'oom_restarts' => $o['opcache_statistics']['oom_restarts'],
    'hash_restarts' => $o['opcache_statistics']['hash_restarts'],
    'jit' => $o['jit']['on'] ?? NULL,
  ] : NULL,
  'apcu' => function_exists('apcu_sma_info') ? apcu_sma_info(TRUE) : NULL,
], JSON_PRETTY_PRINT), "\n";
PHP
  curl -s --max-time 10 "$URL/$PROBE"
  rm -f "$PROBE"
}

# CPU seconds of the web server process tree, including reaped children.
server_cpu() {
  local tck p name t
  tck=$(getconf CLK_TCK)
  for p in $(pgrep -x 'unitd|apache2'); do
    name=$(tr '\0' ' ' < /proc/$p/cmdline | sed 's/unit: //; s/ \[.*//; s/  *$//' | cut -c1-40)
    t=$(sed 's/.*) //' /proc/$p/stat | awk '{print $12 + $13 + $14 + $15}')
    echo "$name|$t"
  done | awk -F'|' -v k="$tck" '{c[$1] += $2; n[$1]++} END {for (i in c) printf "  %-40s procs=%-3d cpu_s=%.1f\n", i, n[i], c[i] / k}' | sort
}

case "$1" in
  start)
    section "start $(date -u +%FT%TZ) job=$CI_JOB_NAME runner=$CI_RUNNER_DESCRIPTION host=$HOSTNAME"
    echo "cpu_model: $(awk -F': ' '/^model name/ {print $2; exit}' /proc/cpuinfo)"
    echo "nproc=$(nproc) affinity=$(nproc --all 2>/dev/null)/$(python3 -c 'import os; print(len(os.sched_getaffinity(0)))' 2>/dev/null) cpu.max=$(cat /sys/fs/cgroup/cpu.max 2>/dev/null) memory.max=$(cat /sys/fs/cgroup/memory.max 2>/dev/null)"
    echo "kernel=$(uname -r) loadavg=$(cut -d' ' -f1-3 /proc/loadavg)"
    echo "KUBERNETES_CPU_REQUEST=$KUBERNETES_CPU_REQUEST CONCURRENCY=$CONCURRENCY server=$(command -v unitd >/dev/null && unitd --version 2>&1 | head -1 || apache2 -v 2>/dev/null | head -1)"
    php -v | head -1
    echo "cli: $(php -r 'echo php_ini_loaded_file(), " opcache.enable_cli=", ini_get("opcache.enable_cli"), " opcache.file_cache=", ini_get("opcache.file_cache");')"
    cat /sys/fs/cgroup/cpu.stat 2>/dev/null | tr '\n' ' '; echo
    section "server php (start)"
    probe
    ;;
  end)
    section "end $(date -u +%FT%TZ)"
    echo "loadavg=$(cut -d' ' -f1-3 /proc/loadavg)"
    echo "cpu.stat: $(tr '\n' ' ' < /sys/fs/cgroup/cpu.stat 2>/dev/null)"
    echo "cpu.pressure: $(tr '\n' ' ' < /sys/fs/cgroup/cpu.pressure 2>/dev/null)"
    echo "memory.peak_mb=$(( $(cat /sys/fs/cgroup/memory.peak 2>/dev/null || echo 0) / 1048576 )) memory.pressure: $(tr '\n' ' ' < /sys/fs/cgroup/memory.pressure 2>/dev/null)"
    [ -d /tmp/opcache-cli ] && echo "cli opcache file_cache: files=$(find /tmp/opcache-cli -type f | wc -l) size=$(du -sh /tmp/opcache-cli | cut -f1)"
    section "web server cpu by process (incl. reaped children)"
    server_cpu
    if [ -S /var/run/control.unit.sock ]; then
      section "unit /status"
      sudo curl -s --max-time 5 --unix-socket /var/run/control.unit.sock http://localhost/status
      echo
    fi
    section "access log"
    for f in /var/log/unit-access.log /var/log/apache2/test.apache.access.log /var/log/apache2/access.log; do
      [ -s "$f" ] || continue
      echo "file=$f"
      awk '{n++; s[$9]++} END {printf "requests=%d status:", n; for (k in s) printf " %s=%d", k, s[k]; print ""}' "$f"
      # Unit logs $request_time (seconds) as the last field.
      if [ "$f" = /var/log/unit-access.log ]; then
        awk '{print $NF}' "$f" | sort -n | awk '{a[NR] = $1; s += $1} END {if (NR) printf "time_s sum=%.1f p50=%.4f p95=%.4f p99=%.4f max=%.3f\n", s, a[int(NR*.5)+1], a[int(NR*.95)+1], a[int(NR*.99)+1], a[NR]}'
        echo "slowest:"; awk '{print $NF, $9, $6, $7}' "$f" | tr -d '"' | sort -rn | head -10 | sed 's/^/  /'
      fi
    done
    section "server php (end)"
    probe
    section "server error log tail"
    for f in /var/log/unit.log /var/log/apache2/error.log /var/log/apache2/test.apache.error.log; do
      [ -s "$f" ] && { echo "file=$f lines=$(wc -l < "$f") alerts=$(grep -c -E '\[(alert|crit|emerg)\]' "$f")"; grep -v '\[info\]' "$f" | tail -20; }
    done
    ;;
esac
exit 0
