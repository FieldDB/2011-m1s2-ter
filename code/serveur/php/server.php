<?php

require_once("ressources/backend.inc");
require_once("ressources/db.inc");

/** Ecrit un rapport d'erreur dans un fichier.
* @param errNum : Numéro de l'erreur.
* @param msg : Description de l'erreur.
* @param [other] : (Optionnel) Complément d'information.
*/
function logError($errNum, $msg, $other="")
{
	$file = fopen("./log.txt","a+");

	// Met en forme la chaine contenant les paramètres de la requête.
	$dumpParameters = str_replace("(\n","",print_r($_GET,true));
	$dumpParameters = str_replace(")\n","",$dumpParameters);

	fwrite($file,"\nErreur n° ".$errNum);
	fwrite($file," : ".$msg);
	if(!empty($other))	
		fwrite($file,"\n ".$other);
	fwrite($file,"\n  ".$dumpParameters);

	fclose($file);
}

/** La fonction principale.
* @param action : Un identifiant d'action.
*/
function main()
{
	if(!isset($_GET['action']) || !isset($_GET['user']) || !isset($_GET['passwd'])) {
		throw new Exception("La requête est incomplète", 2);
	}
	
	// Login
	$action = $_GET['action'];
	$user = SQLite3::escapeString($_GET['user']);
	$loginIsOk = checkLogin($user, $_GET['passwd']);
	if ($action != 3 && (!$loginIsOk)) {
		throw new Exception("Utilisateur non enregistré ou mauvais mot de passe", 3);
	}
	if ($action == 3) {
		echo '{"login_ok":' . ($loginIsOk ? 'true' : 'false') . '}';
		exit;
	}
	
	// Sinon tout est bon on effectue l'opération correspondant à la commande passée.
	// TODO : en production, utiliser : header("Content-Type: application/json; charset=utf-8");
	header("Content-Type: text/plain; charset=utf-8");
	
	if  ($action == 2) {                // "Create partie"
		// Requête POST : http://serveur/server.php?action=2&nb=2&mode=normal&user=foo&passwd=bar
		if (!isset($_GET['nb']) || !isset($_GET['mode'])) {
			throw new Exception("La requête est incomplète", 2);
		}
		createGame(intval($_GET['nb']), $_GET['mode']);
		echo '{"success":1}';
	}
	else if($action == 0) {           // "Get partie"
		// Requête POST : http://serveur/server.php?action=0&user=foo&passwd=bar
		getGame($user);
	}
	else if($action == 1) {           // "Set partie"
		// Requête POST : http://serveur/server.php?action=1&mode=normal&user=foo&passwd=bar&gid=1234&pgid=12357&0=0&1=-1&2=22&3=13&9=-1
		if (!isset($_GET['pgid']) || !isset($_GET['gid']) || !isset($_GET['answers'])) {
			throw new Exception("La requête est incomplète", 2);
		}
		setGameGetScore($user, $_GET['pgid'], $_GET['gid'], $_GET['answers']);
	} else if($action == 4) {           // CheckWord
		if (!isset($_GET['word']))
			errRequestIncomplete();

		if(wordExist($_GET['word']))
			echo true;
		else
			echo false;
	}
	else if($action == 5) {           // Get relations (JSON)
		echo getGameRelationsJSON();
	}
	else if($action == 6) {
		if (!isset($_GET['game']))
			errRequestIncomplete();
		
		decodeAndInsertGame($_GET['game']);
		
	} else {
		throw new Exception("Commande inconnue", 2);
	}
}

function server() {
	if(isset($_GET['callback']))
		echo $_GET['callback'].'(';
	ob_start();
	try {
		main();
		ob_end_flush();
	} catch (Exception $e) {
		ob_end_clean();
		echo "{\"error\":".$e->getCode().",\"msg\":".json_encode("".$e->getMessage())."}";
		logError($e->getCode(), $e->getMessage(), date("c"));
		closeDB();
	}
	if(isset($_GET['callback']))
		echo ')';
}

server();

?>
