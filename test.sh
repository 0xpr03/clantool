#!/bin/bash
set -e

cargo build --verbose

mysql -e 'DROP DATABASE IF EXISTS test;'
mysql -e 'CREATE DATABASE test;'
RUST_BACKTRACE=1 cargo test -- --test-threads=1
mysql -e 'DROP DATABASE IF EXISTS test;'
mysql -e 'CREATE DATABASE test;'

# init config
cargo run --bin clantool -- --help || true
sed -i -e 's/"user"/"root"/g' config/config.toml
sed -i -e 's/password = "password"//g' config/config.toml
sed -i -e 's/"clantool"/"test"/g' config/config.toml
cat config/config.toml

# init db & run crawl
RUST_BACKTRACE=1 cargo run --bin clantool -- init
# fails in CI
#RUST_BACKTRACE=1 cargo run --bin clantool -- fcrawl || true
mysql -e 'DROP DATABASE test;'
