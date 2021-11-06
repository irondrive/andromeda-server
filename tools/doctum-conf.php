<?php

use Doctum\Doctum;
use Doctum\Parser\Filter\TrueFilter;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()->name('*.php')->in('Andromeda')
    ->exclude('Apps/Testutil')
    ->exclude('DBConfig.php');

$doctum = new Doctum($iterator, [
    'title' => 'Andromeda Server API',
    'build_dir' => __DIR__.'/../docs/doctum_build',
    'cache_dir' => __DIR__.'/../docs/doctum_cache'
]);

$doctum['filter'] = function () {
    return new TrueFilter();
};

return $doctum;
