# shellcheck shell=bash
# Sourced by bin/php and bin/composer. image_for <php version> prints the development image tag, building it once:
# indexnowkit-php:<version>-<hash of docker/php/Dockerfile>, so editing the Dockerfile rebuilds and nothing else does.
image_for() {
    local ver="$1" dir="$ROOT/docker/php" hash tag
    hash="$(shasum -a 256 "$dir/Dockerfile" | cut -c1-12)"
    tag="indexnowkit-php:${ver}-${hash}"
    if ! docker image inspect "$tag" >/dev/null 2>&1; then
        echo "building ${tag} from docker/php/Dockerfile" >&2
        docker build --quiet --build-arg "PHP_VERSION=${ver}" --tag "$tag" "$dir" >/dev/null
    fi
    printf '%s' "$tag"
}
