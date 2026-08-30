#!/usr/bin/env bash
#
# Builds the distributable plugin zip.
#
# Produces two files in dist/:
#   - action-bar-for-hivepress.zip           (attach this to the GitHub release)
#   - action-bar-for-hivepress-<version>.zip (identical contents; version-tagged for your own tracking)
#
# Both unzip to a top-level "action-bar-for-hivepress/" folder, so WordPress installs
# the plugin into the correct directory with no folder-mismatch warnings, and the
# release asset keeps a fixed name so this link always serves the newest version:
#
#   https://github.com/irapidchris-del/action-bar-for-hivepress/releases/latest/download/action-bar-for-hivepress.zip
#
set -euo pipefail

SLUG="action-bar-for-hivepress"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAIN="$ROOT/$SLUG.php"

if [ ! -f "$MAIN" ]; then
	echo "Error: $MAIN not found." >&2
	exit 1
fi

# Read the version from the plugin header.
VERSION="$(grep -iE '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r[:space:]')"

if [ -z "$VERSION" ]; then
	echo "Error: could not read Version from the plugin header." >&2
	exit 1
fi

DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"

rm -rf "$STAGE"
mkdir -p "$STAGE"

# Files and directories that ship in the release (everything else is left out).
DIST_ITEMS=(
	"$SLUG.php"
	"uninstall.php"
	"readme.txt"
	"license.txt"
	"includes"
	"assets"
	"languages"
)

for item in "${DIST_ITEMS[@]}"; do
	if [ -e "$ROOT/$item" ]; then
		cp -R "$ROOT/$item" "$STAGE/"
	fi
done

# Strip editor/OS junk that may have crept into copied directories.
find "$STAGE" -name ".DS_Store" -delete
find "$STAGE" -name "Thumbs.db" -delete

# Release asset: fixed name, version-free top-level folder.
RELEASE_ZIP="$DIST/$SLUG.zip"
rm -f "$RELEASE_ZIP"
( cd "$DIST" && zip -rqX "$SLUG.zip" "$SLUG" )

# Version-tagged copy for internal tracking (same contents, same internal folder).
VERSIONED_ZIP="$DIST/$SLUG-$VERSION.zip"
rm -f "$VERSIONED_ZIP"
cp "$RELEASE_ZIP" "$VERSIONED_ZIP"

# Tidy the staging folder; keep only the zips.
rm -rf "$STAGE"

echo "Built $SLUG $VERSION"
echo "  Release asset (attach to the GitHub release): $RELEASE_ZIP"
echo "  Internal tracking copy:                       $VERSIONED_ZIP"
echo "  Both extract to a top-level '$SLUG/' folder."
