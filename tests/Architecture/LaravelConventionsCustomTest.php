<?php

declare(strict_types=1);

namespace tests\Architecture;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Throwable;

arch()
    ->expect('App\Domains\Shared\Enums')
    ->toBeEnums();
arch()
    ->expect('App')
    ->not->toBeEnums()
    ->ignoring('App\Domains\Shared\Enums');

arch()
    ->expect('App\Http\Controllers')
    ->toBeClasses();
arch()
    ->expect('App\Actions')
    ->toBeClasses();
arch()
    ->expect('App\Services')
    ->toBeClasses();
arch()
    ->expect('App\Console\Commands')
    ->toBeClasses();
arch()
    ->expect('App\Policies')
    ->toBeClasses();
arch()
    ->expect('App\Exceptions')
    ->toBeClasses();
arch()
    ->expect('App\Observers')
    ->toBeClasses();

arch()
    ->expect('App\Exceptions')
    ->classes()
    ->toImplement('Throwable')
    ->ignoring('App\Exceptions\Handler');

arch()
    ->expect('App')
    ->not->toImplement(Throwable::class)
    ->ignoring('App\Exceptions');

arch()
    ->expect('App\Http\Middleware')
    ->classes()
    ->toHaveMethod('handle');

arch()
    ->expect('App')
    ->not->toExtend(Model::class)
    ->ignoring('App\Domains');

arch()
    ->expect('App\Http\Requests')
    ->classes()
    ->toHaveSuffix('Request');

arch()
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch()
    ->expect('App\Http\Requests')
    ->toHaveMethod('rules');

arch()
    ->expect('App')
    ->not->toExtend(FormRequest::class)
    ->ignoring('App\Http\Requests');

arch()
    ->expect('App\Console\Commands')
    ->classes()
    ->toHaveSuffix('Command');

arch()
    ->expect('App\Console\Commands')
    ->classes()
    ->toExtend(Command::class);

arch()
    ->expect('App\Console\Commands')
    ->classes()
    ->toHaveMethod('handle');

arch()
    ->expect('App')
    ->not->toExtend(Command::class)
    ->ignoring('App\Console\Commands');

arch()
    ->expect('App\Mail')
    ->classes()
    ->toExtend(Mailable::class);

arch()
    ->expect('App')
    ->not->toExtend(Mailable::class)
    ->ignoring('App\Mail');

// TODO : Implement this contract in Mails
// arch()
//    ->expect('App\Jobs')
//    ->classes()
//    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch()
    ->expect('App\Jobs')
    ->classes()
    ->toHaveMethod('handle');

arch()
    ->expect('App\Listeners')
    ->toHaveMethod('handle');

arch()
    ->expect('App')
    ->not->toExtend(Notification::class)
    ->ignoring('App\Domains');

arch()
    ->expect('App\Providers')
    ->toHaveSuffix('ServiceProvider');

arch()
    ->expect('App\Providers')
    ->toExtend(ServiceProvider::class);

arch()
    ->expect('App')
    ->not->toExtend(ServiceProvider::class)
    ->ignoring('App\Providers');

arch()
    ->expect('App')
    ->not->toHaveSuffix('ServiceProvider')
    ->ignoring('App\Providers');

arch()
    ->expect('App')
    ->not->toHaveSuffix('Controller')
    ->ignoring('App\Http\Controllers');

arch()
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');

// TODO : test lié au problème d'utilisation des Requests dans les Actions
// arch()
//    ->expect('App\Http')
//    ->toOnlyBeUsedIn('App\Http');

arch()
    ->expect([
        'dd',
        'ddd',
        'dump',
        'env',
        'exit',
        'ray',
    ])->not->toBeUsed();

arch()
    ->expect('App\Policies')
    ->classes()
    ->toHaveSuffix('Policy');

arch()
    ->expect('App\Attributes')
    ->classes()
    ->toImplement(ContextualAttribute::class)
    ->toHaveAttribute('Attribute')
    ->toHaveMethod('resolve');
