@echo off
echo Quick update: master branch

REM Ensure we're on master and update
git checkout master
git fetch origin
git pull origin master

echo.
echo ✓ Master branch updated!
timeout /t 2 /nobreak >nul
