<?php
require 'google_login.php';
//require 'firebird.php';

 $conexao = getFirebirdConn();

?>
<html>
<head>

    <link rel="stylesheet" href="style/inputEvent.css">
    <link rel="stylesheet" href="style/table.css">

</head>
<body>
<?php

?>
<center>

    <form id="edit" action="google_calendar_api.php" method="post">
        <table>
            <tr>
                <td colspan="3">
                    <input class="summary" placeholder="Título" name="ie-su" type="text">
                </td>
                <td rowspan="2">
                    <textarea placeholder="Descrição" name="ie-dc"></textarea>
                </td>
                <td rowspan="2">
                    <input type="submit" name="insertEvent">
                </td>
            </tr>
            <tr>
                <td>
                    Data<br>
                    <input type="date" name="ie-date">
                </td>

                <td>
                    Começo<br>
                    <input type="time">
                </td>

                <td>
                    Fim<br>
                    <input type="time">
                </td>
            </tr>
            <tr>

            </tr>


        </table>

    </form>


    <form action="showFirebird.php" method="get">
        <label for="prof">Escolha um Colaborador:</label>
        <select name="prof">
			<?php
			$sql_select = "select * from CB_VW_PROFISSIONAL ORDER BY CB_VW_PROFISSIONAL.NOME";
			$rid = ibase_query($conexao, $sql_select) or dir(ibase_errmsg());

			while ($row = ibase_fetch_row($rid)) {
				option($row[0], $row[1], $row[2]);
			}
			?>
        </select>
		<?php


		?>
        <br>
        <input type="submit" value="Submit">
    </form>
	<?php

	$id = 0;
	if (isset($_GET['prof'])) {

		$id = $_GET['prof'];

		$sql_select = "select * from CB_VW_PROFISSIONAL where CB_VW_PROFISSIONAL.ID = $id;";
		$rid = ibase_query($conexao, $sql_select) or dir(ibase_errmsg());
		$row = ibase_fetch_row($rid);



		if(empty($row[11])){
		    print "Sem Login no google";
        } else{

        }

		?>

        <div class="dateBlock">


            <table id="ag_dba">
                <tr>
                    <th>
                        Nome do Evento
                    </th>
                    <th>
                        Começo
                    </th>
                    <th>
                        Fim
                    </th>
                    <th>
                        Descrição
                    </th>
                    <th>
                        ID
                    </th>

                    <th>
                        Opções
                    </th>
                </tr>
				<?php


				//			$sql_select = "select NOME, STATUS , HORARIO_INI , HORARIO_FIN , DESCRICAO , ID from AG_AGENDAMENTO_CAB;";


				$sql_select = "
select NOME, STATUS , HORARIO_INI , HORARIO_FIN , DESCRICAO , ID 
from AG_AGENDAMENTO_CAB
WHERE 
      ID_PROFISSIONAL = $id
      and HORARIO_INI > '01.10.2021'
ORDER BY HORARIO_INI;"; // current_time
				$rid = ibase_query($conexao, $sql_select) or dir(ibase_errmsg());
				//$coln = ibase_num_fields($rid);
				$index = 0;
				ibase_close($conexao);
				while ($row = ibase_fetch_row($rid)) {

//				if (++$index > 1)
//					break;

					$start = $row[2];

					$summary = $row[0];
					?>
                    <tr>
                        <td><?php print $summary ?></td>

                        <td>
							<?php
							$date = date_create($start);
							print date_format($date, 'd/m/y H:i:s');
							?>
                        </td>

                        <td>
							<?php
							$date = date_create($row[3]);
							print date_format($date, 'd/m/y H:i:s');
							?>
                        </td>

                        <td>
							<?php
							print $row[4];
							?>
                        </td>

                        <td><?php print $row[5] ?></td>
                        <td>
                            <a href="edit.php?event-id=<?php print $row[5]; ?>">Editar</a>
                            <form action="google_calendar_api.php" method="post">
                                <input type="hidden" name="event-id" value="<?php print $row[5] ?>">
                                <input type="submit" value="delete" name="deleteEvent">
                            </form>
                        </td>
                    </tr>

					<?php

				}

				?>
            </table>
        </div

		<?php
	}
	?>


</center>


<?php

//$sql_select = "select * from CB_VW_PROFISSIONAL where ID = 1;";
////$sql_select = "select * from AG_AGENDAMENTO_CAB;";
//$rid = ibase_query($conexao, $sql_select) or dir(ibase_errmsg());
//$row = ibase_fetch_row($rid);
//
//
//foreach ($row as $item) {
//	print $item . "<br>";
//}
//

?>

</body>
</html>