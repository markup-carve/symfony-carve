#!/usr/bin/env bash
set -euo pipefail

# Builds this checkout (0.2, latest) at the site root and 0.1 at 0.1/, each with its own
# docs/package files, config and theme. An empty ref builds this checkout itself.
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
latest_ref="${DOCS_LATEST_REF-}"
latest_base="${DOCS_LATEST_BASE:-/symfony-carve/}"
latest_label="${DOCS_LATEST_LABEL:-0.2 (latest)}"
latest_edit_branch="${DOCS_LATEST_EDIT_BRANCH:-main}"
latest_series="${DOCS_LATEST_SERIES:-}"
secondary_ref="${DOCS_SECONDARY_REF-origin/0.1}"
secondary_base="${DOCS_SECONDARY_BASE:-/symfony-carve/0.1/}"
secondary_label="${DOCS_SECONDARY_LABEL:-0.1}"
secondary_edit_branch="${DOCS_SECONDARY_EDIT_BRANCH:-0.1}"
secondary_series="${DOCS_SECONDARY_SERIES-}"
combined_dist="$repo_dir/docs/.vitepress/dist-versioned"
worktrees=()

cleanup() {
  for tree in "${worktrees[@]}"; do
    git -C "$repo_dir" worktree remove --force "$tree"
  done
}
trap cleanup EXIT

side_tree() {
  local ref="$1"
  if [[ -z "$ref" ]]; then
    echo "$repo_dir"
    return
  fi
  local tree
  tree="$(mktemp -d "${TMPDIR:-/tmp}/symfony-carve-docs-side.XXXXXX")"
  git -C "$repo_dir" worktree add --detach "$tree" "$ref" >&2
  echo "$tree"
}

build_side() {
  local tree="$1" base="$2" label="$3" edit_branch="$4" series="$5"
  echo "Building $label from $tree at $base"
  (
    cd "$tree/docs"
    npm ci
    DOCS_BASE="$base" \
      DOCS_VERSION_TEXT="$label" \
      DOCS_LATEST_BASE="$latest_base" \
      DOCS_LATEST_LABEL="$latest_label" \
      DOCS_OTHER_BASE="$secondary_base" \
      DOCS_OTHER_LABEL="$secondary_label" \
      DOCS_EDIT_BRANCH="$edit_branch" \
      VITE_DOCS_SERIES="$series" \
      npm run docs:build
  )
}

secondary_subpath="${secondary_base#"$latest_base"}"
secondary_subpath="${secondary_subpath%/}"
if [[ -z "$secondary_subpath" || "$secondary_subpath" == "$secondary_base" || "$secondary_subpath" == *..* ]]; then
  echo "Secondary base must be a child of latest base" >&2
  exit 1
fi

latest_tree="$(side_tree "$latest_ref")"
[[ "$latest_tree" == "$repo_dir" ]] || worktrees+=("$latest_tree")
build_side "$latest_tree" "$latest_base" "$latest_label" "$latest_edit_branch" "$latest_series"

secondary_tree="$(side_tree "$secondary_ref")"
[[ "$secondary_tree" == "$repo_dir" ]] || worktrees+=("$secondary_tree")
build_side "$secondary_tree" "$secondary_base" "$secondary_label" "$secondary_edit_branch" "$secondary_series"

# Only built output crosses refs, so a failed side leaves no combined site to upload.
node -e 'import("node:fs").then(fs => fs.rmSync(process.argv[1], { recursive: true, force: true }))' "$combined_dist"
mkdir -p "$combined_dist/$secondary_subpath"
cp -R "$latest_tree/docs/.vitepress/dist/." "$combined_dist/"
cp -R "$secondary_tree/docs/.vitepress/dist/." "$combined_dist/$secondary_subpath/"
echo "Combined docs: $combined_dist"
