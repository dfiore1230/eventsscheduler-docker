#!/usr/bin/env bash
set -euo pipefail

CREDENTIALS_FILE="${CREDENTIALS_FILE:-.github/.dockerhub-credentials}"

if [[ ! -f "$CREDENTIALS_FILE" ]]; then
  echo "Credentials file '$CREDENTIALS_FILE' not found. Copy .github/.dockerhub-credentials.example and fill it out." >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "The GitHub CLI (gh) must be installed and authenticated to upload secrets." >&2
  exit 1
fi

# shellcheck disable=SC1090
source "$CREDENTIALS_FILE"

required_vars=(DOCKERHUB_USERNAME DOCKERHUB_TOKEN DOCKERHUB_REPOSITORY)
for var in "${required_vars[@]}"; do
  if [[ -z "${!var:-}" ]]; then
    echo "Environment variable '$var' is missing in $CREDENTIALS_FILE." >&2
    exit 1
  fi
  printf 'Setting repository secret %s...\n' "$var"
  gh secret set "$var" --body "${!var}"
done

echo "Docker Hub secrets uploaded. Re-run the GitHub Actions workflow to publish the image."
