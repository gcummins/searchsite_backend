@echo off

rem    Batch script for thumnails generation
rem    using nconvert (included with XnView:
rem    www.xnview.com) - By Sylvain Pajot


rem path to nconvert (no final '\')
set path=c:\progra~1\xnview

rem thumbnails height
set height=100

rem thumbnails quality (1-100)
set quality=100

rem thumbnails format (jpeg/gif/png)
set format=jpeg

rem temporary directory
set temp_dir=temp_thb



echo Thumnails generation in progress...

rem create temporary directory...
md %temp_dir%

rem a little tweak
set filter=%format%
if "%filter%"=="jpeg" set filter=jpg

rem ... fill it with thumnails
for %%f in (*.%filter%) do (
  echo Generating thumbnail for %%f
  %path%\nconvert -quiet -out %format% -ratio -resize 0 %height% -q %quality% -o "%temp_dir%\_thb_%%f" "%%f"
)

echo Cleaning up...
rem move thumbnails and remove temporary directory
move %temp_dir%\* . 2> nul
rmdir %temp_dir%

echo Thumbnails have been generated !

