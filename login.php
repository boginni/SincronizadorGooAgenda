<?php
include "google_calendar_api.php";


if (isset($_GET['code'])) {
    $queryManager = new QueryManager(getFirebirdConn());
	$profissional = $queryManager->getProfissonal($_GET['prof']);
    $api = new GoogleClient($profissional);
    $returnStr = '';
	try{
        $api->authorizeClient($_GET['code']);
        $returnStr = 'suc=1';
    } catch (InvalidArgumentException $e){
        $returnStr = 'exc=1';
    }
    $queryManager->commitWork();
    header("Location: showGoogle.php?$returnStr");

}




