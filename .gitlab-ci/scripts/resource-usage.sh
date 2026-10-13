#!/bin/bash
#
# Reports the CPU and memory used by the job's container, to help size
# KUBERNETES_CPU_REQUEST and CONCURRENCY. Reads the container's cgroup (v2)
# files, so it must run inside the job container, for example in after_script.

# cspell:ignore getconf Ksection loadavg

cgroup=/sys/fs/cgroup

echo -e "\e[0Ksection_start:$(date +%s):resource_usage\r\e[0KResource usage of this job's container"

if [ ! -f "$cgroup/cpu.stat" ]; then
  echo "  Not available: this node does not use cgroup v2."
  echo -e "\e[0Ksection_end:$(date +%s):resource_usage\r\e[0K"
  exit 0
fi

# The container's run time, from the start time of its first process.
ticks=$(getconf CLK_TCK)
start=$(awk '{print $22}' /proc/1/stat)
uptime=$(awk '{print $1}' /proc/uptime)
run_time=$(awk -v u="$uptime" -v s="$start" -v t="$ticks" 'BEGIN {printf "%.0f", u - s / t}')

cpu_used=$(awk '$1 == "usage_usec" {print $2}' "$cgroup/cpu.stat")
cpu_waiting=$(awk '$1 == "some" {sub("total=", "", $5); print $5}' "$cgroup/cpu.pressure" 2>/dev/null)
cpu_max=$(cat "$cgroup/cpu.max" 2>/dev/null)
memory_peak=$(cat "$cgroup/memory.peak" 2>/dev/null)
memory_max=$(cat "$cgroup/memory.max" 2>/dev/null)

awk -v run="$run_time" -v used="$cpu_used" -v waiting="${cpu_waiting:-0}" \
  -v request="${KUBERNETES_CPU_REQUEST:-}" -v concurrency="${CONCURRENCY:-not set}" \
  -v cpu_max="${cpu_max:-max}" -v peak="${memory_peak:-0}" -v memory_max="${memory_max:-max}" 'BEGIN {
  average = (run > 0) ? used / 1e6 / run : 0
  split(cpu_max, quota, " ")
  printf "  Run time:          %d s\n", run
  printf "  CPU request:       %s (concurrency %s)\n", (request != "") ? request : "the runner default", concurrency
  if (request > 0) {
    printf "  CPU used:          %.1f CPUs on average, %.0f%% of the request\n", average, 100 * average / request
  }
  else {
    printf "  CPU used:          %.1f CPUs on average\n", average
  }
  printf "  CPU limit:         %s\n", (quota[1] == "max") ? "none" : sprintf("%.1f CPUs", quota[1] / quota[2])
  printf "  Waiting for CPU:   %.1f%% of the run time\n", (run > 0) ? 100 * waiting / 1e6 / run : 0
  printf "  Memory peak:       %s\n", (peak > 0) ? sprintf("%.1f GiB", peak / 2^30) : "not available"
  printf "  Memory limit:      %s\n", (memory_max == "max") ? "none" : sprintf("%.1f GiB", memory_max / 2^30)
}'

echo "  Node:              $(nproc) CPUs, $(awk '/^MemTotal/ {printf "%.0f", $2 / 2^20}' /proc/meminfo) GiB memory, load $(cut -d' ' -f1 /proc/loadavg),$(grep -m1 'model name' /proc/cpuinfo | cut -d: -f2)"

echo -e "\e[0Ksection_end:$(date +%s):resource_usage\r\e[0K"
