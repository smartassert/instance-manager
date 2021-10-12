#!/usr/bin/env bash

if [ ! -f "$ENV_FILE_PATH" ]; then
  echo "Configuration for service $SERVICE_ID not found: $ENV_FILE_PATH"
  exit 1
fi

echo "$(<"$ENV_FILE_PATH")"
