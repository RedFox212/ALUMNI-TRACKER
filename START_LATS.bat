@echo off
setlocal
title LATS - Lyceum Alumni Tracking System

echo.
echo  ======================================================
echo    LYCEUM ALUMNI TRACKING SYSTEM (LATS) - PORTAL
echo  ======================================================
echo.
echo  Checking if XAMPP is active...
echo.
echo  [IMPORTANT] Make sure Apache and MySQL are STARTED 
echo  in your XAMPP Control Panel.
echo.
echo  Link: http://localhost/ALUMNI
echo.
echo  Launching portal in your default browser...
echo.

:: Open default browser
start http://localhost/ALUMNI

echo.
echo  Portal Launched!
echo.
echo  - To exit this window, press any key.
echo  - To keep the portal running, keep XAMPP active.
echo.
echo  ======================================================
pause > nul
exit
