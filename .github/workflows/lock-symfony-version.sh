#!/bin/sh

sed --in-place --regexp-extended --expression='/symfony\/deprecation-contracts/b; /symfony\/error-handler/b; /symfony\/phpunit-bridge/b; s/"(symfony\/.*)": ".*"/"\1": "'$VERSION'"/' composer.json
