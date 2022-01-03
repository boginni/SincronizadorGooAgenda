<?php
require 'google_calendar_api.php';




//$sql = "select * from CB_PROFISSIONAL A where A.TOKEN_GOOGLE is not null";
//print_r($queryManager->query($sql));




function sync(){

    $conn = getFirebirdConn();
    $queryManager = new QueryManager($conn);

    print("Starting Sync Process... \n");

    /**
     * Pega os Profissionais q possuem login com google
     */
    $list = $queryManager->getProfissonalListAsArray();
    foreach($list as $curProfissional) {
        syncProfissional($curProfissional, $queryManager);
    }

    print("\nSync Process Finished \n");



    ibase_close($conn);


    $conn = null;
    $queryManager = null;
    $curProfissional = null;
    $list = null;
}

print "Starting Script";

$sleepTime = 3;

for ($i = 0; $i < 100; $i++){
    sync();
    print("Sleeping for $sleepTime seconds \n");
    sleep($sleepTime);

}

print "Closing Script";

?>


