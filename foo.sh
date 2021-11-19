#!/usr/bin/env bash

ENV_VARS_JSON='{"key1":"value1","key2":"value2"}'
CONFIGURATION_PATH=./env.json

jq "." <<< "$ENV_VARS_JSON" > "$CONFIGURATION_PATH"
