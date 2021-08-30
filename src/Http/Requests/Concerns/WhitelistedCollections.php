<?php

namespace DoubleThreeDigital\GuestEntries\Http\Requests\Concerns;

trait WhitelistedCollections
{
    public function collectionIsWhitelisted(string $collectionHandle)
    {
        return config("guest-entries.collections.{$collectionHandle}", false);
    }
}
