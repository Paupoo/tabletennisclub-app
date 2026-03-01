<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ContactReasonEnum;

describe('ContactReasonEnum', function () {

    it('GetLabel function returns the correct label', function () {
        $this->assertEquals(__('Join us'), ContactReasonEnum::JOIN_US->getLabel());
        $this->assertEquals(__('Have a try'), ContactReasonEnum::TRIAL->getLabel());
        $this->assertEquals(__('Info about competitions'), ContactReasonEnum::INFO_INTERCLUBS->getLabel());
        $this->assertEquals(__('Become a supporter'), ContactReasonEnum::BECOME_SUPPORTER->getLabel());
        $this->assertEquals(__('Partnership/Sponsoring'), ContactReasonEnum::PARTNERSHIP->getLabel());
    });

    it('Returns the good string', function () {
        $this->assertEquals(ContactReasonEnum::JOIN_US, ContactReasonEnum::from('JOIN_US'));
        $this->assertEquals(ContactReasonEnum::PARTNERSHIP, ContactReasonEnum::from('PARTNERSHIP'));
        $this->assertEquals(ContactReasonEnum::INFO_INTERCLUBS, ContactReasonEnum::from('INFO_INTERCLUBS'));
        $this->assertEquals(ContactReasonEnum::BECOME_SUPPORTER, ContactReasonEnum::from('BECOME_SUPPORTER'));
        $this->assertEquals(ContactReasonEnum::TRIAL, ContactReasonEnum::from('TRIAL'));
    });

    // Is this test useful?
    it('values() returns the array of values', function () {
        $this->assertEquals(
            array_map(fn ($case) => $case->value, ContactReasonEnum::cases()),
            ContactReasonEnum::values()
        );
    });

    it('Returns null when value not in enum', function () {
        $this->assertNull(ContactReasonEnum::tryFrom('unknown'));
        $this->assertNull(ContactReasonEnum::tryFrom('join_us'));
    });

})->group('contact');
