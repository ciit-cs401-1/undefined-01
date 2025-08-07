@echo off
echo ==========================================
echo    Development Environment Manager
echo ==========================================
echo.
echo Please choose an option:
echo 1. Start development servers (Laravel + Vite)
echo 2. Update master branch
echo 3. Update master + start development servers
echo 4. Exit
echo.
set /p choice="Enter your choice (1-4): "

if "%choice%"=="1" goto start_dev
if "%choice%"=="2" goto update_only
if "%choice%"=="3" goto update_and_start
if "%choice%"=="4" goto exit
echo Invalid choice. Please try again.
goto menu

:update_only
echo.
echo ==========================================
echo    Updating master branch
echo ==========================================
call master-update.bat
goto end

:update_and_start
echo.
echo ==========================================
echo    Updating master branch first...
echo ==========================================
call master-update.bat
echo.
echo ==========================================
echo    Now starting development servers...
echo ==========================================
goto start_dev

:start_dev
echo.
echo ==========================================
echo    Starting Local Development Servers
echo ==========================================

echo.
echo Starting Laravel development server...
start "Laravel Server" cmd /k "php artisan serve"

echo.
echo Waiting 3 seconds for Laravel server to start...
timeout /t 3 /nobreak >nul

echo.
echo Starting Vite development server...
start "Vite Server" cmd /k "npm run dev"

echo.
echo ==========================================
echo    Development Servers Started!
echo ==========================================
echo.
echo Your application is now running:
echo   - Backend API: http://localhost:8000
echo   - Frontend: http://localhost:5173
echo.
echo Press any key to continue or close this window...
pause >nul
goto end

:exit
echo Goodbye!
goto end

:end
