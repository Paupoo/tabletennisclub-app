<?php

declare(strict_types=1);

namespace tests\Architecture;

/*
 * Eight of the nineteen breadcrumb rungs went through __(), eleven wrote their
 * label in English and shipped it straight to the screen: a French back office
 * showing "Users / List", and a Dutch one showing the same. The inconsistency
 * lived in the helper, so every page inherited it.
 */
it('translates every breadcrumb label', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/Breadcrumb.php');

    preg_match_all("/return \\\$this->add\(\s*('[^']*')/", $source, $matches);

    expect($matches[1])->toBe([], sprintf(
        "A breadcrumb label reaches the screen as written — wrap it in __():\n%s",
        implode("\n", $matches[1]),
    ));
});
