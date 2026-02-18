#!/bin/bash

CACHE_FILE="$1"

if [[ "$CI_MERGE_REQUEST_LABELS" =~ "Test artifact cache" ]]; then
  CACHE_URL="https://git.drupalcode.org/api/v4/projects/${CI_PROJECT_ID}/jobs/artifacts/${CI_MERGE_REQUEST_SOURCE_BRANCH_NAME}/raw/${CACHE_FILE}?job=Write%20lint%20caches"
else
  CACHE_URL="https://git.drupalcode.org/api/v4/projects/project%2Fdrupal/jobs/artifacts/${CI_MERGE_REQUEST_TARGET_BRANCH_NAME}${CI_COMMIT_BRANCH}/raw/${CACHE_FILE}?job=Write%20lint%20caches"
fi

# Get the cache file from the artifacts of the latest successful job from the
# target branch. Allow the job to proceed and pass if the file doesn't exist.
echo "Downloading $CACHE_URL"
curl --fail --location --create-dirs --output "$CACHE_FILE" "$CACHE_URL" || true
