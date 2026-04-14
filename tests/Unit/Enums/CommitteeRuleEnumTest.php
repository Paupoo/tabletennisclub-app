<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CommitteeRolesEnum;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNull;

describe('CommitteRolesEnum', function () {
    
    it('returns the correct string', function () {
        $this->assertEquals(__('President'), CommitteeRolesEnum::PRESIDENT->label());
        $this->assertEquals(__('Vice-President'), CommitteeRolesEnum::VICE_PRESIDENT->label());
        $this->assertEquals(__('Treasurer'), CommitteeRolesEnum::TREASURER->label());
        $this->assertEquals(__('Secretary'), CommitteeRolesEnum::SECRETARY->label());
        $this->assertEquals(__('Administrator'), CommitteeRolesEnum::ADMINISTRATOR->label());
    });
    
    it('generates an array for Mary-UI options', function () {
        $this->assertEquals([
            ['id' => 'PRESIDENT', 'name' => __('President')],
            ['id' => 'VICE_PRESIDENT', 'name' => __('Vice-President')],
            ['id' => 'TREASURER', 'name' => __('Treasurer')],
            ['id' => 'SECRETARY', 'name' => __('Secretary')],
            ['id' => 'ADMINISTRATOR', 'name' => __('Administrator')],
        ], CommitteeRolesEnum::getOptions());
    });

    it('returns null when value not in enum', function () {
        assertNull(CommitteeRolesEnum::tryFrom('Président'));
        assertNull(CommitteeRolesEnum::tryFrom('Admin'));
        assertNull(CommitteeRolesEnum::tryFrom('president'));
    });

}
)->group('club-settings');