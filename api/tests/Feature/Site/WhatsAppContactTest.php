<?php

use Laravel\Sanctum\Sanctum;

test('the website gets no chat number until the admin sets one', function (): void {
    $this->getJson('/api/public/meta')->assertOk()->assertJsonPath('whatsapp', null);
});

test('a saved number reaches the website as digits and clearing it hides the button again', function (): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->putJson('/api/admin/settings', ['whatsapp_contact_number' => '+971 50-123 4567'])
        ->assertOk()->assertJsonPath('settings.whatsapp_contact_number', '+971 50-123 4567');
    $this->getJson('/api/public/meta')->assertJsonPath('whatsapp', '971501234567');

    $this->putJson('/api/admin/settings', ['whatsapp_contact_number' => ''])->assertOk();
    $this->getJson('/api/public/meta')->assertJsonPath('whatsapp', null);
});

test('settings refuse a number WhatsApp could not open', function (string $number): void {
    Sanctum::actingAs(admin(), ['admin']);

    $this->putJson('/api/admin/settings', ['whatsapp_contact_number' => $number])
        ->assertStatus(422)->assertJsonValidationErrors('whatsapp_contact_number');
})->with(['no country code' => '050 123 4567', 'words' => 'call me', 'too short' => '+971 50', 'a link' => 'https://wa.me/971501234567']);
