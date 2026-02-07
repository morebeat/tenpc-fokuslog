#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage: scripts/repo-reinit.sh --target ../fokuslog-clean --remote git@github.com:ORG/NEW.git [options]

Copies the current FokusLog checkout into a sanitized directory without history,
removes sensitive files, initializes a fresh Git repository, and optionally
configures the new remote.

Options:
  --target PATH          Destination directory for the cleaned copy (required)
  --remote URL           Remote URL for the new repository (optional)
  --source PATH          Source checkout (default: current working directory)
  --branch NAME          Initial branch name (default: main)
  --no-commit            Skip creating the first commit
  --extra-exclude PATTERN  Additional rsync exclude (can be repeated)
  --force                 Overwrite existing target directory
  -h, --help             Show this help and exit
EOF
}

TARGET=""
REMOTE=""
SOURCE="$(pwd)"
BRANCH="main"
CREATE_COMMIT=1
FORCE=0
EXTRA_EXCLUDES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --target)
            TARGET="$2"; shift 2;;
        --remote)
            REMOTE="$2"; shift 2;;
        --source)
            SOURCE="$2"; shift 2;;
        --branch)
            BRANCH="$2"; shift 2;;
        --no-commit)
            CREATE_COMMIT=0; shift;;
        --extra-exclude)
            EXTRA_EXCLUDES+=("$2"); shift 2;;
        --force)
            FORCE=1; shift;;
        -h|--help)
            usage; exit 0;;
        *)
            echo "Unknown option: $1" >&2
            usage
            exit 1;;
    esac
done

if [[ -z "$TARGET" ]]; then
    echo "Error: --target is required" >&2
    usage
    exit 1
fi

if [[ -d "$TARGET" ]]; then
    if [[ $FORCE -eq 1 ]]; then
        rm -rf "$TARGET"
    else
        echo "Error: target directory exists (use --force to overwrite): $TARGET" >&2
        exit 1
    fi
fi

if ! command -v git >/dev/null 2>&1; then
    echo "Error: git not found" >&2
    exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
    echo "Error: rsync not found" >&2
    exit 1
fi

if [[ -d "$SOURCE/.git" ]]; then
    if [[ -n "$(git -C "$SOURCE" status --porcelain)" ]]; then
        read -r -p "Source repository has uncommitted changes. Continue? [y/N] " ans
        if [[ ! "$ans" =~ ^[Yy]$ ]]; then
            echo "Aborted"; exit 1
        fi
    fi
else
    echo "Warning: $SOURCE does not look like a Git repository" >&2
fi

EXCLUDES=(
    '--exclude=.git'
    '--exclude=.github/workflows/secrets-example.yml'
    '--exclude=logs/'
    '--exclude=backups/'
    '--exclude=cache/'
    '--exclude=*.log'
    '--exclude=*.bak'
    '--exclude=*.tmp'
    '--exclude=.env'
    '--exclude=.env.*'
)

for item in "${EXTRA_EXCLUDES[@]}"; do
    EXCLUDES+=("--exclude=$item")
fi

echo "[1/4] Copying sanitized files to $TARGET"
rsync -a --delete "${EXCLUDES[@]}" "$SOURCE/" "$TARGET/"

pushd "$TARGET" >/dev/null

find . -maxdepth 2 -type f \( -name '*.env' -o -name '*.pem' -o -name '*.key' \) -print -delete || true

rm -rf .git

git init --initial-branch="$BRANCH" >/dev/null

git status --short

if [[ $CREATE_COMMIT -eq 1 ]]; then
    git add .
    git commit -m "Initial sanitized import" >/dev/null
fi

if [[ -n "$REMOTE" ]]; then
    git remote add origin "$REMOTE"
    echo "Configured remote origin -> $REMOTE"
fi

echo "Sanitized repository created at $TARGET"

popd >/dev/null
