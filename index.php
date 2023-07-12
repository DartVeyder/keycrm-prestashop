<?php
    require_once('vendor/autoload.php');
    require_once('config.php');
    require_once('class/stock_synchronizer.php');
 
    new StockSynchronizer(KEYCRM_TOKEN, KEYCRM_URL_API);
    

?>