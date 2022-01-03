<?php
require 'google_calendar_api.php';
$conexao = getFirebirdConn();
$queryManager = new QueryManager($conexao);


$totQ = $queryManager->getEventCount();
$profQTD = $queryManager->getEventCountProf();


?>
<html>
<head>

    <link rel="stylesheet" href="style/inputEvent.css">
    <link rel="stylesheet" href="style/table.css">

    <script src="js/canvasjs.min.js"></script>

    <style>
        div.block {

            width: 80%;
            margin: auto;
        }

        div.border {
            border: 1px solid black;
        }

        .customers {
            font-family: Arial, Helvetica, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }

        .customers td, .customers th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .customers tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .customers tr:hover {
            background-color: #ddd;
        }

        .customers th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #04AA6D;
            color: white;
        }

        .warning {
            border: 1px solid;
            margin: 10px 0px;
            padding: 15px 10px 15px 50px;
            background-repeat: no-repeat;
            background-position: 10px center;

            display: inline-block;

        }

        .error {
            color: #D8000C;
            background-color: #FFBABA;
            background-image: url('https://i.imgur.com/GnyDvKN.png');

        }

        .success {
            color: #4F8A10;
            background-color: #DFF2BF;
            background-image: url('https://i.imgur.com/Q9BGTuy.png');
        }


    </style>

    <script>
        window.onload = function() {

            var chart = new CanvasJS.Chart("chartContainer", {
                animationEnabled: true,
                title: {
                    text: "Eventos Por profissionais"
                },
                data: [{
                    type: "pie",
                    startAngle: 240,
                    yValueFormatString: "##0.00\"%\"",
                    indexLabel: "{label} {y}",
                    dataPoints: [
                        <?php
                        foreach ($profQTD as $row) {
                            $prof = $row['1'];
                            $qtd = 100 * $row[0] / $totQ;
                            print "{y: $qtd, label: \"$prof\"},";
                        }
                        ?>
                    ]
                }]
            });
            chart.render();

        }
    </script>

</head>
<body>
<?php


/**
 * @param $profissional Profissional
 */
function option($profissional)
{

    $id = $profissional->getID();
    $nome = $profissional->getNome();

    print "<option value='$id'>$id $nome</option>";
}


/**
 * @param $profissional Profissional
 * @param $qm QueryManager
 */
function printProfissional($profissional, $qm)
{
    $id = $profissional->getID();
    $nome = $profissional->getNome();
    $sync = new Synchronizer($profissional, $qm);


    $sync->startClient();
    $validToken = $sync->getClient()->validateToken();
    $strStatus = $validToken ? "Valido" : "Desconectado";

    $classStatus = $validToken ? 'green' : 'red';

    $info =
        "<td>$id</td>" .
        "<td>$nome</td>" .
        "<td class = '$classStatus'>$strStatus</td>";

    return $info;
}

function printProfissionalQTD($row)
{

    $nome = $row['1'];
    $qtd = $row['0'];


    $info =
        "<td>$nome</td>" .
        "<td>$qtd</td>";

    return $info;
}

?>
<div class="block border">
    <center>
        <?php
        if (isset($_GET['exc'])) {
            print "<div class='warning error'>Código inválido</div>";
        }

        if (isset($_GET['suc'])) {
            print "<div class='warning success'>Conta Cadastrada</div>";
        }
        ?>
        <form action="showGoogle.php" method="get">
            <label for="prof">Escolha um Colaborador:</label><br>
            <select name="prof">

                <?php


                $profissionais = $queryManager->getProfissonalListAsArray(true);

                foreach ($profissionais as $curProfissional) {
                    $row = printProfissional($curProfissional, $queryManager);
                    option($curProfissional);
                    print "<tr>$row</tr>";
                }


                ?>
            </select>
            <?php

            ?>
            <input type="submit" value="Selecionar">
        </form>
        <?php

        if (!isset($_GET['prof'])) {
            print "selecione um colaborador";

        } else {

            $profissional = $queryManager->getProfissonal($_GET['prof']);

            $api = new GoogleClient($profissional);
            if (!$api->validateToken()) {
                $authUrl = $api->getClient()->createAuthUrl();
                ?>
                <br>
                <a href="<?php print $authUrl ?>" target="_blank">Pegue um
                    código</a> de autenticação da sua conta no google:
                <form action='login.php' method='get'>
                    <input name='code' placeholder='Cole o código de autenticação'>
                    <input name="prof" type="hidden" value="<?php print $_GET['prof'] ?>">
                    <input type='submit' name='enviar'>
                </form>
                <br>
                <?php
                print "</body></html>";
            } else {
                ?>
                <label>Essa conta já está vinculada!!</label>
                <?php
            }
        }
        ?>
    </center>
</div>

<?php
$profissionais = $queryManager->getProfissonalListAsArray();

?>
<br>
<div class="block">
    <table class="customers">
        <tr>
            <th>
                ID
            </th>
            <th>
                NOME
            </th>
            <th>
                STATUS
            </th>
        </tr>

        <?php
        foreach ($profissionais as $curProfissional) {
            $row = printProfissional($curProfissional, $queryManager);

            print "<tr>$row</tr>";
        }

        ?>
    </table>
</div>

<br>
<div id="chartContainer" style="height: 300px; width: 100%;"></div>
<br>
<br>
<div class="block">
    <table class="customers">
        <tr>
            <th>
                NOME
            </th>
            <th>
                Quantidade
            </th>
        </tr>

        <?php
        foreach ($profQTD as $curProfissional) {
            $row = printProfissionalQTD($curProfissional);

            print "<tr>$row</tr>";
        }

        ?>
    </table>
</div>


</body>
</html>