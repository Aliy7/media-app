<?php

namespace Tests\Feature;

use App\Livewire\MediaUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies that the MediaUploader Livewire component correctly updates its
 * reactive state when broadcast event handlers are triggered. These tests call
 * the PHP handler methods directly — Echo / WebSocket transport is the concern
 * of the end-to-end Soketi verification in task 4.5.
 */
class MediaUploaderBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_started_transitions_to_processing_state(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'pending')
            ->call('onProcessingStarted', [])
            ->assertSet('uploadStatus', 'processing')
            ->assertSet('processingStep', 'starting')
            ->assertSet('progress', 0);
    }

    public function test_step_completed_advances_progress_and_step(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'processing')
            ->call('onStepCompleted', ['step' => 'resize', 'progress' => 33, 'output_path' => 'media/uuid-resized.jpg'])
            ->assertSet('uploadStatus', 'processing')
            ->assertSet('processingStep', 'resize')
            ->assertSet('progress', 33);
    }

    public function test_step_completed_advances_to_thumbnail_step(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'processing')
            ->set('processingStep', 'resize')
            ->set('progress', 33)
            ->call('onStepCompleted', ['step' => 'thumbnail', 'progress' => 66, 'output_path' => 'media/uuid-thumbnail.jpg'])
            ->assertSet('processingStep', 'thumbnail')
            ->assertSet('progress', 66);
    }

    public function test_processing_completed_finalises_to_completed_state(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'processing')
            ->set('progress', 66)
            ->call('onProcessingCompleted', [
                'status'       => 'completed',
                'outputs'      => ['thumbnail' => 'media/uuid-thumb.jpg', 'optimized' => 'media/uuid-opt.jpg'],
                'completed_at' => now()->toIso8601String(),
            ])
            ->assertSet('uploadStatus', 'completed')
            ->assertSet('progress', 100);
    }

    public function test_processing_failed_sets_failure_state(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'processing')
            ->call('onProcessingFailed', [
                'step'      => 'resize',
                'error'     => 'Corrupt image file',
                'failed_at' => now()->toIso8601String(),
            ])
            ->assertSet('uploadStatus', 'failed')
            ->assertSet('failureStep', 'resize')
            ->assertSet('failureError', 'Corrupt image file');
    }

    public function test_reset_form_restores_idle_state(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadedUuid', 'test-uuid')
            ->set('uploadStatus', 'completed')
            ->set('progress', 100)
            ->set('processingStep', 'optimize')
            ->call('resetForm')
            ->assertSet('uploadStatus', 'idle')
            ->assertSet('uploadedUuid', '')
            ->assertSet('progress', 0)
            ->assertSet('processingStep', null);
    }

    public function test_reset_form_clears_failure_fields(): void
    {
        Livewire::test(MediaUploader::class)
            ->set('uploadStatus', 'failed')
            ->set('failureStep', 'resize')
            ->set('failureError', 'Corrupt image')
            ->call('resetForm')
            ->assertSet('uploadStatus', 'idle')
            ->assertSet('failureStep', null)
            ->assertSet('failureError', null);
    }
}
