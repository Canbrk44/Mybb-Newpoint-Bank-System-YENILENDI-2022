<?php
/*
 * @author 	: Surdeanu Mihai
 * @version	: 2.1
 * @date	: 05.09.2012
 * =========================
 * All rights reserved.
 */
function task_newpoints_bank_task($task) 
{
	global $db, $mybb;
	
	// se actualizeaza dobanda pentru fiecare utilizator in parte
	newpoints_task_update_rate();
	
	// se actualizeaza dobanda pentru imprumuturi
	newpoints_task_update_loan();

	// se citesc datele din cache
	$penalties = $mybb->cache->read('newpoints_bank');
	if ( ! $penalties || ! is_array($penalties)) {
		$penalties = array(
			'bp' => array(),
			'bv' => array()
		);
	} else {
		if ( ! array_key_exists('bp', $penalties) || ! is_array($penalties['bp'])) {
			$penalties['bp'] = array();
		}
		if ( ! array_key_exists('bv', $penalties) || ! is_array($penalties['bv'])) {
			$penalties['bv'] = array();
		}
	}	

	$query = $db->write_query("
    	SELECT u.uid AS uid, u.usergroup AS usergroup, u.additionalgroups AS addgroups, u.displaygroup AS displaygroup, l.username AS username, l.date AS date
        FROM " . TABLE_PREFIX . "newpoints_loans l
        LEFT JOIN " . TABLE_PREFIX . "users u ON (u.username = l.username)
		WHERE ((" . TIME_NOW . " - l.date) DIV 86400) >= 7
    ");
	// pentru fiecare imprumut existent
	while ($row = $db->fetch_array($query)) {
    	// de cate zile exista acest imprumut
    	$diff = TIME_NOW - (int)$row['date'];
    	$diff_days = (int)($diff / 86400);
		switch ($diferenta_in_zile) {
        	case 7 :
            	// se trimite un PM prin care utilizatorul e informat ca trebuie sa isi plateasca imprumutul
                $pm = array(
                	"subject" => "Payment Reminder (7 days)",
                    "message" => "Hi [b]" . $row['username'] . "[/b]! This email is automatically to your hereby notified that a few days ago you did a loan and you must pay! \nYou can pay to get rid of money! \nIf in the following 4 days you do not pay you will not be able to post threads or replies!",
                    "touid" => $row['uid'],
                    "receivepms" => true
                );
                newpoints_send_pm($pm, -1);    
            break;
        	case 11 :   
				// o noua instiintare dar si o noua penalizare
                $pm = array(
                   	"subject" => "Payment Reminder (11 days)",
                	"message" => "Hi [b]" . $row['username'] . "[/b]!! This email is automatically to your hereby notified that a few days ago you did a loan and you must pay! \nYou can pay to get rid of money! \nIf in the following 3 days you do not pay you will cannot view some forums!",
                	"touid" => $row['uid'],
                   	"receivepms" => true
                );
                newpoints_send_pm($pm, -1);  
				
                // se adauga si penalizarea
				array_push($penalties['bp'], $row['uid']);
			break;
        	case 14 :
				// au trecut deja 2 saptamani
                $pm = array(
                	"subject" => "Payment Reminder (14 days)",
                    "message" => "Hi [b]" . $row['username'] . "[/b]!! This email is automatically to your hereby notified that a few days ago you did a loan and you must pay! \nYou can pay to get rid of money! \nIn this moment you cannot post or view some threads!",
                    "touid" => $row['uid'],
                    "receivepms" => true
                );
                newpoints_send_pm($pm,-1);  
				
                // se adauga si penalizarea
				array_push($penalties['bp'], $row['uid']);
				array_push($penalties['bv'], $row['uid']);  
        	break;
			case 21 :
				// au trecut 3 saptamani deja
				
				// utilizatorul e banat pe viata
                $bantime = '---';
                $lifted = 0;                   
                // care este noul grup al utilizatorului
                $gid = 7;     
               	// vectorul ce va fi inserat in tabelul "banned"
                $insert_array = array(
                	'uid' => $row['uid'],
                    'gid' => $gid,
                    'oldgroup' => $row['usergroup'],
                    'oldadditionalgroups' => $row['addgroups'],
                    'olddisplaygroup' => $row['displaygroup'],
                    'admin' => 1,
                    'dateline' => TIME_NOW,
                    'bantime' => $bantime,
                    'lifted' => $lifted,
                    'reason' => 'User banned because he do not pay his borrow for more then 3 weeks!'
               	);
                // se insereaza in baza de date
                $db->insert_query('banned', $insert_array);
                // se muta userul in grupul dorit
                $db->write_query("
                	UPDATE " . TABLE_PREFIX . "users 
                    SET usergroup = '" . $gid . "' 
                    WHERE uid = '" . (int)$row['uid'] . "'
                ");   
		}
    }
	
	// se actualizeaza cache-ul cu cei banati
    $mybb->cache->update_banned();
				 
	// se actualizeaza cache-ul cu penalizari
	$mybb->cache->update('newpoints_bank', $penalties);
	
	// task rulat cu succes
	add_task_log($task, 'The Bank System task successfully ran.');
}

// Functia acorda dobanda zilnica care este setata de catre administrator pentru cei care au bani in banca
function newpoints_task_update_rate() 
{
	global $db, $mybb;
	
	$now = TIME_NOW;
	$rate = number_format((float)$mybb->settings['newpoints_bank_rate'] / 100, 4);
	$max = (float)$mybb->settings['newpoints_bank_maxbank'];
	if ($max < 0) {
		$max = 0;
	}
		
	// se actualizeaza dobanda bancii pentru fiecare utilizator in parte
	$db->write_query("
		UPDATE " . TABLE_PREFIX . "users 
		SET newpoints_bankoffset = LEAST({$max}, newpoints_bankoffset * (1 + {$rate} * ({$now} - newpoints_bankbasetime) / 86400)), newpoints_bankbasetime = {$now} 
		WHERE newpoints_bankoffset > 0
	");
}

// Functia actualizeaza dobanda care se plateste zilnic pentru un imprumut
function newpoints_task_update_loan() 
{
	global $db, $mybb;
	
	$now = TIME_NOW;
	$rate = number_format((float)$mybb->settings['newpoints_bank_commission'] / 100, 4);
	
	// se actualizeaza dobanda bancii pentru fiecare utilizator in parte
	$db->write_query("
		UPDATE " . TABLE_PREFIX . "newpoints_loans 
		SET sum_loan = (sum_loan - sum_paid) * (1 + {$rate} * ({$now} - datec) / 86400), datec = {$now}
	");
}
?>
