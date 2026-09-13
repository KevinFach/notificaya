<?php

use App\Support\PhoneNormalizer;

it('normalises a national number to E.164', function (?string $input, ?string $expected) {
    expect(PhoneNormalizer::normalize($input))->toBe($expected);
})->with([
    'plain digits' => ['5512345678', '+525512345678'],
    'spaced' => ['55 1234 5678', '+525512345678'],
    'punctuated' => ['(55) 1234-5678', '+525512345678'],
    'leading zeros' => ['0015512345678', '+5215512345678'],
    'already E.164' => ['+525512345678', '+525512345678'],
    'E.164 with spaces' => ['+52 55 1234 5678', '+525512345678'],
    'other country' => ['+1 415 555 0100', '+14155550100'],
    'null' => [null, null],
    'empty' => ['', null],
    'letters only' => ['sin teléfono', null],
    'too short' => ['12345', null],
]);

it('applies the country prefix of the origin', function () {
    expect(PhoneNormalizer::normalize('5512345678', '+34'))->toBe('+345512345678');
});

it('rejects a number that would not fit the FastSMS column', function () {
    expect(PhoneNormalizer::normalize(str_repeat('9', 25)))->toBeNull();
});
