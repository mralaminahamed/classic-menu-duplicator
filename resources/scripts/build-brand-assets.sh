#!/usr/bin/env bash
#
# Build the Swift Menu Duplicator WordPress.org brand assets.
#
# Rasterises the SVG masters in .wordpress-org/ into the exact PNG dimensions
# required by the WordPress.org plugin directory:
#
#   icon-128x128.png     (128×128)
#   icon-256x256.png     (256×256)
#   banner-772x250.png   (772×250)
#   banner-1544x500.png  (1544×500)
#
# Rasteriser: prefers `rsvg-convert` (librsvg) for speed and accuracy, falls
# back to `inkscape` if present. Optionally compresses outputs with `oxipng`
# when available.
#
# Usage:
#   bash bin/build-brand-assets.sh                 # build into .wordpress-org/
#   bash bin/build-brand-assets.sh --output DIR    # build into a different dir
#   bash bin/build-brand-assets.sh --check         # validate inputs only
#   bash bin/build-brand-assets.sh --help
#
# Exit codes:
#   0  success
#   1  unrecoverable error (missing tool, missing source, dimension mismatch)
#   2  CLI usage error
#
# Author: Al Amin Ahamed <mrabir.ahamed@gmail.com>
# License: GPL-2.0-or-later

set -o errexit
set -o nounset
set -o pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Paths
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$( cd -- "${SCRIPT_DIR}/.." &> /dev/null && pwd )"
ASSETS_DIR="${PROJECT_ROOT}/.wordpress-org"
OUTPUT_DIR="${ASSETS_DIR}"
CHECK_ONLY=0
SIZE_WARN_BYTES=$(( 250 * 1024 ))   # warn above 250 KB per asset

# Source SVG → output PNG mapping. Format: "source_svg|output_png|width|height"
ASSET_MAP=(
	"icon.svg|icon-256x256.png|256|256"
	"icon.svg|icon-128x128.png|128|128"
	"banner-1544x500.draft.svg|banner-1544x500.png|1544|500"
	"banner-772x250.draft.svg|banner-772x250.png|772|250"
)

# ─────────────────────────────────────────────────────────────────────────────
# Pretty output helpers (TTY-aware)
# ─────────────────────────────────────────────────────────────────────────────

if [ -t 1 ]; then
	C_RESET=$'\033[0m'
	C_BOLD=$'\033[1m'
	C_DIM=$'\033[2m'
	C_RED=$'\033[31m'
	C_GREEN=$'\033[32m'
	C_YELLOW=$'\033[33m'
	C_BLUE=$'\033[34m'
	C_CYAN=$'\033[36m'
else
	C_RESET=""; C_BOLD=""; C_DIM=""
	C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_CYAN=""
fi

log_info()  { printf '%s[info]%s    %s\n'  "${C_BLUE}"   "${C_RESET}" "$*"; }
log_step()  { printf '%s[step]%s    %s\n'  "${C_CYAN}"   "${C_RESET}" "$*"; }
log_ok()    { printf '%s[ok]%s      %s\n'  "${C_GREEN}"  "${C_RESET}" "$*"; }
log_warn()  { printf '%s[warn]%s    %s\n'  "${C_YELLOW}" "${C_RESET}" "$*" >&2; }
log_error() { printf '%s[error]%s   %s\n'  "${C_RED}"    "${C_RESET}" "$*" >&2; }
log_dim()   { printf '%s%s%s\n'            "${C_DIM}"    "$*"          "${C_RESET}"; }

# ─────────────────────────────────────────────────────────────────────────────
# CLI parsing
# ─────────────────────────────────────────────────────────────────────────────

print_usage() {
	cat <<-EOF
		Usage: bash bin/build-brand-assets.sh [options]

		Options:
		  --output DIR    Write PNGs into DIR instead of .wordpress-org/
		  --check         Validate the SVG inputs and tooling, do not rasterise
		  -h, --help      Show this help and exit
	EOF
}

while [ $# -gt 0 ]; do
	case "$1" in
		--output)
			[ $# -ge 2 ] || { log_error "--output requires a directory argument"; exit 2; }
			OUTPUT_DIR="$2"
			shift 2
			;;
		--check)
			CHECK_ONLY=1
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
# Tool detection
# ─────────────────────────────────────────────────────────────────────────────

detect_rasteriser() {
	if command -v rsvg-convert >/dev/null 2>&1; then
		echo "rsvg-convert"
		return 0
	fi
	if command -v inkscape >/dev/null 2>&1; then
		echo "inkscape"
		return 0
	fi
	return 1
}

detect_optimiser() {
	if command -v oxipng >/dev/null 2>&1; then
		echo "oxipng"
		return 0
	fi
	return 1
}

detect_dimension_checker() {
	if command -v identify >/dev/null 2>&1; then
		echo "identify"
		return 0
	fi
	if command -v file >/dev/null 2>&1; then
		echo "file"
		return 0
	fi
	return 1
}

# ─────────────────────────────────────────────────────────────────────────────
# Rasterisation
# ─────────────────────────────────────────────────────────────────────────────

rasterise() {
	local tool="$1" source="$2" output="$3" width="$4" height="$5"

	case "$tool" in
		rsvg-convert)
			rsvg-convert \
				--width="${width}" \
				--height="${height}" \
				--format=png \
				--keep-aspect-ratio \
				--background-color=none \
				--output="${output}" \
				"${source}"
			;;
		inkscape)
			inkscape \
				--export-type=png \
				--export-filename="${output}" \
				--export-width="${width}" \
				--export-height="${height}" \
				--export-background-opacity=0 \
				"${source}" >/dev/null 2>&1
			;;
		*)
			log_error "Unknown rasteriser: ${tool}"
			return 1
			;;
	esac
}

# ─────────────────────────────────────────────────────────────────────────────
# Output validation
# ─────────────────────────────────────────────────────────────────────────────

read_dimensions() {
	local tool="$1" path="$2"

	case "$tool" in
		identify)
			identify -format '%w %h' "${path}"
			;;
		file)
			# file reports e.g. "PNG image data, 256 x 256, ..."
			file "${path}" | sed -nE 's/.*PNG image data, ([0-9]+) x ([0-9]+).*/\1 \2/p'
			;;
		*)
			echo ""
			;;
	esac
}

verify_dimensions() {
	local checker="$1" output="$2" expected_w="$3" expected_h="$4"

	if [ -z "${checker}" ]; then
		log_warn "No dimension checker available; skipping size validation for $(basename "${output}")"
		return 0
	fi

	local actual
	actual="$( read_dimensions "${checker}" "${output}" )"
	if [ -z "${actual}" ]; then
		log_warn "Could not read dimensions for $(basename "${output}")"
		return 0
	fi

	local actual_w="${actual%% *}"
	local actual_h="${actual##* }"
	if [ "${actual_w}" != "${expected_w}" ] || [ "${actual_h}" != "${expected_h}" ]; then
		log_error "Dimension mismatch for $(basename "${output}"): expected ${expected_w}x${expected_h}, got ${actual_w}x${actual_h}"
		return 1
	fi
}

human_size() {
	local bytes="$1"
	if [ "${bytes}" -lt 1024 ]; then
		printf '%d B' "${bytes}"
	elif [ "${bytes}" -lt $(( 1024 * 1024 )) ]; then
		printf '%.1f KB' "$( awk -v b="${bytes}" 'BEGIN{printf "%.1f", b/1024}' )"
	else
		printf '%.1f MB' "$( awk -v b="${bytes}" 'BEGIN{printf "%.1f", b/1024/1024}' )"
	fi
}

file_size_bytes() {
	# macOS stat vs GNU stat have different flag syntax — try both.
	if stat -f '%z' "$1" >/dev/null 2>&1; then
		stat -f '%z' "$1"
	else
		stat -c '%s' "$1"
	fi
}

# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────

main() {
	printf '\n%s%sSwift Menu Duplicator — brand asset build%s\n' "${C_BOLD}" "${C_CYAN}" "${C_RESET}"
	log_dim "  project root: ${PROJECT_ROOT}"
	log_dim "  assets dir:   ${ASSETS_DIR}"
	log_dim "  output dir:   ${OUTPUT_DIR}"
	printf '\n'

	# Detect tooling
	local rasteriser
	if ! rasteriser="$( detect_rasteriser )"; then
		log_error "No SVG rasteriser found."
		log_error "Install one of the following:"
		log_error "  macOS:  brew install librsvg"
		log_error "  Linux:  sudo apt-get install librsvg2-bin   # or: inkscape"
		exit 1
	fi
	log_ok "rasteriser: ${rasteriser}"

	local optimiser=""
	if optimiser="$( detect_optimiser )"; then
		log_ok "optimiser:  ${optimiser}"
	else
		log_warn "optimiser:  none (install 'oxipng' for ~50% smaller PNGs)"
	fi

	local checker=""
	checker="$( detect_dimension_checker || true )"
	if [ -n "${checker}" ]; then
		log_ok "validator:  ${checker}"
	else
		log_warn "validator:  none (install ImageMagick for dimension checks)"
	fi

	# Validate inputs
	printf '\n'
	log_step "validating SVG masters"
	local entry source_name source_path
	for entry in "${ASSET_MAP[@]}"; do
		source_name="${entry%%|*}"
		source_path="${ASSETS_DIR}/${source_name}"
		if [ ! -f "${source_path}" ]; then
			log_error "missing source: ${source_path}"
			exit 1
		fi
		log_dim "  found ${source_name}"
	done

	if [ "${CHECK_ONLY}" -eq 1 ]; then
		printf '\n'
		log_ok "check-only mode: all preconditions satisfied"
		exit 0
	fi

	mkdir -p "${OUTPUT_DIR}"

	# Build each asset
	printf '\n'
	log_step "rasterising"

	local output_name width height output_path size_bytes failures=0
	for entry in "${ASSET_MAP[@]}"; do
		source_name="${entry%%|*}"
		entry="${entry#*|}"
		output_name="${entry%%|*}"
		entry="${entry#*|}"
		width="${entry%%|*}"
		height="${entry#*|}"

		source_path="${ASSETS_DIR}/${source_name}"
		output_path="${OUTPUT_DIR}/${output_name}"

		printf '  %s%s%s ← %s @ %sx%s ... ' \
			"${C_BOLD}" "${output_name}" "${C_RESET}" \
			"${source_name}" "${width}" "${height}"

		if ! rasterise "${rasteriser}" "${source_path}" "${output_path}" "${width}" "${height}"; then
			printf '%sfailed%s\n' "${C_RED}" "${C_RESET}"
			failures=$(( failures + 1 ))
			continue
		fi

		if ! verify_dimensions "${checker}" "${output_path}" "${width}" "${height}"; then
			printf '%sdimension mismatch%s\n' "${C_RED}" "${C_RESET}"
			failures=$(( failures + 1 ))
			continue
		fi

		if [ -n "${optimiser}" ]; then
			oxipng -o 4 --strip safe --quiet "${output_path}"
		fi

		size_bytes="$( file_size_bytes "${output_path}" )"
		printf '%sok%s  (%s)\n' "${C_GREEN}" "${C_RESET}" "$( human_size "${size_bytes}" )"

		if [ "${size_bytes}" -gt "${SIZE_WARN_BYTES}" ]; then
			log_warn "  ${output_name} is $( human_size "${size_bytes}" ) — above the 250 KB soft cap"
		fi
	done

	printf '\n'
	if [ "${failures}" -gt 0 ]; then
		log_error "build failed: ${failures} asset(s) did not produce a valid output"
		exit 1
	fi

	log_ok "all brand assets built successfully"
	printf '\n'
}

main "$@"
