<?php

namespace App\Livewire;

use App\Models\Media;
use App\Services\MediaUploadService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class MediaLibrary extends Component
{
    use WithPagination;

    /**
     * In-memory overrides for status/progress received via WebSocket before
     * the next full re-render. Keyed by media UUID.
     *
     * @var array<string, array{status: string, progress: int, step: string|null, error: string|null}>
     */
    public array $liveUpdates = [];

    /** asc | desc — persisted in Livewire state across re-renders */
    public string $sortOrder = 'desc';

    /** all | pending | processing | completed | failed */
    public string $statusFilter = 'all';

    /**
     * Called from Alpine + Echo in the blade when a broadcast event arrives.
     * Triggers a Livewire re-render so updated values are applied immediately.
     */
    public function handleMediaUpdate(
        string  $uuid,
        string  $status,
        int     $progress,
        ?string $step,
        ?string $error,
    ): void {
        $this->liveUpdates[$uuid] = compact('status', 'progress', 'step', 'error');
    }

    /**
     * Re-queues a failed media card from the library without re-upload.
     * Ownership is verified — only the authenticated owner can retry their media.
     */
    public function retryMedia(string $uuid): void
    {
        $media = Media::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('status', Media::STATUS_FAILED)
            ->first();

        if (! $media) {
            return;
        }

        app(MediaUploadService::class)->retry($media);

        // Clear any stale live-update state for this card so it re-renders
        // from the fresh DB values immediately.
        unset($this->liveUpdates[$uuid]);
    }

    public function updatedStatusFilter(string $value): void
    {
        if (! in_array($value, ['all', 'pending', 'processing', 'completed', 'failed'], true)) {
            $this->statusFilter = 'all';
        }
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sortOrder = $this->sortOrder === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function render()
    {
        $query = Media::where('user_id', auth()->id());

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $mediaItems = $query->orderBy('created_at', $this->sortOrder)->paginate(12);

        // Group the current page's items into human-readable date buckets.
        $grouped = $mediaItems->getCollection()->groupBy(fn ($m) => $this->dateBucket($m->created_at));

        return view('livewire.media-library', compact('grouped', 'mediaItems'))
            ->layout('layouts.app');
    }

    private function dateBucket(Carbon $date): string
    {
        if ($date->isToday())             return 'Today';
        if ($date->isYesterday())         return 'Yesterday';
        if ($date->gt(now()->subDays(7))) return 'This week';
        if ($date->gt(now()->subDays(30))) return 'This month';
        return $date->format('F Y');
    }
}
