<?php

use OVAC\Guardrails\Support\Auth as AuthHelper;
use OVAC\Guardrails\Tests\Fixtures\User;

it('resolves provider model from guard and finds user by id', function () {
    /** @var \Illuminate\Database\Connection $db */
    $db = app('db');
    $id = $db->table('users')->insertGetId(['name' => 'T']);

    expect(AuthHelper::providerModelClass('web'))
        ->toBe(User::class)
        ->and(AuthHelper::findUserById($id)->id)->toBe($id);
});
