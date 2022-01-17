#!/bin/sh

sed --in-place --regexp-extended --expression='/symfony\/error-handler/b; /symfony\/phpunit-bridge/b; s/"(symfony\/.*)": ".*"/"\1": "'$VERSION'"/' composer.json
