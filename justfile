set shell := ["bash", "-euo", "pipefail", "-c"]

php := "8.2"
image := "mktr-tooling:" + php
docker_run := "docker run --rm --user $(id -u):$(id -g) -v \"$PWD\":/app -w /app " + image

_help:
    @echo "Available commands:"
    @echo "  just lint          - check the PrestaShop coding standard and index.php guards"
    @echo "  just fix           - apply the PrestaShop coding standard"
    @echo "  just package       - build dist/mktr.zip for the Addons validator"
    @echo "  just version       - print the module version"
    @echo "  just bump 1.1.9    - set the module version in config.xml and mktr.php"
    @echo ""
    @just --list

# Build the tooling image (PHP CLI, composer, PrestaShop coding standards).
tooling:
    docker build --target tooling --build-arg PHP_VERSION={{php}} -t {{image}} .

# Check the PrestaShop coding standard and the index.php guard in every folder.
lint: tooling index-check
    {{docker_run}} php-cs-fixer fix --dry-run --diff

# Apply the PrestaShop coding standard.
fix: tooling
    {{docker_run}} php-cs-fixer fix

# The Addons validator requires an index.php in every folder, including .github/
# when it validates the repository straight from GitHub. vendor/ is not checked.
index-check:
    #!/usr/bin/env bash
    set -euo pipefail
    missing=$(git ls-files | grep -v '^vendor/' | awk -F/ '{ path = ""; for (i = 1; i < NF; i++) { path = path (i > 1 ? "/" : "") $i; print path } }' | sort -u | while read -r dir; do
        git ls-files --error-unmatch "$dir/index.php" > /dev/null 2>&1 || echo "$dir"
    done)
    if [ -n "$missing" ]; then
        printf 'Folders without index.php:\n%s\n' "$missing" >&2
        exit 1
    fi

# Build dist/mktr.zip: lints PHP, checks versions and structure, applies .dockerignore.
package:
    rm -rf dist
    docker build --target artifact --build-arg PHP_VERSION={{php}} --output type=local,dest=dist .
    unzip -l dist/mktr.zip | tail -1

# Print the module version (config.xml).
version:
    @sed -n 's/.*<version><!\[CDATA\[\(.*\)\]\]><\/version>.*/\1/p' config.xml

# Set the module version in config.xml and mktr.php.
bump new_version:
    #!/usr/bin/env bash
    set -euo pipefail
    if ! [[ "{{new_version}}" =~ ^[0-9]+(\.[0-9]+){2,3}$ ]]; then
        echo "Invalid version '{{new_version}}', expected e.g. 1.1.9" >&2
        exit 1
    fi
    sed -i.bak -E 's#(<version><!\[CDATA\[)[^]]*(\]\]></version>)#\1{{new_version}}\2#' config.xml
    sed -i.bak -E "s#(\\\$this->version = ')[^']*(';)#\1{{new_version}}\2#" mktr.php
    rm -f config.xml.bak mktr.php.bak
    grep -n '<version>' config.xml
    grep -n "this->version = " mktr.php

# Remove build output.
clean:
    rm -rf dist
