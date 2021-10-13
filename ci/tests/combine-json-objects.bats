#!/usr/bin/env bats

script_name=$(basename "$BATS_TEST_FILENAME" | sed 's/bats/sh/g')
export script_name

setup() {
  load 'node_modules/bats-support/load'
  load 'node_modules/bats-assert/load'
}

main() {
  bash "${BATS_TEST_DIRNAME}/../scripts/$script_name" "$ARG1" "$ARG2"
}

@test "$script_name: first argument is non-json" {
  ARG1="non-json value" \
  ARG2="[]" \
  run main

  assert_failure "1"
  assert_line --index 0 --regexp "parse error: Invalid literal at line [0-9]+, column [0-9]+"
  assert_line --index 1 "Invalid (non-json)"
  assert_line --index 2 "ARG1: non-json value"
  assert_line --index 3 "ARG2: []"
  assert_line --index 4 "jq exit code: 4"
}

@test "$script_name: second argument is non-json" {
  ARG1="{}" \
  ARG2="non-json value" \
  run main

  assert_failure "1"
  assert_line --index 0 --regexp "parse error: Invalid literal at line [0-9]+, column [0-9]+"
  assert_line --index 1 "Invalid (non-json)"
  assert_line --index 2 "ARG1: {}"
  assert_line --index 3 "ARG2: non-json value"
  assert_line --index 4 "jq exit code: 4"
}

@test "$script_name: two empty objects combine into an empty object" {
  ARG1="{}" \
  ARG2="{}" \
  run main

  assert_success
  assert_output "{}"
}

@test "$script_name: two empty arrays combine into an empty array" {
  ARG1="[]" \
  ARG2="[]" \
  run main

  assert_success
  assert_output "[]"
}

@test "$script_name: empty first argument and empty object combine into empty object" {
  ARG1="" \
  ARG2="{}" \
  run main

  assert_success
  assert_output "{}"
}

@test "$script_name: two objects without overlapping keys combine" {
  ARG1='{"key1": "value1"}' \
  ARG2='{"key2": "value2"}' \
  run main

  assert_success
  assert_output "{
  \"key1\": \"value1\",
  \"key2\": \"value2\"
}"
}

@test "$script_name: two objects with overlapping keys combine; second arg overrides first" {
  ARG1='{"field1": 0, "label": "first label"}' \
  ARG2='{"field2": 1, "label": "second label"}' \
  run main

  assert_success
  assert_output "{
  \"field1\": 0,
  \"label\": \"second label\",
  \"field2\": 1
}"
}
