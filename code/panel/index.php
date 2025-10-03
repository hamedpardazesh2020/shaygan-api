<?php

require '../src/ShygunWebServiceClient.php';

$client = new ShygunWebServiceClient('http://81.16.121.68:2030/api', array(
  'Server'           => 'Web-Service',
  'AllowRowSecurity' => false,
  'Level'            => 1,
  'DBUserName'       => 'websrv',
  'DBPassword'       => '***',      // پسورد رو اینجا بگذار
  'DataBaseName'     => 'cybazg09',
  'Language'         => 3,
  // اگر احراز هویت مرکزی/برنامه نیاز است:
  // 'AuthUser'      => '',
  // 'AuthPassword'  => '',
  // یا اگر ConnectionName دارید:
  // 'ConnectionName'=> 'SampleConnection',
));

/** 1) دریافت حساب‌ها (با اطلاعات اضافه) */
$res = $client->accountGet(array(
  'WithExtraFields' => 'true',
  'AccountNumber'   => array('From' => '212010035', 'To' => '212010035', 'In' => array()),
), 0, 50, null, null, /*api-version*/ true);

/** 2) مانده‌ی کالاها با فیلتر کد کالا و انبار */
$res2 = $client->itemGetRemain(array(
  'Date'               => array('From' => '', 'To' => '', 'In' => array()),
  'ItemCode'           => array('From' => '1001', 'To' => '1001', 'In' => array()),
  'StockGuID'          => array('From' => '', 'To' => '', 'In' => array()),
  'ShowItemsZeroRemain'=> array('From' => '', 'To' => '', 'In' => array()),
), 0, 100);

/** 3) مانده حساب (ClosingBalance) */
$res3 = $client->statementDetailGetClosingBalance(array(
  'Date'          => array('From' => '', 'To' => '', 'In' => array()),
  'AccountGuId'   => array('From' => '', 'To' => '', 'In' => array()),
  'AccountNumber' => array('From' => '11010001', 'To' => '11019999', 'In' => array()),
), 0, 0, 0, 0);

?>