@echo off
echo %date% %time% : Starting queue worker >> D:\inetpub\wwwroot\approvalform\queue_worker.log
cd /d D:\inetpub\wwwroot\approvalform
D:\php81\php.exe artisan queue:work --queue=approvalform-queue --tries=3 --timeout=60 >> D:\inetpub\wwwroot\approvalform\queue_worker.log 2>&1
echo %date% %time% : Queue worker stopped >> D:\inetpub\wwwroot\approvalform\queue_worker.log
