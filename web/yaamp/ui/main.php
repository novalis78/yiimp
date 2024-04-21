<?php

require('misc.php');
echo <<<END

<html class="no-js" lang="en-US">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta name="description" content="Join the Marscoin Mining Pool to contribute to Mars exploration. Participate anonymously, switch pools automatically, and maximize your mining profit!">
<meta name="keywords" content="marscoin, cryptocurrency, mining pool, bitcoin, altcoin, profit switching, scrypt mining">

<!-- Open Graph data -->
<meta property="og:title" content="Marscoin Mining Pool" />
<meta property="og:type" content="website" />
<meta property="og:url" content="http://mining-mars.com/" />
<meta property="og:image" content="http://www.mining-mars/images/mining.png" />
<meta property="og:description" content="Join the Marscoin Mining Pool to contribute to Mars exploration. Participate anonymously, switch pools automatically, and maximize your mining profit!" />
<meta property="og:site_name" content="Marscoin Mining Pool" />

<!-- Twitter Card data -->
<meta name="twitter:card" content="Mining on Mars for fun and prosperity">
<meta name="twitter:site" content="@marscoinorg">
<meta name="twitter:title" content="Marscoin Mining Pool">
<meta name="twitter:description" content="Join the Marscoin Mining Pool to contribute to Mars exploration. Participate anonymously, switch pools automatically, and maximize your mining profit!">
<meta name="twitter:creator" content="@author_handle">
<meta name="twitter:image:src" content="http://mining-mars/images/mining.png">

<title>Marscoin Mining Pool</title>

END;

$pageTitle = empty($this->pageTitle) ? YAAMP_SITE_NAME : YAAMP_SITE_NAME." - ".$this->pageTitle;

echo '<title>'.$pageTitle.'</title>';

echo CHtml::cssFile("/extensions/jquery/themes/ui-darkness/jquery-ui.css");
echo CHtml::cssFile('/yaamp/ui/css/main.css');
echo CHtml::cssFile('/yaamp/ui/css/table.css');

//echo CHtml::scriptFile('/extensions/jquery/js/jquery-1.8.3-dev.js');
//echo CHtml::scriptFile('/extensions/jquery/js/jquery-ui-1.9.1.custom.min.js');

$cs = app()->getClientScript();
$cs->registerCoreScript('jquery.ui');
//$cs->registerScriptFile('/yaamp/ui/js/jquery.tablesorter.js', CClientScript::POS_END);

echo CHtml::scriptFile('/yaamp/ui/js/jquery.tablesorter.js');

// if(!controller()->admin)
// echo <<<end
// <script>
// (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
// (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
// m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
// })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

// ga('create', 'UA-58136019-1', 'auto');
// ga('send', 'pageview');

// $(document).ajaxSuccess(function(){ga('send', 'pageview');});

// </script>
// end;

echo "</head>";

///////////////////////////////////////////////////////////////

echo '<body class="page">';
echo '<a href="/site/mainbtc" style="display: none;">main</a>';

showPageHeader();
showPageContent($content);
showPageFooter();

echo "</body></html>";
return;

/////////////////////////////////////////////////////////////////////

function showItemHeader($selected, $url, $name)
{
	if($selected) $selected_text = "class='selected'";
	else $selected_text = '';

	echo "<span><a $selected_text href='$url'>$name</a></span>";
	echo "&nbsp;";
}

function showPageHeader()
{
	echo '<div class="tabmenu-out">';
	echo '<div class="tabmenu-inner">';

	echo '&nbsp;&nbsp;<a href="/">'.YAAMP_SITE_NAME.'</a>';

	$action = controller()->action->id;
	$wallet = user()->getState('yaamp-wallet');
	$ad = isset($_GET['address']);

	showItemHeader(controller()->id=='site' && $action=='index' && !$ad, '/', 'Home');
	showItemHeader($action=='mining', '/site/mining', 'Pool');
	showItemHeader(controller()->id=='site'&&($action=='index' || $action=='wallet') && $ad, "/?address=$wallet", 'Wallet');
	showItemHeader(controller()->id=='stats', '/stats', 'Graphs');
	showItemHeader($action=='miners', '/site/miners', 'Miners');
	showItemHeader(controller()->id=='api', '/site/api', 'API');
	if (YIIMP_PUBLIC_EXPLORER)
		showItemHeader(controller()->id=='explorer', '/explorer', 'Explorers');

	if (YIIMP_PUBLIC_BENCHMARK)
		showItemHeader(controller()->id=='bench', '/bench', 'Benchs');

	if (YAAMP_RENTAL)
		showItemHeader(controller()->id=='renting', '/renting', 'Rental');

	if(controller()->admin)
	{
		if (isAdminIP($_SERVER['REMOTE_ADDR']) === false)
			debuglog("admin {$_SERVER['REMOTE_ADDR']}");

		showItemHeader(controller()->id=='coin', '/coin', 'Coins');
		showItemHeader($action=='common', '/site/common', 'Dashboard');
		showItemHeader(controller()->id=='site'&&$action=='admin', "/site/admin", 'Wallets');

		if (YAAMP_RENTAL)
			showItemHeader(controller()->id=='renting' && $action=='admin', '/renting/admin', 'Jobs');

		if (YAAMP_ALLOW_EXCHANGE)
			showItemHeader(controller()->id=='trading', '/trading', 'Trading');

		if (YAAMP_USE_NICEHASH_API)
			showItemHeader(controller()->id=='nicehash', '/nicehash', 'Nicehash');
	}

	echo '<span style="float: right;">';

	$mining = getdbosql('db_mining');
	$nextpayment = date('H:i T', $mining->last_payout+YAAMP_PAYMENTS_FREQ);
	$eta = ($mining->last_payout+YAAMP_PAYMENTS_FREQ) - time();
	$eta_mn = 'in '.round($eta / 60).' minutes';

	echo '<span id="nextpayout" style="font-size: .8em;" title="'.$eta_mn.'">Next Payout: '.$nextpayment.'</span>';

	echo "</div>";
	echo "</div>";
}

function showPageFooter()
{
	echo '<div class="footer">';
	$year = date("Y", time());

	echo "<p>&copy; $year ".YAAMP_SITE_NAME.' - '.
		'<a href="https://github.com/novalis78/yiimp">Open source Project</a></p>';

	echo '</div><!-- footer -->';
}


