@echo off
echo Master Branch Management Tool
echo.
echo Note: Main branch has been removed. Only master branch exists now.
echo This script now serves as a master branch maintenance tool.
echo.

REM Check if we're in a git repository
git rev-parse --git-dir >nul 2>&1
if errorlevel 1 (
    echo Error: Not a git repository
    pause
    exit /b 1
)

REM Fetch latest changes
echo Fetching latest changes from remote...
git fetch origin

REM Switch to master branch
echo Ensuring we're on master branch...
git checkout master
if errorlevel 1 (
    echo Error: Could not switch to master branch
    pause
    exit /b 1
)

REM Pull latest master changes
echo Pulling latest master changes...
git pull origin master

echo.
echo ✓ Master branch is up to date!
echo ✓ You are now on the master branch
echo.
echo Current status:
git status --short

echo.
pause
