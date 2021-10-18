#!/bin/bash
echo "PRODUCTION"
rm -r -f deploy
mkdir deploy
cp -R controllers deploy/
cp -R classes deploy/
cp -R templates_twig deploy/
scp -r deploy/* root@tecneco.3d0.it:/home/catalogotecneco/public_html/modules/b2b
rm -r -f deploy