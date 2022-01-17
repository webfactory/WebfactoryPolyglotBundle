#!/bin/sh

sed --in-place --regexp-extended --expression='/symfony\/error-handler/! s/"(symfony\/.*)": ".*"/"\1": "'$VERSION'"/' composer.json
