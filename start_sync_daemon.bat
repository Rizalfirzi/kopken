@echo off
title Kedai Kyuti - Kopi Kenangan Realtime MongoDB Sync (Every 5 Mins)
echo ========================================================
echo   Kedai Kyuti - Realtime Sync Service to MongoDB Atlas
echo ========================================================
echo.
echo Upstream Sources:
echo  1. tixandcoffee.developerngantuk.my.id
echo  2. ridstore.developerngantuk.my.id
echo  3. tyaastore.my.id
echo  4. twinsjasdor.my.id
echo.
echo Database: MongoDB Atlas (kyuti)
echo Interval: 300 seconds (5 minutes)
echo.
echo Tekan Ctrl+C untuk menghentikan sinkronisasi.
echo.

python scripts\sync_mongodb.py --daemon --interval 300

pause

