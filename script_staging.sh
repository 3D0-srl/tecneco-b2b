#!/bin/bash
echo "STAGING"
mkdir deploy
cp -R controllers deploy/
cp -R classes deploy/
scp -r deploy root@tecneco.3d0.it:/home/b2bbe/public_html/modules/b2b
rm -r -f deploy