<?php

use Symfony\Component\Finder\Finder;

require __DIR__ . '/vendor/autoload.php';

$startedtime = time();

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
    printf('Can\'t find files');
}

foreach ($resources as $resource) {
    $path = $resource->getRealPath();
    $filename = $resource->getBasename('.html');

    printf('Running for file %s...', $filename);

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

    $dom = new DOMDocument();
    $dom->loadHTML((string) $tidy);

    // Remove links to already packed styles in the head.
    /** @var \DOMElement $head */
    $head = $dom->getElementsByTagName('head')[0];
    // The iterator here gets thrown by removal of nodes.
    // See the comments on: https://www.php.net/manual/en/domnode.removechild.php (Accessed: 3/04/22).
    $links = $head->getElementsByTagName('link');
    $removeQueue = [];
    foreach ($links as $link) {
        /** @var \DOMElement $link */
        $removeQueue[] = $link;
    }
    foreach ($removeQueue as $toRemove) {
        /** @var \DOMElement $toRemove */
        $toRemove->parentNode->removeChild($toRemove);
    }

    // Rewrite links to use x-dictionary.
    foreach ($dom->getElementsByTagName('a') as $anchor) {
        /** @var \DOMElement $anchor */
        $href = $anchor->getAttribute('href');
        $newhref = 'x-dictionary:r:' . str_replace('.html', '', $href);
        $anchor->setAttribute('href', $newhref);
    }

    $content = $dom->saveXML($dom->getElementsByTagName('html')[0]);

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
        '{contents}' => $content,
    ]);

    file_put_contents(__DIR__ . '/PHPDict.xml', $result, FILE_APPEND);

    printf('   Done!!' . PHP_EOL);
}

$fileFooter = <<<XML
</d:dictionary>
XML;

file_put_contents(__DIR__ . '/PHPDict.xml', $fileFooter, FILE_APPEND);

printf(
    '---%sFINISHED PROCESSING! Took: %s seconds%s---%s',
    PHP_EOL,
    time() - $startedtime,
    PHP_EOL,
    PHP_EOL
);