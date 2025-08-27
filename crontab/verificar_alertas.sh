#### /bin/bash
/usr/bin/php /var/www/html/contratos-app/api/alertas/verificar_saldos.php
```


#### 📅 Agendamento com cron
```cron
0 8 * * * /bin/bash /var/www/html/contratos-app/crontab/verificar_saldos.sh >> /var/log/contratos_saldos.log 2>&1
