<?php

require 'google_login.php';


class GoogleClient {

    private $client;
    /**
     * @var Profissional
     */
    private $profissional;

    /**
     *
     */
    function getClient(){
        $this->validateToken();
        return $this->client;
    }

    function getUncheckClient(){
        return $this->client;
    }

    /**
     * @param $profissional Profissional | string
     * @throws \Google\Exception
     */
    function __construct($profissional) {
        $client = new Google_Client();

        $client->setApplicationName('Google Calendar API PHP Quickstart');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAuthConfig('credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');


        $this->client= $client;
        $this->profissional = $profissional;

//        if(is_array($profissional)){
//            $this->client= $client;
//            $this->profissional = $profissional;
//        } else {
//            $this->profissional = new Profissional(array(0 => $profissional));
//        }


    }

    function saveToken() {
        $accessToken = json_encode($this->client->getAccessToken());
        $id = $this->profissional->getID();
        $sql = "UPDATE CB_PROFISSIONAL as x SET x.TOKEN_GOOGLE = '$accessToken' where x.ID = '$id';";
        ibase_query(getFirebirdConn(), $sql) or dir(ibase_errmsg());
    }

    function authorizeClient($authCode) {

        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);

        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        } else {
            $this->saveToken();
        }

    }


    function validateToken() {

        $token = $this->profissional->getToken();

        if(empty($token))
            return false;

        $accessToken = json_decode($token, true);

        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {

            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());

                $this->saveToken();
            } else {
                return false;
            }

        }

        return true;
    }


}
/**
 * Classe Responsável para Agilizar a programação do código
 *
 */
class QueryManager
{


    private $conexao;

    /**
     * @param $conn resource
     */
    public function __construct($conn)
    {
        $this->conexao = $conn;

    }

    public function commitWork(){
        ibase_commit($this->conexao);
    }

    /**
     * @param $sql string
     * @return bool | resource
     */
    public function query($sql)
    {

        $rid = ibase_query($this->conexao, $sql) or dir(ibase_errmsg());
        return $rid;
    }

    public function setProfissionalCalendarID($id_profissional, $id_calendar)
    {

        $sql = "update CB_PROFISSIONAL A
		set A.CALENDAR_ID = '$id_calendar'
		where A.ID = '$id_profissional'";

        return $this->query($sql);

    }

    /**
     * @return integer
     */
    public function getEventCount(){
        $sql = "select count(*) from ag_agendamento_cab a;";
        $rid = $this->query($sql);
        $row = ibase_fetch_row($rid);
        return $row['0'];


    }

    public function getEventCountProf(){
        $sql = "select coalesce(count(*), '0') , coalesce(a.profissional, 'Sem Profissional')  from vw_ag_agendamento a
group by 2
order by count(*) desc ;";
        $rid = $this->query($sql);

        $list = array();
        while ($row = ibase_fetch_row($rid)) {
            array_push($list, $row);
        }
        return $list;




    }

    /**
     * @param $all bool
     * @return bool|resource
     */
    public function getProfissionalList($all = false)
    {
        $flag = $all?'':'where A.TOKEN_GOOGLE is not null';
        $sql = "
        select A.ID, A.TOKEN_GOOGLE, A.CALENDAR_ID, A.CALENDAR_PRINCIPAL, B.nome from cb_profissional A inner join cb_pessoa b ON A.id = B.id $flag;
        "; return $this->query($sql);
    }

    /**
     * @param $all bool
     * @return Profissional[]
     */
    public function getProfissonalListAsArray($all = false){
        $rid = $this->getProfissionalList($all);
        $list = array();
        while ($row = ibase_fetch_row($rid)) {
            array_push($list, new Profissional($row));
        }
        return $list;

    }

    /**
     * @return Profissional
     */
    public function getProfissonal($id_profissional){
        $sql = "select A.ID, A.TOKEN_GOOGLE, A.CALENDAR_ID, A.CALENDAR_PRINCIPAL from CB_PROFISSIONAL A where A.ID = '$id_profissional';";
        $rid = $this->query($sql);
        $list = $this->resourceToArray($rid);
        $profissional = null;
        if(sizeof($list) > 0)
            $profissional = new Profissional($list[0]);
        return $profissional;

    }

    public function updateEventStatus($eventID, $newStatus){
        $sql = "
        UPDATE AG_AGENDAMENTO_CAB A 
        SET A.CALENDAR_STATUS = '$newStatus'
        WHERE A.ID = '$eventID'
        ";

        $this->query($sql);
    }

    /**
     * @param $id_agendamento_cab int | string
     * @return array
     */
    public function getEventDetList($id_agendamento_cab){
        $sql = "
            select A.ID, C.ITEM, A.NOME
            from CP_PRODUTO_SERVICO A
            inner join(
            select B.ID_PRODUTO_SERVICO ID, B.ITEM as ITEM
            from AG_AGENDAMENTO_DET B
            where B.ID_AGENDAMENTO_CAB = '$id_agendamento_cab' and
            B.EXCLUIDO <> 'T'
            ) C on A.ID = C.ID;";
        $rid = $this->query($sql);
        $list = array();
        while ($row = ibase_fetch_row($rid)) {
            array_push($list, new EventItem($row));
        }
        return $list;
    }

    public function getEventCabList($profissional){
        $sql = $profissional->getQueryText();
        $rid = $this->query($sql);
        $list = array();
        while ($row = ibase_fetch_row($rid)) {
            array_push($list, $row);
        }
        return $list;

    }


    public function getEventCabGooGleIdList($profissional){
        $list = array();
        foreach ( $this->getEventCabListAsArray($profissional) as $row) {
            $list[$row->getGoogleEventID()] = '1';
        }
        return $list;
    }

    /**
     * @param $profissional Profissional
     * @return CalendarEvent[]
     */
    public function getEventCabListAsArray($profissional){
        $list = array();
        foreach($this->getEventCabList($profissional) as $row) {
            array_push($list, new CalendarEvent($row, $profissional));
        }
        return $list;
    }

    public function updateEventGoogleID($CabID, $GoogleID)
    {
        print "\nchanged calendar id to: $GoogleID \n";

        $sql = "
        UPDATE AG_AGENDAMENTO_CAB A 
        SET A.CALENDAR_EVENT_ID = '$GoogleID'
        WHERE A.ID = '$CabID'
        ";

        $this->query($sql);
    }

    public function getCalendarStatistic(){
        $sql = "select count(*), iif( A.ID_PROFISSIONAL is not null,
       (select B.NOME
        from CB_VW_PROFISSIONAL B
        where B.id = A.ID_PROFISSIONAL), 'SEM ID')
        from AG_AGENDAMENTO_CAB A
        group by A.ID_PROFISSIONAL
        having count(*) > 0
        order by 1 desc;";

        $rid = $this->query($sql);
        return $this->resourceToArray($rid);
    }

    public function resourceToArray($rid){
        $list = array();
        while ($row = ibase_fetch_row($rid)) {
            array_push($list, $row);
        }
        return $list;
    }



}

class EventItem {


    private $row;

    public function __construct($row)
    {
        $this->row = $row;
    }

    function getName(){
        return $this->row[2];
    }



}

class CalendarEvent{
    private $cab;
    /**
     * @var EventItem[]
     */
    private $det;

    private $id, $idPessoa;

    private $calendarStatus, $calendarEventID;

    public $start, $end;
    public $summary, $description;
    /**
     * @var Profissional
     */
    public $profissional;

    /**
     * @param $cab array
     * @param $profissional Profissional
     */
    public function __construct($cab, $profissional)
    {
        $this->profissional = $profissional;

        $this->cab = $cab;
        $this->id = $cab[0];

        $this->calendarStatus = (int) $cab[8];

        $this->calendarEventID = $cab[9];
        $this->idPessoa = $cab[1];

    }

    /**
     * @return Profissional
     */
    public function getProfissional(){
        return $this->profissional;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    public function getGoogleEventID(){
        return $this->calendarEventID;
    }

    /**
     * @param $det EventItem[]
     */
    public function setItens($det){
        $this->det = $det;
    }

    /**
     * @return bool
     */
    public function onWhiteList(){
        return onWhitelist($this->cab[10]);
    }

    /**
     * r
     */
    public function getSyncEvent(){


        switch ($this->getCalendarStatus()){
            case 0:
            case 1: return new SyncEvent($this);
            case 2: return new InsertEvent($this);
            case 3: return new UpdateEvent($this);
            case 4: return new DeleteEvent($this);
        }
    }

    public function toSynchronize(){
        return $this->getCalendarStatus() !== 0;
    }

    /**
     * @return int
     */
    public function getCalendarStatus(){
        if (empty($this->calendarStatus) and $this->calendarStatus != 0)
            $this->calendarStatus = 1;
        return $this->calendarStatus;
    }

    /**
     * @return int
     */
    public function getCabStatus(){
        return $this->cab[10];
    }



    private static $names = array(0 => "Nada a fazer", 1 => "Verificar", 2 => "Inserir", 3 => "Atualizar", 4 => "Deletar");

    public static function getStatusName($id){

        return self::$names[$id];

    }

    /**
     * @return string
     */
    public function getCalendarStatusName(){
        return self::getStatusName($this->getCalendarStatus());
    }

    public function getItens(){
        $itens = "Selecionados:\n";
        foreach ($this->det as $item) {
            $name = iconv("UTF-8", "UTF-8//IGNORE", $item->getName());
            $itens .= " $name\n";
        }

        return $itens;
    }

    public function createEventDescription(){

        $this->summary =  iconv("UTF-8", "UTF-8//IGNORE", $this->cab[2]);
        $this->setStart($this->cab[3]);
        $this->setEnd($this->cab[4]);

        $telefone = iconv("UTF-8", "UTF-8//IGNORE",$this->cab[5] );
        $email = iconv("UTF-8", "UTF-8//IGNORE", $this->cab[6]);
        $desc = iconv("UTF-8", "UTF-8//IGNORE", $this->cab[7]);

        $itens = $this->getItens();


        $this->description
            = "".
            "Telefone: $telefone\n".
            "Email: $email\n".
            "$itens\n".
            "Obs: \n".
            "$desc".
            "";

    }

    public function setStart($timedate) {

        $timedate = date_create($timedate);

        //$timedate->add(new DateInterval('P10Y'));

        $date = date_format($timedate, 'Y-m-d');;
        $time = date_format($timedate, 'H:i:s');;

        $this->start = $date . "T$time-03:00";

    }

    public function setEnd($timedate) {
        $timedate = date_create($timedate);

        //$timedate->add(new DateInterval('P10Y'));

        $date = date_format($timedate, 'Y-m-d');
        $time = date_format($timedate, 'H:i:s');

        $this->end = $date . "T$time-03:00";

    }

    public function getEventJson() {

        $arr = array('summary' => $this->summary);


        if (!is_null($this->description)) {
            $arr['description'] = $this->description;
        }

        $arr['start'] = array('dateTime' => $this->start);

        $arr['end'] = array('dateTime' => $this->end);

        return $arr;
    }

}

class SyncEvent{
    /**
     * @var Profissional
     */
    Protected $profissional;
    /**
     * @var CalendarEvent
     */
    protected $calendarEvent;
    /**
     * @var Google_Service_Calendar
     */
    protected $service;

    public $calendarId = "primary";

    /**
     * @param $calendarEvent CalendarEvent
     *
     */
    public function __construct($calendarEvent)
    {
        $this->calendarEvent = $calendarEvent;
    }

    /**
     * @param $syncronizer Synchronizer
     * @return bool
     */
    public function setService($syncronizer){
        $this->service = $syncronizer->getService();
        $this->calendarId = $syncronizer->getCalendarID();
    }

    /**
     * @return boolean
     */
    public function doExist(){
        $calendarId = $this->calendarId;
        $eventId = $this->calendarEvent->getGoogleEventID();

//        print "Check Existance \n";
//        print $eventId."\n";

        if(is_null($eventId) or empty($eventId) or is_null($calendarId))
            return 0;

        try{
            $this->service->events->get($calendarId, $eventId);
            return 1;
        } catch (Exception $e){
            return 0;
        }

    }

    /**
     * @param $doExist boolean
     * @return int
     */
    public function execute($doExist){
        if($this->calendarEvent->getCabStatus() == 2){
            return  ($doExist)? 3: 2;
        } else if($doExist){
            return 4;
        }
        return 0;
    }

    public function getGoogleCalendarEvent() {
        $event = new Google_Service_Calendar_Event($this->calendarEvent->getEventJson());
        return $event;
    }

    protected $newEventID;

    public function getNewEventID(){
        return $this->newEventID;
    }


}

/**
 * Class InsertEvent
 *
 * Feita para organizar e facilitar a incerssão de eventos
 */
class InsertEvent extends SyncEvent {

    public function execute($doExist){
        if($doExist)
            return 0;

        try{
            $event = $this->service->events->insert($this->calendarId, $this->getGoogleCalendarEvent());
            $this->newEventID = $event->getId();
            return 0;
        } catch (Exception $e){
            return 2;
        }
    }

}

class UpdateEvent extends SyncEvent {

    public  function execute($doExist){
        if(!$doExist)
            return 2;

        $calendarId = $this->calendarId;
        $eventId = $this->calendarEvent->getGoogleEventID();

//        print "before update \n";
//        print $calendarId."\n";
//        print $eventId."\n";
//        print $this->getGoogleCalendarEvent()->getSummary()."\n";

        $updatedEvent = $this->service->events->update($this->calendarId,  $this->calendarEvent->getGoogleEventID(), $this->getGoogleCalendarEvent());


        try{

            $updatedEvent = $this->service->events->update($this->calendarId,  $this->calendarEvent->getGoogleEventID(), $this->getGoogleCalendarEvent());
            $this->newEventID = $updatedEvent->getId();

            return 0;
        } catch (Exception $e){
            return 3;
        }
    }
}

class DeleteEvent extends  SyncEvent {



    public function execute($doExist){
        if(!$doExist)
            return 0;
        try{
            $this->service->events->delete($this->calendarId, $this->calendarEvent->getGoogleEventID());
            $this->newEventID = '';
            return 0;
        } catch (Exception $e){
            return 4;
        }
    }
     
}

class Profissional
{
    /**
     * @var array
     */
    private $row;

    /**
     * @param $row array
     */
    public function __construct($row)
    {
        $this->row = $row;
    }

    /**
     * @return string
     */
    public function getID(){
        return $this->row[0];
    }

    /**
     * @return string
     */
    public function getToken(){
        $token = $this->row[1];

        return $token;
    }

    /**
     * @return string
     */
    public function getCalendarID(){
        return $this->row[2];
    }


    /**
     * @return bool
     */
    public function isPrincipal(){
        return $this->row[3] == 'T';
    }

    /**
     * @return string
     */
    public function getNome(){
        return $this->row[4];
    }


    public function getQueryText(){
        $dateQuery = (true) ? 'current_time' : "'01.21.2021'";
        $ID_PROFISSIONAL = $this->getID();

        $sql1 = "select A.ID, A.ID_PESSOA, A.NOME, A.HORARIO_INI, A.HORARIO_FIN, A.TELEFONE, A.EMAIL, A.DESCRICAO, A.CALENDAR_STATUS,
        A.CALENDAR_EVENT_ID, A.STATUS from AG_AGENDAMENTO_CAB A 
        WHERE A.ID_PROFISSIONAL ";

        $sql2 = " and A.HORARIO_INI > $dateQuery ORDER BY A.HORARIO_INI; ";
        $normalSQL = "
         $sql1 = '$ID_PROFISSIONAL' $sql2 ";

        $principalSQL = "
        $sql1 is null $sql2";

        return ($this->isPrincipal()) ? $principalSQL : $normalSQL;
    }

}

class Synchronizer {
    private $defaultCalendarName = 'testCalendar';
    /**
     * @var Google_Service_Calendar
     */
    private $service;
    /**
     * @var GoogleClient
     */
    private $client;
    /**
     * @var \Google\Service\Calendar\Calendar
     */
    private $calendar;

    /**
     * @param $profissional Profissional
     * @param $queryManager QueryManager
     */
    public function __construct($profissional, $queryManager)
    {
        $this->profissional = $profissional;
    }

    /**
     * @return GoogleClient
     */
    public function getClient(){
        return $this->client;
    }
    /**
     * @return Google_Service_Calendar
     */
    public function getService()
    {
        return $this->service;
    }
    public function startClient(){
        $this->client = new GoogleClient($this->profissional);
    }

    public function startService(){
        $this->service = new Google_Service_Calendar($this->client->getUncheckClient());
    }

    private $calendarId;

    public function getCalendarID(){
        return $this->calendarId;
    }

    public function startCalendar(){
        try {
            $this->calendarId = $this->profissional->getCalendarID();
            $calendar = $this->service->calendars->get($this->calendarId);
        } catch (Exception $e) {
            $calendar = new Google_Service_Calendar_Calendar();
            $calendar->setSummary($this->defaultCalendarName);
            $newCalendar = $this->service->calendars->insert($calendar);
            $this->calendarId = $newCalendar->getId();
            $calendar = $this->service->calendars->get($this->calendarId);

        }
        $this->calendar = $calendar;
    }

    /**
     * @return \Google\Service\Calendar\Calendar
     */
    public function getCalendar()
    {
        return $this->calendar;
    }


}

/**
 * @param $id int
 * @return bool
 */
function onWhitelist($id)
{

    // ??? = 0
    //Agendado = 1
    //Cancelado = 2
    //Confirmado = 3
    //Aguardando = 4
    //Chamou Para Atendimento
    //Iniciou Atendimento
    //Atendido
    //Horario Não Disponivel
    //Desistiu
    //Transferido
    //Faltou

    $whitelist = [
        0 => true,
        1 => true,
        2 => true,
        3 => true
    ];

    if ($id >= count($whitelist))
        return false;

    return $whitelist[$id];
}

/**
 * @param $profissional Profissional
 * @param $queryManager QueryManager
 */
function syncProfissional($profissional, $queryManager)
{

    $syncronizer = new Synchronizer($profissional, $queryManager);

    $syncronizer->startClient();


    //Token Inválido
    if(!$syncronizer->getClient()->validateToken()){
        return;
    }

    $syncronizer->startService();
    $syncronizer->startCalendar();

//    print  $profissional->getQueryText();
    $events = $queryManager->getEventCabListAsArray($profissional);
    $i = 1;
    //Insere os eventos que estão na whitelist
    if(count($events) > 0)
        print "\nSync ID[".$profissional->getID()."]... \n";

    foreach ($events as $curEvent) {
        print "\n";

        print "Event[".$i++."]:\n";
        print "Status: ".$curEvent->getCalendarStatus()."\n";

        // se status for cancelado e evento existir, delete
        // se status for cancelado e evento não existir, fazer nada

        // se status for atualizar e evento não existir, inserir
        // se status for atualizar e evento existir, alterar e fazer nada

        // se status for inserir e evento não existir, inserir e fazer nada



        if (!$curEvent->toSynchronize())
            continue;

        $items = $queryManager->getEventDetList($curEvent->getId());
        $curEvent->setItens($items);

        $curEvent->createEventDescription();

//        $syncEvent = $curEvent->getSyncEvent();

        $syncEvent = $curEvent->getSyncEvent();

        $syncEvent->setService($syncronizer);

        $doExist = $syncEvent->doExist();

        $newStatus = $syncEvent->execute($doExist);

        if(is_null($newStatus))
            $newStatus = 1;


        print "Exist: ".$doExist."\n";

        $queryManager->updateEventStatus($curEvent->getId(), $newStatus);

        if($newStatus == 0)
            $queryManager->updateEventGoogleID($curEvent->getId(), $syncEvent->getNewEventID());

        print "NewStatus:".$newStatus."\n";




    }

    $queryManager->commitWork();

    //Deleta eventos que não existem
    $calendar = $syncronizer->getCalendar();
    $events = $syncronizer->getService()->events->listEvents($calendar->getId());
    $cab = $queryManager->getEventCabGooGleIdList($profissional);
    foreach ($events->getItems() as $event) {
        if(!array_key_exists($event->getId(), $cab)){
            print 'Deleting: '.$event->getSummary()."\n";
            $syncronizer->getService()->events->delete($calendar->getId(), $event->id);
        }

    }

    /*
     * Limpa a Memoria
     */
    $syncronizer = null;
    $events = null;

}

/**
 * @param $calendarId string
 * @param $service Google_Service_Calendar_Calendar
 */
function clearCalendar($calendarId, $service)
{
    $results = $service->events->listEvents($calendarId);
    $events = $results->getItems();
    foreach ($events as $event) {
        $eventID = $event->getId();
        $service->events->delete($calendarId, $eventID);
    }
}

//
///**
// * @param $service Google_Service_Calendar
// * @param $insertEvent InsertEvent
// */
//function addEvent($service, $insertEvent) {
//	$event = $service->events->insert($insertEvent->calendarId, $insertEvent->getCalendarEvent());
//	return $event;
//}
//
///**
// * @param $service Google_Service_Calendar
// * @param $insertEvent InsertEvent
// */
//function updateEvent($service, $insertEvent, $eventId) {
//	$event = $service->events->update('primary', $eventId, $insertEvent->getCalendarEvent());
//	return $event;
//}
//
///**
// * Chamado através de _POST
// */
//if (isset($_POST['insertEvent'])) {
//
//	if ($client = getValidClient()) {
//
//		$service = new Google_Service_Calendar($client);
//		$insertEvent = new InsertEvent($_POST['ie-su'], $_POST['ie-loc'], $_POST['ie-dc']);
//
//		$insertEvent->setStart2($_POST['ie-date'], true);
//
//		addEvent($service, $insertEvent);
//
//	}
//
//	header("Location: showGoogle.php");
//
//
//}
//
///**
// * Chamado através de _POST
// */
//if (isset($_POST['deleteEvent'])) {
//
//	if ($client = getValidClient()) {
//		$service = new Google_Service_Calendar($client);
//		$eventID = $_POST['event-id'];
//		$service->events->delete('primary', $eventID);
//	}
//
//	header("Location: showGoogle.php");
//}
///**
// * Chamado através de _POST
// */
//if (isset($_POST['getEvent'])) {
//	header("Location: showGoogle.php");
//}
///**
// * Chamado através de _POST
// */ //2pj43blj8pioug0ii7g2tjiq2c@group.calendar.google.com
//if (isset($_POST['updateEvent'])) {
//	if ($client = getValidClient()) {
//		$service = new Google_Service_Calendar($client);
//
//		$insertEvent = new InsertEvent($_POST['ie-su'], '', $_POST['ie-dc']);
//
//		$insertEvent->setStart3(
//			date_format(date_create($_POST['ie-date']), 'Y-m-d'),
//			date_format(date_create($_POST['ie-time-s']), 'H:i:s'));
//
//		$insertEvent->setEnd3(
//			date_format(date_create($_POST['ie-date']), 'Y-m-d'),
//			date_format(date_create($_POST['ie-time-e']), 'H:i:s'));
//
//		$eventID = $_POST['event-id'];
//		print_r($insertEvent->getEventJson());
//		updateEvent($service, $insertEvent, $eventID);
//
//	}
//
//	header("Location: showGoogle.php");
//}



