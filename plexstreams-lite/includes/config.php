<?php
$cfgFile = '/boot/config/plugins/plexstreams-lite/plexstreams-lite.cfg';
$cfg = file_exists($cfgFile) ? parse_ini_file($cfgFile) : [];
$cfg = array_merge([
    'HOST'  => 'http://192.168.10.5:32400',
    'TOKEN' => '',
], $cfg);
