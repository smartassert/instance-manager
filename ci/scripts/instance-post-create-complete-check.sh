#!/usr/bin/env bash

DELAY="${DELAY:-30}"
LIMIT="${LIMIT:-5}"
HAS_SUCCEEDED=0

function run_command_until_successful () {
  local count=0

  until [ "$count" -ge "$LIMIT" ]
  do
    echo "Attempt $((count+1)) of $LIMIT"
    "$@" && HAS_SUCCEEDED=1 && break
    printf "\n"
    count=$((count+1))
    echo -e "Retrying in $DELAY seconds\n"
    sleep "$DELAY"
  done
}

run_command_until_successful php bin/console "app:instance:is-post-create-complete" --id="$1"

if [ "1" != "$HAS_SUCCEEDED" ]; then
  exit 1
fi
