<?php return array (
  'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => 
  array (
    'App\\Events\\PurchaseReceived' => 
    array (
      0 => 'App\\Listeners\\RecordPurchaseAuditLog@handle',
    ),
    'App\\Events\\SaleCompleted' => 
    array (
      0 => 'App\\Listeners\\RecordSaleAuditLog@handle',
    ),
    'App\\Events\\StockLow' => 
    array (
      0 => 'App\\Listeners\\SendLowStockAlert@handle',
    ),
  ),
);