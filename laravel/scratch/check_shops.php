<?php
$columns = DB::select("PRAGMA table_info('shops')");
foreach($columns as $c) {
    echo $c->name . PHP_EOL;
}
