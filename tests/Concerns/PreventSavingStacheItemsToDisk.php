<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Statamic\Facades\Path;
use Statamic\Facades\Stache;

trait PreventSavingStacheItemsToDisk
{
    protected ?string $fakeStacheDirectory = null;

    protected function preventSavingStacheItemsToDisk()
    {
        $this->fakeStacheDirectory = Path::tidy($this->fakeStacheDirectory());

        Stache::stores()->each(function ($store) {
            $dir = Path::tidy(fixtures_path());
            $relative = Str::after(Str::after($store->directory(), $dir), '/');
            $store->directory($this->fakeStacheDirectory().'/'.$relative);
        });
    }

    protected function deleteFakeStacheDirectory()
    {
        app('files')->deleteDirectory($this->fakeStacheDirectory());

        mkdir($this->fakeStacheDirectory(), recursive: true);
        touch($this->fakeStacheDirectory().'/.gitkeep');
    }

    protected function fakeStacheDirectory()
    {
        $this->fakeStacheDirectory ??= fixtures_path('dev-null');

        return $this->fakeStacheDirectory;
    }
}
