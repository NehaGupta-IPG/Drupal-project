/**
 * @file
 * Counts how often the loaded rows' library runs.
 */

((window) => {
  window.treegridTestUiRowAssetRuns =
    (window.treegridTestUiRowAssetRuns ?? 0) + 1;
})(window);
