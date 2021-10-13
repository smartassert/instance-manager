#!/usr/bin/env bash

COMBINED=$(echo "$1 $2" | jq -s add)
JQ_EXIT_CODE="$?"
if [ "0" != "$JQ_EXIT_CODE" ]; then
  echo "Invalid (non-json)"
  echo "ARG1: $1"
  echo "ARG2: $2"
  echo "jq exit code: $JQ_EXIT_CODE"
  exit 1
fi

echo "$COMBINED"
