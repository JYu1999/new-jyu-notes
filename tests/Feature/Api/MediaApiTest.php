<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'public']);
        Storage::fake('public');
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeMedia(string $name = 'old.jpg'): Media
    {
        return Media::create([
            'path' => 'uploads/2026/06/'.$name,
            'mime_type' => 'image/jpeg',
            'size' => 123,
            'original_filename' => $name,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/media')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']); // lacks read
        $this->getJson('/api/media')->assertForbidden();
    }

    public function test_index_paginated(): void
    {
        $this->makeMedia('a.jpg');
        $this->makeMedia('b.jpg');
        Sanctum::actingAs($this->user(), ['media:read']);

        $this->getJson('/api/media')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'path', 'url', 'mime_type', 'original_filename']], 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    public function test_upload_requires_create_ability(): void
    {
        Sanctum::actingAs($this->user(), ['media:read']); // lacks create
        $this->post('/api/media', ['file' => UploadedFile::fake()->image('x.jpg', 10, 10)], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_upload_stores_file_and_returns_record(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']);

        $res = $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 12, 12),
        ], ['Accept' => 'application/json'])->assertCreated();

        $res->assertJsonPath('data.original_filename', 'photo.jpg');
        $path = $res->json('data.path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media', ['original_filename' => 'photo.jpg']);
    }

    public function test_upload_rejects_non_media_file(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']);

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_delete_requires_ability_and_removes(): void
    {
        $media = $this->makeMedia();
        Sanctum::actingAs($this->user(), ['media:delete']);

        $this->deleteJson("/api/media/{$media->id}")->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
