<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');

//Include module
include_once('wirecardinduxive.php');
$module = new WirecardInduxive();

echo $module->responsePage();

include(dirname(__FILE__).'/../../footer.php');