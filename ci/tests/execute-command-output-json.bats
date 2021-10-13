#!/usr/bin/env bats

script_name=$(basename "$BATS_TEST_FILENAME" | sed 's/bats/sh/g')
export script_name

setup() {
  load 'node_modules/bats-support/load'
  load 'node_modules/bats-assert/load'
}

main() {
  bash "${BATS_TEST_DIRNAME}/../scripts/$script_name"
}

@test "$script_name: no command argument executes nothing, outputs nothing and is successful" {
  run main

  assert_success
  assert_output ""
}

@test "$script_name: command output is empty and returns exit code 9" {
  COMMAND="./ci/tests/fixtures/output-nothing-and-exit-9.sh" \
  run main

  assert_failure "2"
  assert_output "{
  \"error\": \"command failure\",
  \"command-exit-code\": 9,
  \"command-output\": \"\"
}"
}

@test "$script_name: command output is non-json and returns exit code 7" {
  COMMAND="./ci/tests/fixtures/output-non-json-and-exit-7.sh" \
  run main

  assert_failure "2"
  assert_output "{
  \"error\": \"command failure\",
  \"command-exit-code\": 7,
  \"command-output\": \"non-json content\"
}"
}

@test "$script_name: command output is json and returns exit code 5" {
  COMMAND="./ci/tests/fixtures/output-json-and-exit-5.sh" \
  run main

  assert_failure "2"
  assert_output "{
  \"error\": \"command failure\",
  \"command-exit-code\": 5,
  \"command-output\": \"{}\"
}"
}

@test "$script_name: command output is non-json and returns exit code 0" {
  COMMAND="./ci/tests/fixtures/output-non-json-and-exit-0.sh" \
  run main

  assert_failure "3"
  assert_output "{
  \"error\": \"jq failure\",
  \"jq-exit-code\": 4,
  \"jq-output\": \"parse error: Invalid literal at line 1, column 9\"
}"
}

@test "$script_name: command output is json and returns exit code 0" {
  COMMAND="./ci/tests/fixtures/output-json-and-exit-0.sh" \
  run main

  assert_success
  assert_output "{}"
}
