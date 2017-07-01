#!/bin/sh
set +e
cd $(dirname $0)/..
echo $(pwd)

protoc --proto_path=grpc/proto \
       --php_out=grpc \
       --grpc_out=grpc \
       --plugin=protoc-gen-grpc=bin/grpc_php_plugin \
       grpc/proto/demo.proto
