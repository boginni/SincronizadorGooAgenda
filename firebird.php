<?php

/**
 * @return false|resource
 */
function getFirebirdConn()
{
    $hostname = 'D:/FireBird/Databases/Clinica Alfredo/Backup/clientesBANCO/CLINICA/C.ALFREDO/CLINICAALFREDO.FDB';
    $username = 'LIVE';
    $password = 'MasterLIVE';
    $pass_default = 'masterkey';
    $user_default = 'SYSDBA';
    return ibase_connect($hostname, $username, $password);
}
