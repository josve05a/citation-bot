#!/bin/sh

set -e

[ -z "${GITHUB_PAT}" ] && exit 0
git config --global user.email "martins@gmail.com"
git config --global user.name "Martin Smith"
git clone -b file-edits https://${GITHUB_PAT}@github.com/${TRAVIS_REPO_SLUG}.git file-maintenance
cd file-maintenance
php maintain-files.php
git add --all *
git commit -m"Automated file maintenance" || true
git push -q origin file-edits