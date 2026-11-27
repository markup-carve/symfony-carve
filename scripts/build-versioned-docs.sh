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
legacy_base="${DOCS_LEGACY_BASE-/symfony-carve/0.2/}"
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

# The latest docs used to live under the legacy base: every latest page gets a
# stub there that redirects to the same page at the root.
legacy_subpath="${legacy_base#"$latest_base"}"
legacy_subpath="${legacy_subpath%/}"
if [[ -n "$legacy_base" ]]; then
  if [[ -z "$legacy_subpath" || "$legacy_subpath" == "$legacy_base" || "$legacy_subpath" == *..* || "$legacy_subpath" == "$secondary_subpath" ]]; then
    echo "Legacy base must be a child of latest base other than the secondary base" >&2
    exit 1
  fi
  stubs=0
  while IFS= read -r page; do
    target="${page%.html}"
    [[ "$target" == index || "$target" == */index ]] && target="${target%index}"
    target="$latest_base$target"
    mkdir -p "$combined_dist/$legacy_subpath/$(dirname "$page")"
    printf '<!doctype html>\n<meta charset="utf-8">\n<title>Moved</title>\n<link rel="canonical" href="%s">\n<meta name="robots" content="noindex">\n<meta http-equiv="refresh" content="0; url=%s">\n<script>location.replace(%s + location.search + location.hash)</script>\n<p><a href="%s">This page has moved.</a></p>\n' \
      "$target" "$target" "'$target'" "$target" > "$combined_dist/$legacy_subpath/$page"
    stubs=$((stubs + 1))
  done < <(cd "$latest_tree/docs/.vitepress/dist" && find . -name '*.html' ! -name 404.html | sed 's|^\./||' | sort)
  echo "Redirect stubs under $legacy_subpath/: $stubs"
fi
echo "Combined docs: $combined_dist"
