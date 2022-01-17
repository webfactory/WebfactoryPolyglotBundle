#!/bin/sh

sed --in-place --regexp-extended --expression='/symfony\/phpunit-bridge/! /symfony\/error-handler/! s/"(symfony\/.*)": ".*"/"\1": "'$VERSION'"/' composer.json
