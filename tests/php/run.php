<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/SimpleTestRunner.php';
require_once __DIR__ . '/EntryPayloadTest.php';

$runner = new SimpleTestRunner();
$runner->run(new EntryPayloadTest());
