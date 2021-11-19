#!/usr/bin/env bash

SECRETS_CONTENT='{"NOT_RELEVANT_1":"value","USERS_TEST_001":"secret 001","USERS_TEST_002":"secret 002"}'

#jq -r '.USERS_TEST_001' <<< "$SECRETS_CONTEXT" > "./secret.txt"
#jq '.USERS_TEST_001' <<< "$SECRETS_CONTENT"

SERVICE_ID="users"
ALLOWED_SECRET_KEY_PREFIX="$(echo ${SERVICE_ID^^})_"

ENV_VAR_OPTIONS="--env-var='KEY1=VALUE1' --env-var='KEY2={{ secrets.USERS_TEST_001 }}' --env-var='KEY3={{ secrets.USERS_TEST_003 }}'"
echo "$ENV_VAR_OPTIONS"

SECRETS_PLACEHOLDERS=$(grep -Eo "\{\{ secrets\.[A-Za-z0-9_-]+ \}\}" <<< "$ENV_VAR_OPTIONS")
#echo "$SECRETS_PLACEHOLDERS"

IFS=$'\n'
for item in $SECRETS_PLACEHOLDERS; do
  SECRET_KEY_NAME="${item/"{{ secrets."/""}"
  SECRET_KEY_NAME="${SECRET_KEY_NAME/" }}"/""}"

#  echo "$item"
  echo "$SECRET_KEY_NAME"

  SECRET_EXISTS=$(jq "has(\"$SECRET_KEY_NAME\")" <<< "$SECRETS_CONTENT")

  if [ "false" = "$SECRET_EXISTS" ]; then
    echo "Secret '$SECRET_KEY_NAME' has not been defined"
#    exit 1
  fi

  SECRET_VALUE=$(jq -r ".$SECRET_KEY_NAME" <<< "$SECRETS_CONTENT")
  echo "$SECRET_VALUE"

  ENV_VAR_OPTIONS="${ENV_VAR_OPTIONS//"{{ secrets.$SECRET_KEY_NAME }}"/"$SECRET_VALUE"}"
done

echo "$ENV_VAR_OPTIONS"

#FOO=$(./vendor/smartassert/bash-ga-ci-tools/src/output-json-scalar-object.sh "{{ key }}={{ value }}\n" <<< '{"key1":"value1","key2":"value2"}')
#echo "$FOO"
#
#IFS=$'\n'
#count=0
#for item in $FOO
#do
#  echo "$item"
#
#  if [[ $item == "$ALLOWED_SECRET_KEY_PREFIX"* ]]; then
#    key=$(cut -d'=' -f1 <<< "$item")
#    value=$(cut -d'=' -f1 <<< "$item")
#    echo "key: $key"
#    echo "value: $value"
#  fi
#done
