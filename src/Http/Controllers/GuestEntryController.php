<?php

namespace DoubleThreeDigital\GuestEntries\Http\Controllers;

use Carbon\Carbon;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryCreated;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryDeleted;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryUpdated;
use DoubleThreeDigital\GuestEntries\Exceptions\AssetContainerNotSpecified;
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
use Statamic\Facades\Site as SiteFacade;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Assets\Assets as AssetFieldtype;
use Statamic\Fieldtypes\Date as DateFieldtype;
use Statamic\Sites\Site;

class GuestEntryController extends Controller
{
    protected $ignoredParameters = ['_token', '_collection', '_id', '_redirect', '_error_redirect', '_request', 'slug', 'published'];

    public function store(StoreRequest $request)
    {
        if (! $this->honeypotPassed($request)) {
            return $this->withSuccess($request);
        }

        $collection = Collection::find($request->get('_collection'));

        /** @var \Statamic\Entries\Entry $entry */
        $entry = Entry::make()
            ->collection($collection->handle())
            ->locale($this->guessSiteFromRequest($request))
            ->published(false);

        if ($request->has('slug')) {
            $entry->slug($request->get('slug'));
        } else {
            $entry->slug(Str::slug($request->get('title')));
        }

        if ($collection->dated()) {
            $this->ignoredParameters[] = 'date';
            $entry->date($request->get('date') ?? now());
        }

        if ($request->has('published')) {
            $entry->published($request->get('published') == '1' || $request->get('published') == 'true' ? true : false);
        }

        foreach (Arr::except($request->all(), $this->ignoredParameters) as $key => $value) {
            /** @var \Statamic\Fields\Field $blueprintField */
            $field = $collection->entryBlueprint()->field($key);

            if ($field && $field->fieldtype() instanceof AssetFieldtype) {
                $value = $this->uploadFile($key, $field, $request);
            }

            if ($field && $field->fieldtype() instanceof DateFieldtype) {
                $format = $field->fieldtype()->config(
                    'format',
                    strlen($value) > 10 ? $field->fieldtype()::DEFAULT_DATETIME_FORMAT : $field->fieldtype()::DEFAULT_DATE_FORMAT
                );

                $value = Carbon::parse($value)->format($format);
            }

            $entry->set($key, $value);
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

        if ($entry->collection()->dated()) {
            $this->ignoredParameters[] = 'date';
        }

        if ($request->has('published')) {
            $entry->published($request->get('published') == 1 || $request->get('published') == 'true' ? true : false);
        }

        foreach (Arr::except($request->all(), $this->ignoredParameters) as $key => $value) {
            /** @var \Statamic\Fields\Field $blueprintField */
            $field = $entry->blueprint()->field($key);

            if ($field && $field->fieldtype() instanceof AssetFieldtype) {
                $value = $this->uploadFile($key, $field, $request);
            }

            if ($field && $field->fieldtype() instanceof DateFieldtype) {
                $format = $field->fieldtype()->config(
                    'format',
                    strlen($value) > 10 ? $field->fieldtype()::DEFAULT_DATETIME_FORMAT : $field->fieldtype()::DEFAULT_DATE_FORMAT
                );

                $value = Carbon::parse($value)->format($format);
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
            throw new AssetContainerNotSpecified("Please specify an asset container on your [{$key}] field, in order for file uploads to work.");
        }

        /** @var \Statamic\Assets\AssetContainer $assetContainer */
        $assetContainer = AssetContainer::findByHandle($field->config()['container']);

        $files = [];
        $uploadedFiles = $request->file($key);

        if (! is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        /* @var \Illuminate\Http\Testing\File $file */
        foreach ($uploadedFiles as $uploadedFile) {
            $path = '/' . $uploadedFile->storeAs(
                isset($field->config()['folder'])
                    ? $field->config()['folder']
                    : '',
                now()->timestamp . '-' . $uploadedFile->getClientOriginalName(),
                $assetContainer->diskHandle()
            );

            // Does path start with a '/'? If so, strip it off.
            if (substr($path, 0, 1) === '/') {
                $path = substr($path, 1);
            }

            $files[] = $path;
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

    protected function guessSiteFromRequest($request): Site
    {
        if ($site = $request->get('site')) {
            return SiteFacade::get($site);
        }

        foreach (SiteFacade::all() as $site) {
            if (Str::contains($request->url(), $site->url())) {
                return $site;
            }
        }

        if ($referer = $request->header('referer')) {
            foreach (SiteFacade::all() as $site) {
                if (Str::contains($referer, $site->url())) {
                    return $site;
                }
            }
        }

        return SiteFacade::current();
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
