<?php

namespace App\Livewire;

use App\Models\Media;
use Livewire\Component;

class MediaLibrary extends Component
{
    public function render()
    {
        $mediaItems = Media::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('livewire.media-library', ['mediaItems' => $mediaItems])
            ->layout('layouts.app');
    }
}
