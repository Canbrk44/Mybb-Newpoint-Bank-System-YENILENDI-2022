<?php
/*
 * @author 	: Surdeanu Mihai
 * @version	: 2.2 Stable
 * @date	: 08.10.2012
 * =========================
 * All rights reserved.
 */
if( ! defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Carlige de legatura
$plugins->add_hook("newpoints_start", "newpoints_bank_page");

// Carlige - in panoul de administrare
if (defined("IN_ADMINCP")) {
	$plugins->add_hook("newpoints_admin_stats_noaction_start", "newpoints_bank_stats");
	$plugins->add_hook("newpoints_admin_maintenance_end", "newpoints_bank_finance");
	$plugins->add_hook("newpoints_admin_maintenance_terminate", "newpoints_bank_finance_do");
	$plugins->add_hook("newpoints_admin_maintenance_edituser_form", "newpoints_bank_edituser");
	$plugins->add_hook("newpoints_admin_maintenance_edituser_commit", "newpoints_bank_edituser_do");
} else {
	$plugins->add_hook("newpoints_default_menu", "newpoints_bank_menu");
	$plugins->add_hook("member_profile_end", "newpoints_bank_profile");
	$plugins->add_hook("newpoints_stats_start", "newpoints_bank_stats");
	
	// Carlige - penalitati
	$plugins->add_hook("newthread_start", "newpoints_bank_postingpenalties");
	$plugins->add_hook("newreply_start", "newpoints_bank_postingpenalties");
	$plugins->add_hook("showthread_start", "newpoints_bank_viewingpenalties");
}

// Carlig pentru backup
$plugins->add_hook('newpoints_task_backup_tables', 'newpoints_bank_backup');

function newpoints_bank_info()
{
	return array(
		"name"			=> "Bank System",
		"description"	=> "Integrates a bank system with NewPoints.",
		"website"		=> "http://www.mihaisurdeanu.ro",
		"author"		=> "Surdeanu Mihai",
		"authorsite"	=> "http://www.mihaisurdeanu.ro",
		"version"		=> "2.2",
		"guid" 			=> "",
		"compatibility" => "1*"
    );
}

function newpoints_bank_install()
{
	global $db, $mybb, $lang;
	
	// se creaza tabela
	$collation = $db->build_create_table_collation();
	// se creaza doar daca nu exista
	if (!$db->table_exists('newpoints_loans')) {
		$db->write_query("CREATE TABLE `" . TABLE_PREFIX . "newpoints_loans` (
	  		`id` int(12) NOT NULL auto_increment,
			`date` bigint(30) NOT NULL,
			`datec` bigint(30) NOT NULL,
	  		`username` text NOT NULL,
	  		`sum_loan` decimal(20,2) NOT NULL,
	  		`sum_paid` decimal(20,2) NOT NULL,
			PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM{$collation}
		");
	}
	
	if ($db->field_exists('newpoints_bankoffset', 'users')) {
		$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` DROP `newpoints_bankoffset`");	
	}
	$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `newpoints_bankoffset` DECIMAL(20,2) NOT NULL DEFAULT '0'");
	
	if ($db->field_exists('newpoints_bankbasetime', 'users')) {
		$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` DROP `newpoints_bankbasetime`");	
	}
	$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `newpoints_bankbasetime` BIGINT(30) NOT NULL DEFAULT '0'");
	  
	// informatii ce se adauga in cache (initiale)
	$datacache = array(
		'bank_sum' => 1000000,
		'bank_tot' => 1000000,
		'bp' => array(),
		'bv' => array()
	);
	$mybb->cache->update('newpoints_bank', $datacache);
	
	// se adauga cateva sabloane in baza de date
	newpoints_add_template('newpoints_bank', '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->newpoints_bank}</title>
{$headerinclude}
{$javascript}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top" width="180">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
</tr>
{$options}
</table>
</td>
<td valign="top">
<form action="newpoints.php" method="POST" name="bank_form">
<input type="hidden" name="postcode" value="{$mybb->post_code}">
<input type="hidden" name="action" value="do_bank">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->newpoints_bank}</strong></td>
</tr>
<tr>
<td width="100%" colspan="2">
<table>
<tr>
<td class="trow1" width="90%">{$lang->newpoints_bank_fee}</td>
<td class="trow1" width="10%"><img src="inc/plugins/newpoints/images/bank.png"/></td>
</tr>
</table>
</td>
</tr>
<tr>
<td class="tcat" width="100%" colspan="2">{$lang->newpoints_bank_transactions}</td>
</tr>
<tr>
<td class="trow2" width="100%" colspan="2"><b>{$mybb->user[\'username\']}</b> {$ihaveborrow}</td>
</tr>
<tr>
<td class="trow2" width="50%"><strong>{$lang->newpoints_bank_onhand}:</strong></td>
<td class="trow2" width="50%">{$mybb->user[\'newpoints\']}</td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_bank_inbank}:</strong></td>
<td class="trow1" width="50%">{$mybb->user[\'newpoints_bankoffset\']} ($lastupdate)</td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_amount}:</strong></td>
<td class="trow1" width="50%"><input class="textbox" type="text" value="0" name="amount" id="amount"/></td>
</tr>
<tr>
<td class="trow2" width="50%"><strong>{$lang->newpoints_action}:</strong></td>
<td class="trow2" width="50%"><select name="bank_action" id="choose_action">
		<option value="1">{$lang->newpoints_bank_withdraw}</option>
		<option value="2">{$lang->newpoints_bank_deposit}</option>
		<option value="3">{$lang->newpoints_bank_borrow}</option>
		<option value="4">{$lang->newpoints_bank_payborrow}</option>
	                          </select></td>
</tr>
<tr>
<td class="trow2" width="100%" colspan="2">{$lang->newpoints_bank_performaction} <b id="change_text">0</b> {$lang->newpoints_bank_points}!</td>
</tr>
<tr>
<td class="tfoot" width="100%" colspan="2" align="center"><input type="submit" name="submit" value="{$lang->newpoints_submit}"></td>
</tr>
</table>
</form>
</td>
</tr>
</table>
{$footer}
</body>
</html>');
	newpoints_add_template('newpoints_bank_profile', '<tr>
	<td class="trow2"><strong>{$currency} {$lang->newpoints_bank_profileinbank}:</strong></td>
	<td class="trow2"><a href="{$mybb->settings[\'bburl\']}/newpoints.php?action=bank">{$points_inbank}</a></td>
</tr>');
	newpoints_add_template('newpoints_bank_stats_loan', '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="4"><strong>{$lang->newpoints_bank_stats_lastloans}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->newpoints_bank_stats_loanuser}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->newpoints_bank_stats_amountborrowed}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->newpoints_bank_stats_amountpaid}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->newpoints_bank_stats_loandata}</strong></td>
</tr>
{$last_bankloans}
</table><br />');
	newpoints_add_template('newpoints_bank_stats_loans', '<tr>
<td class="{$namebankclass}" width="30%">{$bank_loanstats[\'user\']}</td>
<td class="{$namebankclass}" width="30%" align="center">{$bank_loanstats[\'sum_loan\']}</td>
<td class="{$namebankclass}" width="20%" align="center">{$bank_loanstats[\'sum_paid\']}</td>
<td class="{$namebankclass}" width="20%" align="center">{$bank_loanstats[\'date\']}</td>
</tr>');
	newpoints_add_template('newpoints_bank_stats_noloan', '<tr>
<td class="trow1" width="100%" colspan="4">{$lang->newpoints_bank_stats_noloans}</td>
</tr>');
    
	// se adauga si un nou task
    $row = array (
        "title" => "NewPoints Bank System",
        "description" => "This task work everyday and it is a part of Bank System plugin for NewPoints.",  
        "file" => "newpoints_bank_task",
        "minute" => "0",
        "hour" => "12",
        "day" => "*",
        "month" => "*",
        "weekday" => "*",
        "nextrun" => 0,
        "lastrun" => 0,
        "enabled" => 0,
        "logging" => 1,
        "locked" => 0,
                        );
	$db->insert_query('tasks', $row);
}

function newpoints_bank_uninstall()
{
	global $db;

	// se sterg cateva campurile din tabela "users"
	if ($db->field_exists('newpoints_bankoffset', 'users')) {
		$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` DROP `newpoints_bankoffset`");	
	}
	if ($db->field_exists('newpoints_bankbasetime', 'users')) {
		$db->write_query("ALTER TABLE `" . TABLE_PREFIX . "users` DROP `newpoints_bankbasetime`");	
	}
	
	// se sterge o tabela din baza de date
	if ($db->table_exists('newpoints_loans')) {
        $db->drop_table('newpoints_loans');
    }
	
	// se sterg toate sabloanele introduse in baza de date de modificarea de fata
	newpoints_remove_templates("'newpoints_bank','newpoints_bank_profile','newpoints_bank_stats_loan','newpoints_bank_stats_loans','newpoints_bank_stats_noloan'");
	
	// se sterge un task asociat acestei modificari
	$db->delete_query("tasks", "file = 'newpoints_bank_task'");
}

function newpoints_bank_is_installed()
{
	global $db;
	
	return ($db->table_exists('newpoints_loans')) ? TRUE : FALSE;
}

function newpoints_bank_activate()
{
	global $db, $mybb;
	
    // se activeaza task-ul asociat
    $db->query("UPDATE " . TABLE_PREFIX . "tasks SET enabled = 1 WHERE file = 'newpoints_bank_task'"); 
	
	// setarile modificarii
	newpoints_add_setting('newpoints_bank_enable_showprofilebank', 'newpoints_bank', 'Bank Points in user profile?', 'Do you want to display the number of bank points for a member in his profile?', 'yesno', 0, 1);
	newpoints_add_setting('newpoints_bank_dep_fee', 'newpoints_bank', 'Deposit Fee', 'The amount of points required to deposit points in the bank. This amount is taken from user when he deposit points into bank. (Default : 10)', 'text', '10.00', 2);
	newpoints_add_setting('newpoints_bank_with_fee', 'newpoints_bank', 'Withdraw Fee', 'The amount of points required to withdraw points from the bank. This amount is taken from user when he withdraw points from bank. (Default : 10)', 'text', '10.00', 3);
	newpoints_add_setting('newpoints_bank_rate', 'newpoints_bank', 'Bank Interest Rate per day', 'The amount (% of points in bank) of interest given out daily. (Default : 3.00)', 'text', '3.00', 4);
	newpoints_add_setting('newpoints_bank_maxbank', 'newpoints_bank', 'Maximum Amount of Money in the Bank', 'Please insert the maximum amount that a user can have it into our bank. (Default : 1000000)', 'text', '1000000', 5);
	newpoints_add_setting('newpoints_bank_usergroupsborrow', 'newpoints_bank', 'Who can take Money from the Bank?', 'What usergroups can borrow money from the bank? (Default : 2,3,4,5)', 'text', '2,3,4,5', 6);
	newpoints_add_setting('newpoints_bank_commission', 'newpoints_bank', 'Bank commission per day', 'What commission will be given daily for an active loan. (Default : 8.00)', 'text', '8.00', 7);
	newpoints_add_setting('newpoints_bank_max_borrow', 'newpoints_bank', 'Maximum Amount that can be Borrowed', '
What is the maximum amount that can be borrowed by an user from the bank? (Default : 1000)', 'text', '1000', 8);
	newpoints_add_setting('newpoints_bank_max_stats', 'newpoints_bank', 'Display active Loans in NewPoints Stats', 'Set the number of loans that will be shown on the statistics NewPoints page. NOTE: If this field is set to 0 the Bank table stats will not be displayed! (Default : 10)', 'text', '10', 9);
	newpoints_add_setting('newpoints_bank_enable_resaccesdonation', 'newpoints_bank', 'Restrict access to Donation Page', 'Enable restrict acces to donation page for all the members who have an active loan. (Default : Yes)', 'yesno', 1, 10);
	newpoints_add_setting('newpoints_bank_restrictpostingforums', 'newpoints_bank', 'Restrict Posting in Forums', 'This is a penalty method if a user don`t pay his loan! User not be able to post a new thread or a new reply! (insert forum ids by separating each with comma)', 'text', '', 11);
	newpoints_add_setting('newpoints_bank_restrictviewingforums', 'newpoints_bank', 'Restrict Viewing Forums', 'This is a penalty method if a user don`t pay his loan! (insert forum ids by separating each with comma)', 'text', '', 12);

	// se reconstruiesc toate setarile
	$version = version_compare(NEWPOINTS_VERSION, '1.9.5');
	if ($version == 0) {
		newpoints_rebuild_settings_cache();
	} else {
		rebuild_settings();
	}
	 
	// se fac modificari in sistemul de sabloane 
	newpoints_find_replace_templatesets('newpoints_statistics', '#'.preg_quote('width="60%">').'#', 'width="60%">{$newpoints_bank_lastlons}');
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	find_replace_templatesets("member_profile", '#'.preg_quote('{$newpoints_profile}').'#', '{$newpoints_profile}'.'{$newpoints_bank_profile}');
}

function newpoints_bank_deactivate()
{
	global $db, $mybb;
	
    // se dezactiveaza task-ul asociat
    $db->query("UPDATE " . TABLE_PREFIX . "tasks SET enabled = 0 WHERE file = 'newpoints_bank_task'"); 
	
	// se sterg toate setarile din baza de date
	newpoints_remove_settings("'newpoints_bank_dep_fee','newpoints_bank_with_fee','newpoints_bank_rate','newpoints_bank_maxbank','newpoints_bank_commission','newpoints_bank_max_borrow','newpoints_bank_usergroupsborrow','newpoints_bank_max_stats','newpoints_bank_enable_resaccesdonation','newpoints_bank_enable_showprofilebank','newpoints_bank_restrictviewingforums','newpoints_bank_restrictpostingforums'");
	
	// se reconstruiesc toate setarile
	$version = version_compare(NEWPOINTS_VERSION, '1.9.5');
	if ($version == 0) {
		newpoints_rebuild_settings_cache();
	} else {
		rebuild_settings();
	}
	
	// se sterge cache-ul folosit pentru penalitati
    $db->delete_query('datacache', "title = 'newpoints_bank'");
	    
    // se fac modificari in sistemul de sabloane 
	newpoints_find_replace_templatesets('newpoints_statistics', '#'.preg_quote('{$newpoints_bank_lastlons}').'#', '');
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	find_replace_templatesets("member_profile", '#'.preg_quote('{$newpoints_bank_profile}').'#', '', 0);
}

function newpoints_bank_backup(&$backup_fields)
{
	global $db, $table;
	
	// campurile din tabela "users" care au fost adaugate
	$backup_fields[] = 'newpoints_bankoffset';
	$backup_fields[] = 'newpoints_bankbasetime';
}

/*
 * FUNCTII PRINCIPALE
 */
function newpoints_bank_menu(&$menu)
{
	global $mybb, $lang;
	
	newpoints_lang_load('newpoints_bank');
	
	if ($mybb->input['action'] == 'bank')
		$menu[] = "&raquo; <a href=\"{$mybb->settings['bburl']}/newpoints.php?action=bank\">{$lang->newpoints_bank}</a>";
	else
		$menu[] = "<a href=\"{$mybb->settings['bburl']}/newpoints.php?action=bank\">{$lang->newpoints_bank}</a>";
}
function newpoints_bank_update_values($amount, $uid = 0)
{
	global $mybb, $db;
	
	// care este suma maxima pe care cineva o poate avea in banca
	$max = (float)$mybb->settings['newpoints_bank_maxbank'];
	// care este dobanda bancii pe zi
	$rate = number_format((float)$mybb->settings['newpoints_bank_rate'] / 100, 4);
	// variabila ce tine timpul actual
	$now = TIME_NOW;
	
	// se face o actualizare simpla sau multipla
	if (!is_array($uid))
	{
		$uid = (int)$uid;
		if ($uid <= 0) {
			return FALSE;
		}
		
		// se face actualizarea (inclusiv a dobanzii)
		$db->write_query("
			UPDATE " . TABLE_PREFIX . "users  
			SET newpoints_bankoffset = LEAST({$max}, GREATEST(0, (newpoints_bankoffset * (1 + {$rate} * ({$now} - newpoints_bankbasetime) / 86400)) + {$amount})), newpoints_bankbasetime = {$now}  
			WHERE uid = {$uid}
		");
	} else {
		// se face o actualizare pentru mai multi utilizatori
		$users = @implode(',', $uid);
		
		$db->write_query("
			UPDATE " . TABLE_PREFIX . "users  
			SET newpoints_bankoffset = LEAST({$max}, GREATEST(0, (newpoints_bankoffset * (1 + {$rate} * ({$now} - newpoints_bankbasetime) / 86400)) + {$amount})), newpoints_bankbasetime = {$now}  
			WHERE uid IN({$users})
		");
	}
	
	// daca se ajunge aici inseamna ca actualizarea s-a realizat cu succes
	return TRUE;
}
function newpoints_bank_page()
{
	global $mybb, $db, $lang, $cache, $theme, $header, $templates, $plugins, $headerinclude, $footer, $options;
	
	// verificam ca utilizatorul sa fie inregistrat
	if (!$mybb->user['uid']) {
		return;	
	}
	
	// se incarca fisierul de limba
	newpoints_lang_load('newpoints_bank');
	
	// ne aflam pe pagina principala?
	if ($mybb->input['action'] == "bank")
	{
		$plugins->run_hooks('newpoints_bank_start');
		
		// se formateaza punctele utilizatorului
		$mybb->user['newpoints'] = newpoints_format_points($mybb->user['newpoints']);
		
		// cati bani are utilizatorul in banca
		$mybb->user['newpoints_bankoffset'] = newpoints_format_points($mybb->user['newpoints_bankoffset']);
		
		// cand s-a actualizat ultima oara dobanda
		$lastupdate = $lang->newpoints_bank_user_lastupdaten;
		if ($mybb->user['newpoints_bankbasetime'] > 0) {
			$lastupdate = my_date($mybb->settings['dateformat'], $mybb->user['newpoints_bankbasetime']) . ' ' . my_date($mybb->settings['timeformat'], $mybb->user['newpoints_bankbasetime']);	
		}
		$lastupdate = $lang->sprintf($lang->newpoints_bank_user_lastupdate, $lastupdate);
		
		// care este care trebuie platita
		$lang->newpoints_bank_fee = $lang->sprintf($lang->newpoints_bank_fee, newpoints_format_points($mybb->settings['newpoints_bank_dep_fee']), newpoints_format_points($mybb->settings['newpoints_bank_with_fee']), $mybb->settings['newpoints_bank_rate'], $mybb->settings['newpoints_bank_commission']);	
		
		// are utilizatorul imprumuturi?
		$query = $db->simple_select('newpoints_loans', '*', "username = '".$mybb->user['username'] . "'","");
		if ($row = $db->fetch_array($query)) {
			// cat mai e de plata?
            $difference = floatval($row['sum_loan']) - floatval($row['sum_paid']);
			// data realizarii imprumutului
            $date = my_date($mybb->settings['dateformat'], $row['date'], '', 0);
			// data ultimei actualizari a comisionului
			$datec = my_date($mybb->settings['dateformat'], $row['datec'], '', 0) . ' ' . my_date($mybb->settings['timeformat'], $row['datec']);
			// detalii despre imprumut sunt afisate utilizatorului
            $ihaveborrow = $lang->sprintf($lang->newpoints_bank_user_borrow_remain, newpoints_format_points($difference), newpoints_format_points($row['sum_loan']), $date, $datec);
        } else {
			// nu are niciun imprumut facut
            $ihaveborrow = $lang->newpoints_bank_user_noborrow;
        }
		
		// cod javascript
        $javascript = "<script type='text/javascript'>
	Event.observe(document, 'dom:loaded', function() {
		function getAmount(action, amount) {
    		switch(action) {
    			case '1':
        			tax = " . $mybb->settings['newpoints_bank_with_fee'] . ";
        			calc = parseFloat(tax) + parseFloat(amount);
        			return calc;
    			break
    			case '2':
        			tax = " . $mybb->settings['newpoints_bank_dep_fee'] . ";
        			calc = parseFloat(amount) + parseFloat(tax);
        			return calc;
    			break
    			case '3':
        			tax = 0;
        			return tax;
    			break
    			case '4':
        			tax = 0;
        			calc = parseFloat(amount) + parseFloat(tax);
        			return calc;
    			break
   			}
		}		
		$('choose_action').observe('change', function(e) {
			var action = this.options[this.selectedIndex].value;
			var amount = $('amount').value;
			$('change_text').innerHTML = getAmount(action, amount);
		});
		$('amount').observe('change', function(e) {
			var selbox = $('choose_action');
			var action = selbox.options[selbox.selectedIndex].value;
			var amount = this.value;
			$('change_text').innerHTML = getAmount(action, amount);
		});
	});
</script>";

		$plugins->run_hooks('newpoints_bank_end');
		
		// se evalueaza pagina
        eval("\$page = \"".$templates->get('newpoints_bank')."\";");
		
		// se afiseaza pagina pe ecran
		output_page($page);
	} elseif (($mybb->input['action'] == "do_bank")) {
		verify_post_check($mybb->input['postcode']);
		
		$plugins->run_hooks('newpoints_do_bank_start');
		
		// se citesc informatiile din cache
		$datacache = $mybb->cache->read('newpoints_bank');
		if ($datacache && isset($datacache['bank_sum'])) {
			// valoarea din cache
			$sum = (float)$datacache['bank_sum'];
		} else {
			// se apeleaza la o valoare standard
			$sum = 1000000;
		}
		
		$array_bp = array();
		$array_bv = array();
		
		if ( ! in_array($mybb->input['bank_action'], array(1 , 2, 3, 4)) ) {
			error($lang->newpoints_bank_invalid_action);
		} else {
			// exista actiunea aleasa
			switch ($mybb->input['bank_action']) {
				// se scot bani din banca
				case 1:
					// cati bani se scot din banca
					$amount = abs((float)$mybb->input['amount']);
					// suma totala care se va extrage
					$total = $amount + (float)$mybb->settings['newpoints_bank_with_fee'];
					// exista acesti bani in banca?
					if ($total > $mybb->user['newpoints_bankoffset']) {
						error($lang->newpoints_bank_not_enough_bank);
					}			
					// se adauga banii in buzunarul utilizatorului
					newpoints_addpoints($mybb->user['uid'], $amount);
					// se face update la banii in contul utilizatorului
					newpoints_bank_update_values(-$total, $mybb->user['uid']);
					// se adauga un log in sistem
					newpoints_log('Bank Withdraw', $lang->sprintf($lang->newpoints_bank_withdrawn_log, newpoints_format_points($amount)));
					// se face o redirectionare
					redirect($mybb->settings['bburl'] . "/newpoints.php?action=bank", $lang->sprintf($lang->newpoints_bank_withdrawn, newpoints_format_points($amount)));	
				break;
				// se depoziteaza bani in banca
				case 2:		
					// nu exista bani in buzunar destui pentru a face depozitul
					$amount = abs((float)$mybb->input['amount']);
					$total = $amount + (float)$mybb->settings['newpoints_bank_dep_fee'];
					if ($total > $mybb->user['newpoints']) {
						error($lang->newpoints_bank_not_enough_hand);
					}
					// se iau banii din buzunarul utilizatorului
					newpoints_addpoints($mybb->user['uid'], -$total);
					// se actualizeaza banii din contul banii
					newpoints_bank_update_values($amount, $mybb->user['uid']);
					// se adauga un log in sistem
					newpoints_log('Bank Deposit', $lang->sprintf($lang->newpoints_bank_deposited_log, newpoints_format_points($amount)));
					// se face o redirectionare
					redirect($mybb->settings['bburl'] . "/newpoints.php?action=bank", $lang->sprintf($lang->newpoints_bank_deposited, newpoints_format_points($amount)));
				break;
            	// se imprumuta bani din banca
            	case 3:
					// verificam daca grupul din care face parte utilizatorul poate face imprumuturi
					$groups = @explode(',', $mybb->settings['newpoints_bank_usergroupsborrow']);
                	if ( ! in_array($mybb->user['usergroup'], $groups)) {
                    	error($lang->newpoints_bank_not_enabled_borrow);   
                	}
					// suma pe care dorim sa o imprumutam
                	$amount = abs((float)$mybb->input['amount']);
					// verificam daca suma de imprumut e mai mica decat cea maxim admisa
                	if ($amount > (float)$mybb->settings['newpoints_bank_max_borrow']) {
                    	error($lang->sprintf($lang->newpoints_bank_max_borrowpoints, $mybb->settings['newpoints_bank_max_borrow']));
                	}
					// verificam daca utilizatorul are deja un imprumut
                	if ($db->num_rows($db->simple_select('newpoints_loans', '*', "username = '{$mybb->user['username']}'")) > 0){
                    	error($lang->newpoints_bank_another_borrow);
                	}				
					// are banca destui bani de a plati acel imprumut
                	if ($amount > $sum) {
                    	error($lang->newpoints_bank_not_bank_funds);
                	}
					// informatii despre imprumut
                	$infos = array(
						"date" => TIME_NOW,
						"datec" => TIME_NOW,
                        "username" => $mybb->user['username'],  
                        "sum_loan" => $amount,
                        "sum_paid" => 0,
                    );      
					$db->insert_query('newpoints_loans', $infos);
					// se actualizeaza banii din banca
					$sum -= $amount;
                	// se actualizeaza banii din buzunarul utilizatorului
                	$db->write_query("
						UPDATE " . TABLE_PREFIX . "users 
						SET newpoints = newpoints + {$amount} 
						WHERE uid = " . (int)$mybb->user['uid']
					);
                	// se introduce un nou log in sistem
                	newpoints_log('Bank New Borrow', $lang->sprintf($lang->newpoints_bank_newborrow_log, newpoints_format_points($amount)));
                	// se realizeaza o redirectionare
                	redirect($mybb->settings['bburl'] . "/newpoints.php?action=bank", $lang->sprintf($lang->newpoints_bank_borrowed, newpoints_format_points($amount)));
            	break;
            	// se plateste un imprumut sau o parte din acesta
            	case 4:
					//se verifica daca ai ce sa platesti
					$query = $db->simple_select('newpoints_loans', '*', " username = '" . $mybb->user['username'] . "'", array('limit' => 1));
                	if ($db->num_rows($query) == 0) {
                    	error($lang->newpoints_bank_not_active_borrow);
                	}
					// cat se doreste a se plati
                	$amount = abs((float)$mybb->input['amount']);
					// detalii despre imprumut
                	$borrow = $db->fetch_array($query);
					// cat trebuie sa se plateasca
					$rate = number_format((float)$mybb->settings['newpoints_bank_commission'] / 100, 4);
					$difference = ((float)$borrow['sum_loan'] - (float)$borrow['sum_paid']) * (1 + $rate * (TIME_NOW - $borrow['datec']) / 86400);
                	// va mai ramane ceva de plata?
                	if ($amount >= $difference) {
						// nu mai ramane nimic de plata
                    	newpoints_addpoints($mybb->user['uid'], -(float)$difference, 1, true);  
						// se sterge imprumutul din baza de date 
						$db->delete_query('newpoints_loans', "username = '" . $mybb->user['username'] . "'");
						// se cresc banii din banca
						$sum += $difference;
						// ne asiguram ca se scot penalitatile
						array_push($array_bp, $mybb->user['uid']);
						array_push($array_bv, $mybb->user['uid']);
						// se face redirectionarea
                    	redirect($mybb->settings['bburl']."/newpoints.php?action=bank", $lang->sprintf($lang->newpoints_bank_payborrowed, newpoints_format_points($difference))); 
                	} else {
						// a mai ramas ceva de plata
                    	newpoints_addpoints($mybb->user['uid'], -(float)$amount, 1, true); 
                    	// se cresc banii din banca
						$sum += $amount;
                    	// se actualizeaza suma platita de utilizator 
                    	$db->write_query("
							UPDATE " . TABLE_PREFIX . "newpoints_loans 
							SET sum_loan = sum_paid + {$difference}, sum_paid = sum_paid + {$amount}, datec = " . TIME_NOW . " 
							WHERE username = '" . $mybb->user['username'] . "'
						");     
						// se realizeaza redirectionarea utilizatorului
                    	redirect($mybb->settings['bburl'] . "/newpoints.php?action=bank", $lang->sprintf($lang->newpoints_bank_payborrowed, newpoints_format_points($amount)));
                	}
            	break;
			}
		}
		
		// se actualizeaza banii din banca
		if ($datacache) {
			// are si banca o limita de bani
			if (isset($datacache['bank_tot']) && $sum > $datacache['bank_tot']) {
				$datacache['bank_sum'] = $datacache['bank_tot'];
			}
			// ne asiguram ca se scot penalizarile instant
			if (isset($datacache['bp']) && is_array($datacache['bp'])) {
				$datacache['bp'] = array_diff($datacache['bp'], $array_bp);
			} else {
				$datacache['bp'] = array();
			}
			if (isset($datacache['bv']) && is_array($datacache['bv'])) {
				$datacache['bv'] = array_diff($datacache['bv'], $array_bv);
			} else {
				$datacache['bv'] = array();
			}
		} 
		$datacache['bank_sum'] = $sum;
		$mybb->cache->update('newpoints_bank', $datacache);
		
		$plugins->run_hooks('newpoints_do_bank_end');
	}
}

function newpoints_bank_profile()
{
    global $db, $mybb, $templates, $memprofile, $newpoints_bank_profile, $lang;
    
	if ($mybb->settings['newpoints_bank_enable_showprofilebank'] == 1) {
		// se incarca fisierul de limba
        newpoints_lang_load('newpoints_bank');
   	    $currency = $mybb->settings['newpoints_main_curname'];
        $points_inbank = newpoints_format_points($memprofile['newpoints_bankoffset']);  
        eval("\$newpoints_bank_profile = \"".$templates->get('newpoints_bank_profile')."\";"); 
    } else {
        eval("\$newpoints_bank_profile = '';");    
    }
}

function newpoints_bank_stats()
{
    global $db, $mybb, $lang, $templates, $cache, $theme, $last_bankloans, $newpoints_bank_lastlons;
	
    if (intval($mybb->settings['newpoints_bank_max_stats']) == 0) {
        eval("\$newpoints_bank_lastlons = '';");    
    } else {
		$query = $db->write_query("
    		SELECT u.uid AS uid, l.*
        	FROM " . TABLE_PREFIX . "newpoints_loans l
        	LEFT JOIN " . TABLE_PREFIX . "users u ON (u.username = l.username)
			ORDER BY l.date DESC LIMIT " . intval($mybb->settings['newpoints_bank_max_stats'])
		);
   		$last_bankloans = '';
    	while($row = $db->fetch_array($query)) {
			$namebankclass = alt_trow();
			$link = build_profile_link(htmlspecialchars_uni($row['username']), intval($row['uid']));
			$bank_loanstats['user'] = $link;
			$bank_loanstats['sum_loan'] = number_format($row['sum_loan'], 2, '.', '');
  			$bank_loanstats['sum_paid'] = number_format($row['sum_paid'], 2, '.', '');
			$bank_loanstats['date'] = my_date($mybb->settings['dateformat'], (int)$row['date'], '', false);
			eval("\$last_bankloans .= \"" . $templates->get('newpoints_bank_stats_loans') . "\";");
		}
		if (!$last_bankloans) {
			eval("\$last_bankloans = \"" . $templates->get('newpoints_bank_stats_noloan') . "\";");
    	}
    	eval("\$newpoints_bank_lastlons = \"" . $templates->get('newpoints_bank_stats_loan') . "\";");
    }
}

/*
 * PANOU DE ADMINISTRARE
 */
function newpoints_bank_finance_do()
{
	global $db, $mybb, $form, $lang;
	
	// se incarca fisierul de limba
	newpoints_lang_load('newpoints_bank');
	
	// ce actiune se petrece
	if ($mybb->input['action'] == 'bank_finance') {
		if($mybb->input['no']) {
			admin_redirect("index.php?module=newpoints-maintenance");
		}
		if($mybb->request_method == "post") {
			$mybb->input['bank_finance'] = abs((float)$mybb->input['bank_finance']);
			if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'] || !$mybb->input['bank_finance']) {
				$mybb->request_method = "get";
				flash_message($lang->newpoints_error, 'error');
				admin_redirect("index.php?module=newpoints-maintenance");
			}
		
			if ($mybb->input['bank_finance'] < 0) {
				flash_message($lang->newpoints_bank_finance_negative, 'error');
				admin_redirect("index.php?module=newpoints-maintenance");
			}

			// se prelucreaza informatiile din cache
			$datacache = $mybb->cache->read('newpoints_bank');
			if ($datacache && isset($datacache['bank_tot'])) {
				$datacache['bank_sum'] += $mybb->input['bank_finance'] - $datacache['bank_tot'];
			} else {
				$datacache['bank_sum'] += $mybb->input['bank_finance'];
			}
			$datacache['bank_tot'] = $mybb->input['bank_finance'];
			// se actualizeaza toate datele
			$mybb->cache->update('newpoints_bank', $datacache);
				
			flash_message($lang->newpoints_bank_finance_success, 'success');
			admin_redirect('index.php?module=newpoints-maintenance');
		} else {
			$mybb->input['bank_finance'] = (float)$mybb->input['bank_finance'];
			
			$form = new Form("index.php?module=newpoints-maintenance&amp;action=bank_finance&amp;bank_finance={$mybb->input['bank_finance']}&amp;my_post_key={$mybb->post_code}", 'post');
			
			echo "<div class=\"confirm_action\">\n";
			echo "<p>{$lang->newpoints_bank_finance_confirm}</p>\n";
			echo "<br />\n";
			echo "<p class=\"buttons\">\n";
			echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
			echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
			echo "</p>\n";
			echo "</div>\n";
			
			$form->end();
		}
	}
}

function newpoints_bank_finance() 
{
 	global $db, $mybb, $form, $lang;
	
	// se incarca fisierul de limba
	newpoints_lang_load('newpoints_bank');
	
	echo '<br/>';
	
	// care este valoarea curenta a fondului bancii
	$datacache = $mybb->cache->read('newpoints_bank');
	$value = 1000000;
	if ($datacache && isset($datacache['bank_tot'])) {
		$value = $datacache['bank_tot'];
	}
	
	$form = new Form("index.php?module=newpoints-maintenance&amp;action=bank_finance", "post", "newpoints");
	echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
	$form_container = new FormContainer($lang->newpoints_bank_finance_title);
	$form_container->output_row(
		$lang->newpoints_bank_finance, 
		$lang->newpoints_bank_finance_desc, 
		$form->generate_text_box(
			'bank_finance', 
			$value, 
			array('id' => 'bank_finance')
		), 
		'bank_finance'
	);
	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
	$buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
	$form->output_submit_wrapper($buttons);
	$form->end();
}

function newpoints_bank_edituser_do()
{
	global $updates, $mybb;	
	
	$updates['newpoints_bankoffset'] = (float)$mybb->input['bankmoney'];
	$updates['newpoints_bankbasetime'] = TIME_NOW;
}

function newpoints_bank_edituser()
{
	global $db, $mybb, $form, $lang, $user;
	
	// se incarca fisierul de limba
	newpoints_lang_load('newpoints_bank');
	
	// se adauga un nou formular
	$form_container = new FormContainer($lang->newpoints_bank_money_inbank);
	$form_container->output_row(
		$lang->newpoints_bank_money_inbank, 
		$lang->newpoints_bank_money_inbank_desc, 
		$form->generate_text_box(
			'bankmoney', 
			$user['newpoints_bankoffset'], 
			array('id' => 'bankmoney')
		), 
		'bankmoney'
	);
	$form_container->end();
}

/*
 * RESTRICTII
 */
function newpoints_bank_restrictaccestodon()
{
    global $db, $lang, $mybb;
	
	if ($mybb->settings['newpoints_bank_enable_resaccesdonation'] == 0)
		return;
			
    if ($db->num_rows($db->simple_select('newpoints_loans', '*', "username = '" . $mybb->user['uid'] . "'","")) > 0) {
		// se incarca fisierul de limba
		newpoints_lang_load('newpoints_bank');
		// se afiseaza eroarea pe ecran
        error($lang->newpoints_bank_restrict_donation);
    }    
}
function newpoints_bank_postingpenalties()
{
	global $db, $mybb, $fid, $lang, $cache;
	
	// citim datele din cache pentru a spori viteza de lucru
	$penalties = $cache->read('newpoints_bank');
	if ( ! $penalties || ! is_array($penalties)) {
		return;
	}
	
	// daca utilizatorul este sanctionat
	if (in_array($mybb->user['uid'], $penalties['bp'])) {
		$forums_array = explode(',', $mybb->settings['newpoints_bank_restrictpostingforums']);
		// ce forumuri nu are dreptul de a vedea!?
        if (in_array($fid, $forums_array)) {
       		newpoints_lang_load('newpoints_bank');
            error($lang->newpoints_bank_restrictpostacces);
        }
    }
}
function newpoints_bank_viewingpenalties()
{
	global $db, $mybb, $fid, $lang, $cache;
	
	// citim datele din cache pentru a spori viteza de lucru
	$penalties = $cache->read('newpoints_bank');
	if ( ! $penalties || ! is_array($penalties)) {
		return;
	}
	
	// daca utilizatorul este sanctionat
	if (in_array($mybb->user['uid'], $penalties['bv'])) {
		$forums_array = explode(',', $mybb->settings['newpoints_bank_restrictviewingforums']);
		// ce forumuri nu are dreptul de a vedea!?
        if (in_array($fid, $forums_array)) {
       		newpoints_lang_load('newpoints_bank');
            error($lang->newpoints_bank_restrictviewacces);
        }
    }
}
?>