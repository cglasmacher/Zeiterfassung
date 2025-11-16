@echo off
echo Fixing Database Issues...
echo.

echo Step 1: Checking database columns...
php artisan db:check-columns
echo.

echo Step 2: Running migrations...
php artisan migrate
if %errorlevel% neq 0 (
    echo Error running migrations
    pause
    exit /b 1
)
echo.

echo Step 3: Recalculating wages for all entries...
php artisan wages:recalculate
if %errorlevel% neq 0 (
    echo Note: wages:recalculate command may not exist yet
)
echo.

echo Done! Please check the output above for any errors.
echo.
pause