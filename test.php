<?php
$key='{
				  "type": "service_account",
				  "project_id": "api-xxxxxxxxxxxxxxxxxxxx",
				  "private_key_id": "xxxxxxxxxxxxxxxxxxxxxxxx",
				  "private_key": "xxxxxxxxxxxxxxxxxxxxxxxxxx",
				  "client_email": "xxxxxxxxxxxxxxxxxxxx",
				  "client_id": "xxxxxxxxxxxxxxxxxxxxxxxxxxx",
				  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
				  "token_uri": "https://accounts.google.com/o/oauth2/token",
				  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
				  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/xxxxxxxxxxxxxxxxxxxxx"
				}';
include("./GoogleServiceAccounts.class.php");
$obj=GoogleServiceAccounts::getInstance($key);
$obj->setPackage("package");
var_dump($obj->getReviews());
