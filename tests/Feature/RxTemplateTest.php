<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RxTemplateTest extends ApiTestCase
{
    public function test_owner_uploads_template_and_every_device_pulls_it(): void
    {
        Storage::fake(Clinic::RX_TEMPLATE_DISK);
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $cursor = $this->getJson('/api/sync?since=0')->assertJsonPath('settings.rx.image', null)->json('cursor');

        $image = $this->post('/api/clinic/rx-template', ['image' => UploadedFile::fake()->image('rx.png', 600, 850)], ['Accept' => 'application/json'])
            ->assertOk()->json('image');

        $this->getJson("/api/sync?since={$cursor}")->assertJsonPath('settings.rx.image', $image);
        $this->get($image)->assertOk();
        $this->get(parse_url($image, PHP_URL_PATH))->assertForbidden();
    }

    public function test_replacing_template_deletes_old_file_and_changes_url(): void
    {
        Storage::fake(Clinic::RX_TEMPLATE_DISK);
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));

        $first = $this->post('/api/clinic/rx-template', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->json('image');
        $oldPath = $clinic->fresh()->rx_template_path;
        $second = $this->post('/api/clinic/rx-template', ['image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])->json('image');

        $this->assertNotSame($first, $second);
        Storage::disk(Clinic::RX_TEMPLATE_DISK)->assertMissing($oldPath);
        $this->get($first)->assertNotFound();

        $secondPath = $clinic->fresh()->rx_template_path;
        $this->deleteJson('/api/clinic/rx-template')->assertOk()->assertJsonPath('image', null);
        Storage::disk(Clinic::RX_TEMPLATE_DISK)->assertMissing($secondPath);
    }

    public function test_rx_layout_is_saved_and_validated(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $layout = ['paper' => 'A4', 'top' => 60, 'right' => 20, 'bottom' => 30, 'left' => 20,
            'fontScale' => 110, 'showHeader' => false, 'showDiagnosis' => true, 'printImage' => false];

        $this->putJson('/api/clinic/rx', $layout)->assertOk()->assertJsonPath('paper', 'A4')->assertJsonPath('showHeader', false);
        $this->getJson('/api/sync?since=0')->assertJsonPath('settings.rx.top', 60);

        $this->putJson('/api/clinic/rx', ['paper' => 'Letter'] + $layout)->assertJsonValidationErrors('paper');
    }

    public function test_only_the_owner_changes_the_prescription_layout(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic, 'doctor', false));

        $this->post('/api/clinic/rx-template', ['image' => UploadedFile::fake()->image('rx.png')], ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson('/api/clinic/rx-template')->assertForbidden();
    }

    public function test_template_must_be_an_image(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));

        $this->post('/api/clinic/rx-template', ['image' => UploadedFile::fake()->create('rx.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('image');
    }
}
