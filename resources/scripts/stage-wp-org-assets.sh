#!/usr/bin/env bash
#
# Stage a clean WordPress.org SVN `assets/` payload.
#
# Copies the production-ready files from .wordpress-org/ into a clean
# staging directory, deliberately excluding working files that must never
# reach the WordPress.org plugin directory:
#
#   - *.draft.svg        SVG concept drafts (kept in git for designers, not for SVN)
#   - BRAND.md           Internal design system documentation
#   - README.md          Any internal README inside .wordpress-org/
#   - .gitkeep, .DS_Store, Thumbs.db   OS / VCS noise
#
# Both `svn-readme-assets-update.yml` and `svn-deploy.yml` invoke this
# script and then point the 10up SVN actions at the staged directory via
# the ASSETS_DIR environment variable.
#
# Usage:
#   bash resources/scripts/stage-wp-org-assets.sh                  # stage into ./.wp-org-staged
#   bash resources/scripts/stage-wp-org-assets.sh --output DIR     # custom staging dir
#   bash resources/scripts/stage-wp-org-assets.sh --list           # show what would be copied
#   bash resources/scripts/stage-wp-org-assets.sh --help
#
# Author: Al Amin Ahamed <mrabir.ahamed@gmail.com>
# License: GPL-2.0-or-later

set -o errexit
set -o nounset
set -o pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Paths and defaults
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
# Two levels up: this script lives in resources/scripts/.
PROJECT_ROOT="$( cd -- "${SCRIPT_DIR}/../.." &> /dev/null && pwd )"
SOURCE_DIR="${PROJECT_ROOT}/.wordpress-org"
OUTPUT_DIR="${PROJECT_ROOT}/.wp-org-staged"
LIST_ONLY=0

# Exclusion list — anything matching ANY of these glob patterns is skipped.
# Patterns are matched against the basename of each candidate file.
EXCLUDE_PATTERNS=(
	'*.draft.svg'
	'*.draft.*'
	'BRAND.md'
	'README.md'
	'.gitkeep'
	'.DS_Store'
	'._*'
	'Thumbs.db'
	'.svn'
	'.git'
)

# Inclusion list — only files matching one of these patterns are eligible
# even if they pass the exclusion check. This is a defence-in-depth filter
# so that an accidental .pdf or .key file dropped into .wordpress-org/ does
# not silently ship to the WordPress.org SVN repo.
INCLUDE_PATTERNS=(
	'icon.svg'
	'icon-*.png'
	'icon-*.svg'
	'banner-*.png'
	'banner-*.svg'
	'screenshot-*.png'
	'screenshot-*.jpg'
)

# ─────────────────────────────────────────────────────────────────────────────
# Pretty output (TTY-aware)
# ─────────────────────────────────────────────────────────────────────────────

if [ -t 1 ]; then
	C_RESET=$'\033[0m'; C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'
	C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'
	C_BLUE=$'\033[34m'; C_CYAN=$'\033[36m'
else
	C_RESET=""; C_BOLD=""; C_DIM=""; C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_CYAN=""
fi

log_info()  { printf '%s[info]%s    %s\n'  "${C_BLUE}"   "${C_RESET}" "$*"; }
log_ok()    { printf '%s[ok]%s      %s\n'  "${C_GREEN}"  "${C_RESET}" "$*"; }
log_skip()  { printf '%s[skip]%s    %s\n'  "${C_DIM}"    "${C_RESET}" "$*"; }
log_warn()  { printf '%s[warn]%s    %s\n'  "${C_YELLOW}" "${C_RESET}" "$*" >&2; }
log_error() { printf '%s[error]%s   %s\n'  "${C_RED}"    "${C_RESET}" "$*" >&2; }

# ─────────────────────────────────────────────────────────────────────────────
# CLI parsing
# ─────────────────────────────────────────────────────────────────────────────

print_usage() {
	cat <<-EOF
		Usage: bash bin/stage-wp-org-assets.sh [options]

		Stage WordPress.org assets into a clean directory, excluding working files.

		Options:
		  --output DIR    Stage into DIR (default: .wp-org-staged)
		  --list          Show what would be copied without writing anything
		  -h, --help      Show this help
	EOF
}

while [ $# -gt 0 ]; do
	case "$1" in
		--output)
			[ $# -ge 2 ] || { log_error "--output requires a directory argument"; exit 2; }
			OUTPUT_DIR="$2"
			shift 2
			;;
		--list)
			LIST_ONLY=1
			shift
			;;
		-h|--help)
			print_usage
			exit 0
			;;
		*)
			log_error "Unknown argument: $1"
			print_usage >&2
			exit 2
			;;
	esac
done

# ─────────────────────────────────────────────────────────────────────────────
# Matchers
# ─────────────────────────────────────────────────────────────────────────────

matches_any() {
	local name="$1"
	shift
	local pattern
	for pattern in "$@"; do
		# shellcheck disable=SC2053
		if [[ "${name}" == ${pattern} ]]; then
			return 0
		fi
	done
	return 1
}

# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────

main() {
	printf '\n%s%sSwift Menu Duplicator — WordPress.org asset staging%s\n' \
		"${C_BOLD}" "${C_CYAN}" "${C_RESET}"
	printf '%s  source:  %s%s\n' "${C_DIM}" "${SOURCE_DIR}" "${C_RESET}"
	printf '%s  output:  %s%s\n' "${C_DIM}" "${OUTPUT_DIR}" "${C_RESET}"
	printf '\n'

	if [ ! -d "${SOURCE_DIR}" ]; then
		log_error "source directory does not exist: ${SOURCE_DIR}"
		exit 1
	fi

	if [ "${LIST_ONLY}" -eq 0 ]; then
		# Recreate the output directory cleanly so stale files from a previous
		# run never leak into the SVN upload.
		rm -rf "${OUTPUT_DIR}"
		mkdir -p "${OUTPUT_DIR}"
	fi

	local copied=0 skipped=0
	local entry name

	# Iterate top-level files only — .wordpress-org/ is intentionally flat per
	# the WordPress.org plugin directory expectations.
	while IFS= read -r -d '' entry; do
		name="$( basename "${entry}" )"

		if matches_any "${name}" "${EXCLUDE_PATTERNS[@]}"; then
			log_skip "${name}  (excluded)"
			skipped=$(( skipped + 1 ))
			continue
		fi

		if ! matches_any "${name}" "${INCLUDE_PATTERNS[@]}"; then
			log_skip "${name}  (not on include list)"
			skipped=$(( skipped + 1 ))
			continue
		fi

		if [ "${LIST_ONLY}" -eq 1 ]; then
			printf '  %swould copy%s  %s\n' "${C_GREEN}" "${C_RESET}" "${name}"
		else
			cp -p "${entry}" "${OUTPUT_DIR}/${name}"
			printf '  %scopied%s     %s\n' "${C_GREEN}" "${C_RESET}" "${name}"
		fi
		copied=$(( copied + 1 ))
	done < <( find "${SOURCE_DIR}" -maxdepth 1 -type f -print0 | LC_ALL=C sort -z )

	# WordPress.org reads assets/blueprints/blueprint.json to offer the "Live
	# Preview" button. It lives in a subdirectory, so the flat sweep above
	# never sees it — and because the SVN assets folder is mirrored rather
	# than merged, leaving it out would delete the blueprint from the
	# directory listing.
	if [ -f "${SOURCE_DIR}/blueprints/blueprint.json" ]; then
		if [ "${LIST_ONLY}" -eq 1 ]; then
			printf '  would copy  %s\n' 'blueprints/blueprint.json'
		else
			mkdir -p "${OUTPUT_DIR}/blueprints"
			cp -p "${SOURCE_DIR}/blueprints/blueprint.json" "${OUTPUT_DIR}/blueprints/blueprint.json"
		fi

		copied=$(( copied + 1 ))
	fi

	printf '\n'

	if [ "${copied}" -eq 0 ]; then
		log_error "no production assets found — refusing to publish an empty asset folder"
		exit 1
	fi

	if [ "${LIST_ONLY}" -eq 1 ]; then
		log_info "dry run complete: ${copied} file(s) eligible, ${skipped} skipped"
	else
		log_ok "staged ${copied} file(s) into ${OUTPUT_DIR} (${skipped} skipped)"
	fi
}

main "$@"
