<?php
date_default_timezone_set('America/Argentina/Cordoba');
require '/vendor/autoload.php';

$s3 = new Aws\S3\S3Client([
  'version' => 'latest',
  'region'  => 'us-east-1',
  'endpoint' => 'https://storage-dev.minio.theke.io/',
  'use_path_style_endpoint' => true,
  'credentials' => [
    'key'    => 'w3S0BFfzEMc1dp34bYIe',
    'secret' => 'ZksWRjOGyeXye5ps9bQS2KouyMM9Y5Uksr7amnGb'
  ],
]);

$insert = $s3->putObject([
  	'Bucket' => 'testminio',
	'Key' => 'imagetest.png',
  	'Body'   => fopen('treeimage','r')
]);

$retrive = $s3->getObject([
	'Bucket' => 'testminio',
	'Key' => 'imagetest.png',
  	'SaveAs' => 'pngtreetest_local.png'
]);

echo $retrive['Key'] . "succesfully download\n";

//phpinfo();
?>
