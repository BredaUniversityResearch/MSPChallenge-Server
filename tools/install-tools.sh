#!/bin/bash
function download() {
  curl -OLs https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
  curl -OLs https://phpmd.org/static/latest/phpmd.phar
  curl -OLs https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar
}

function makeExecutable() {
  cat > "$1" <<- CONTENT
#!/bin/bash
SCRIPT_DIR="\$( cd -- "\$( dirname -- "\${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
php "\${SCRIPT_DIR}/$1.phar" "\$@"
CONTENT
}

function makeExecutables() {
  makeExecutable phpcs
  makeExecutable phpcbf
  makeExecutable phpmd
}

download
makeExecutables