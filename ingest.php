<?php

use Symfony\Component\Finder\Finder;

require __DIR__ . '/vendor/autoload.php';

function pntf(string $string, ...$values): void
{
    printf($string . \PHP_EOL, ...$values);
}

$fileHeader = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE inline_dtd[
    <!ENTITY nbsp "&#160;">
]>
<!--
    This can be built using Dictionary Development Kit.
-->
<d:dictionary xmlns="http://www.w3.org/1999/xhtml" xmlns:d="http://www.apple.com/DTDs/DictionaryService-1.0.rng">
XML;

file_put_contents(__DIR__ . '/PHPDict.xml', $fileHeader);

$resources = (new Finder())
    ->files()
    ->in(__DIR__ . '/resources')
    ->name('*.html');

if (!$resources->hasResults()) {
    pntf('Can\'t find files');
}

foreach ($resources as $resource) {
    $path = $resource->getRealPath();
    $filename = $resource->getBasename('.html');

    pntf('Running for file %s..', $filename);
    $startedtime = time();

    // Convert HTML to XHTML
    $config = [
        'output-xhtml' => true,
        'clean' => true,
        'wrap-php' => true,
        'doctype' => 'omit',
        'quote-nbsp' => false
    ];

    $tidy = new tidy();
    $tidy->parseFile($path, $config);
    $tidy->cleanRepair();

    $template = <<<XML
    <d:entry id="{id}" d:title="{title}">
        <d:index d:value="{fulldex}"/>
        <d:index d:value="{index}"/>
        {contents}
    </d:entry>
    XML;

    $filenamearr = explode('.', $filename);

    $result = strtr($template, [
        '{id}' => $filename,
        '{title}' => $filename,
        '{fulldex}' => $filename,
        '{index}' => array_pop($filenamearr),
        '{contents}' => $tidy,
    ]);

    file_put_contents(__DIR__ . '/PHPDict.xml', $result, FILE_APPEND);

    pntf(
        'Done for file %s, Took: %s',
        $filename,
        time() - $startedtime
    );
}

$fileFooter = <<<XML
</d:dictionary>
XML;

file_put_contents(__DIR__ . '/PHPDict.xml', $fileFooter, FILE_APPEND);
