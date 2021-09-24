<?php

namespace DoubleThreeDigital\GuestEntries\Http\Controllers;

use DoubleThreeDigital\GuestEntries\Events\GuestEntryCreated;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryDeleted;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryUpdated;
use DoubleThreeDigital\GuestEntries\Exceptions\AssetContainerNotFoundSpecified;
use DoubleThreeDigital\GuestEntries\Http\Requests\DestroyRequest;
use DoubleThreeDigital\GuestEntries\Http\Requests\StoreRequest;
use DoubleThreeDigital\GuestEntries\Http\Requests\UpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Assets\Assets;

class GuestEntryController extends Controller
{
    protected $ignoredParameters = ['_token', '_collection', '_id', '_redirect', '_error_redirect', '_request', 'slug', 'published', 'date'];

    public function store(StoreRequest $request)
    {
        if (! $this->honeypotPassed($request)) {
            return $this->withSuccess($request);
        }

        $collection = Collection::find($request->get('_collection'));

        /** @var \Statamic\Entries\Entry $entry */
        $entry = Entry::make()
            ->collection($collection->handle())
            ->published(false);

        if ($request->has('slug')) {
            $entry->slug($request->get('slug'));
        } else {
            $entry->slug(Str::slug($request->get('title')));
        }

        if ($request->has('published')) {
            $entry->published($request->get('published') == '1' || $request->get('published') == 'true' ? true : false);
        }

        foreach (Arr::except($request->all(), $this->ignoredParameters) as $key => $value) {
            /** @var \Statamic\Fields\Field $blueprintField */
            $field = $collection->entryBlueprint()->field($key);

            if ($field && $field->fieldtype() instanceof Assets) {
                $value = $this->uploadFile($key, $field, $request);
            }

            $entry->set($key, $value);
        }

        if ($collection->dated()) {
            $entry->date($request->get('date') ?? now());
        }

        $entry->save();
        $entry->touch();

        event(new GuestEntryCreated($entry));

        return $this->withSuccess($request);
    }

    public function update(UpdateRequest $request)
    {
        if (! $this->honeypotPassed($request)) {
            return $this->withSuccess($request);
        }

        /** @var \Statamic\Entries\Entry $entry */
        $entry = Entry::find($request->get('_id'));

        /** @var array $data */
        $data = $entry->data()->toArray();

        if ($request->has('slug')) {
            $entry->slug($request->get('slug'));
        }

        if ($request->has('published')) {
            $entry->published($request->get('published') == 1 || $request->get('published') == 'true' ? true : false);
        }

        foreach (Arr::except($request->all(), $this->ignoredParameters) as $key => $value) {
            /** @var \Statamic\Fields\Field $blueprintField */
            $field = $entry->blueprint()->field($key);

            if ($field && $field->fieldtype() instanceof Assets) {
                $value = $this->uploadFile($key, $field, $request);
            }

            $data[$key] = $value;
        }

        if ($entry->revisionsEnabled()) {
            /** @var \Statamic\Revisions\Revision $revision */
            $revision = $entry->makeWorkingCopy();
            $revision->id($entry->id());

            $revision->attributes([
                'title' => $entry->get('title'),
                'slug' => $entry->slug(),
                'published' => $entry->published(),
                'data' => $data,
            ]);

            if ($entry->collection()->dated() && $request->has('date')) {
                $revision->date($request->get('date'));
            }

            if ($request->user()) {
                $revision->user($revision->user());
            }

            $revision->message(__('Guest Entry Updated'));
            $revision->action('revision');

            $revision->save();
            $entry->save();
        } else {
            $entry->data($data);

            if ($entry->collection()->dated() && $request->has('date')) {
                $entry->date($request->get('date'));
            }

            $entry->save();
            $entry->touch();
        }

        event(new GuestEntryUpdated($entry));

        return $this->withSuccess($request);
    }

    public function destroy(DestroyRequest $request)
    {
        if (! $this->honeypotPassed($request)) {
            return $this->withSuccess($request);
        }

        $entry = Entry::find($request->get('_id'));

        $entry->delete();

        event(new GuestEntryDeleted($entry));

        return $this->withSuccess($request);
    }

    protected function uploadFile(string $key, Field $field, Request $request)
    {
        if (! isset($field->config()['container'])) {
            throw new AssetContainerNotFoundSpecified("Please specify an asset container on your [{$key}] field, in order for file uploads to work.");
        }

        /** @var \Statamic\Assets\AssetContainer $assetContainer */
        $assetContainer = AssetContainer::findByHandle($field->config()['container']);

        $files = [];
        $uploadedFiles = $request->file($key);

        if (! is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        /** @var \Illuminate\Http\Testing\File $file */
        foreach ($uploadedFiles as $uploadedFile) {
            $path = '/' . $uploadedFile->store(
                isset($field->config()['folder'])
                    ? $field->config()['folder']
                    : $assetContainer->diskPath(),
                $assetContainer->diskHandle()
            );

            $files[] = str_replace($assetContainer->diskPath(), '', $path);
        }

        if (count($files) === 0) {
            return null;
        }

        if (count($files) === 1) {
            return $files[0];
        }

        return $files;
    }

    protected function honeypotPassed(Request $request): ?bool
    {
        $honeypot = config('guest-entries.honeypot');

        if (! $honeypot) {
            return true;
        }

        return empty($request->get($honeypot));
    }

    protected function withSuccess(Request $request, array $data = [])
    {
        if ($request->wantsJson()) {
            $data = array_merge($data, [
                'status'  => 'success',
                'message' => null,
            ]);

            return response()->json($data);
        }

        return $request->_redirect ?
            redirect($request->_redirect)->with($data)
            : back()->with($data);
    }

    protected function withErrors(Request $request, string $errorMessage)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => $errorMessage,
            ]);
        }

        return $request->_error_redirect
            ? redirect($request->_error_redirect)->withErrors($errorMessage, 'guest-entries')
            : back()->withErrors($errorMessage, 'guest-entries');
    }
}
