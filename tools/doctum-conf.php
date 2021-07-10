<?php

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()->name('*.php')->in('.')
    ->exclude('docs')->exclude('tools')
    ->exclude('vendor')->exclude('Config.php');

return new Doctum($iterator, [
    'title' => 'Andromeda Server API',
    'build_dir' => __DIR__.'/../docs/doctum_build',
    'cache_dir' => __DIR__.'/../docs/doctum_cache'
]);