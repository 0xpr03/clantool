#!/bin/bash
set -e
set -x

printenv -0
cargo build --verbose

mysql -h $MYSQL_HOST -u $USER -p$MYSQL_PWD -e 'DROP DATABASE IF EXISTS test;'
mysql -h $MYSQL_HOST -u $USER -p$MYSQL_PWD -e 'CREATE DATABASE test;'
RUST_BACKTRACE=1 cargo test -- --test-threads=1
mysql -h $MYSQL_HOST -u $USER -p$MYSQL_PWD -e 'DROP DATABASE IF EXISTS test;'
mysql -h $MYSQL_HOST -u $USER -p$MYSQL_PWD -e 'CREATE DATABASE test;'

# init config
cargo run --bin clantool -- --help || true
sed -i -e 's/"user"/"root"/g' config/config.toml
sed -i -e 's/password = "password"/password = "root"/g' config/config.toml
sed -i -e 's/"clantool"/"test"/g' config/config.toml
cat config/config.toml

# init db & run crawl
RUST_BACKTRACE=1 cargo run --bin clantool -- init
# fails in CI
#RUST_BACKTRACE=1 cargo run --bin clantool -- fcrawl || true
mysql -h $MYSQL_HOST -u $USER -p$MYSQL_PWD -e 'DROP DATABASE test;'
