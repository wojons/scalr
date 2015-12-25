invoke-webrequest -uri http://buildbot.scalr-labs.com/win/feature-SCALARIZR-1891-azure/x86_64/scalarizr_3.7.b8105.734db39-1.x86_64.exe -outfile scalarizr.exe
start-process -wait -nonewwindow scalarizr.exe /S
start-service scalarizr
