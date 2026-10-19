# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-cli-alpine AS tooling

RUN apk add --no-cache git zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tools \
    PATH="/tools/vendor/bin:$PATH"

RUN composer global require --no-interaction --no-progress \
        prestashop/php-dev-tools:^5 \
        friendsofphp/php-cs-fixer:^3.95 \
 && composer clear-cache

WORKDIR /app


FROM tooling AS package

WORKDIR /build

COPY . mktr/

RUN <<'EOF'
set -eu

xml_version=$(sed -n 's/.*<version><!\[CDATA\[\(.*\)\]\]><\/version>.*/\1/p' mktr/config.xml)
php_version=$(sed -n "s/.*\$this->version = '\(.*\)';.*/\1/p" mktr/mktr.php)
if [ -z "$xml_version" ] || [ "$xml_version" != "$php_version" ]; then
    echo "Version mismatch: config.xml=$xml_version mktr.php=$php_version" >&2
    exit 1
fi

for required in mktr.php config.xml logo.png index.php; do
    [ -f "mktr/$required" ] || { echo "Missing mktr/$required" >&2; exit 1; }
done

find mktr -name '*.php' -not -path 'mktr/vendor/*' | while read -r file; do
    php -l "$file" > /dev/null || { php -l "$file" >&2; exit 1; }
done

# The Addons validator rejects folders without an index.php guard and hidden
# development files; vendor/ is not checked by the validator.
missing=$(find mktr -path mktr/vendor -prune -o -type d -print | while read -r dir; do
    [ -f "$dir/index.php" ] || echo "$dir"
done)
[ -z "$missing" ] || { printf 'Folders without index.php:\n%s\n' "$missing" >&2; exit 1; }

leaked=$(find mktr -path mktr/vendor -prune -o -name '.*' ! -name '.htaccess' -print)
[ -z "$leaked" ] || { printf 'Development files in the package (add them to .dockerignore):\n%s\n' "$leaked" >&2; exit 1; }

mkdir -p /out/package
zip -q -r -X /out/mktr.zip mktr
cp -a mktr /out/package/
echo "$xml_version" > /out/VERSION
EOF


FROM scratch AS artifact

COPY --from=package /out/ /
