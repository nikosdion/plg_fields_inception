#!/usr/bin/env bash
rm -rf release
mkdir release
pushd `pwd`/plugins/fields/inception
zip -r ../../../release/plg_fields_inception.zip *
popd