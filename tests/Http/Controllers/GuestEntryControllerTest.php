<?php

namespace DoubleThreeDigital\GuestEntries\Tests\Http\Controllers;

use DoubleThreeDigital\GuestEntries\Events\GuestEntryCreated;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryDeleted;
use DoubleThreeDigital\GuestEntries\Events\GuestEntryUpdated;
use DoubleThreeDigital\GuestEntries\Tests\TestCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class GuestEntryControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(app('stache')->store('entries')->directory());

        $this->app['config']->set('guest-entries.collections', [
            'comments' => true,
            'albums' => true,
        ]);
    }

    /** @test */
    public function can_store_entry()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');
    }

    /** @test */
    public function can_store_entry_where_slug_is_generated_from_title()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is fantastic',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is fantastic');
        $this->assertSame($entry->slug(), 'this-is-fantastic');
    }

    /** @test */
    public function can_store_entry_with_custom_form_request()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                '_request' => FirstCustomStoreRequest::class,
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('description');
    }

    /** @test */
    public function cant_store_entry_if_collection_has_not_been_whitelisted()
    {
        Collection::make('smth')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'smth',
                'title' => 'Whatever',
                'slug' => 'whatever',
            ])
            ->assertForbidden();

        $entry = Entry::all()->last();

        $this->assertNull($entry);
    }

    /** @test */
    public function can_store_entry_and_user_is_redirected()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                '_redirect' => '/bobs-your-uncle',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect('/bobs-your-uncle');

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');
    }

    /** @test */
    public function can_store_entry_and_ensure_ignored_parameters_are_not_saved()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                '_redirect' => '/whatever',
                '_error_redirect' => '/whatever-else',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');

        $this->assertNull($entry->get('_collection'));
        $this->assertNull($entry->get('_redirect'));
        $this->assertNull($entry->get('_error_redirect'));
    }

    /** @test */
    public function can_store_entry_and_ensure_updated_at_is_set()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');
        $this->assertNotNull($entry->get('updated_at'));
    }

    /** @test */
    public function can_store_entry_where_collection_is_date_ordered_and_ensure_date_is_saved()
    {
        Collection::make('comments')->dated(true)->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');

        $this->assertNotNull($entry->get('date'));
    }

    /** @test */
    public function can_store_entry_and_ensure_honeypot_works_if_value_is_empty()
    {
        Config::set('guest-entries.honeypot', 'postcode');

        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great2',
                'postcode' => '',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great2');
    }

    /** @test */
    public function can_store_entry_and_ensure_honeypot_works_if_value_is_not_empty()
    {
        Config::set('guest-entries.honeypot', 'postcode');

        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great3',
                'postcode' => 'A12 34B',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNull($entry);
    }

    /** @test */
    public function can_store_entry_and_ensure_entry_is_unpublished_by_default()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');
        $this->assertFalse($entry->published());
    }

    /** @test */
    public function can_store_entry_and_ensure_published_status_is_updated()
    {
        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
                'published' => '1',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');
        $this->assertTrue($entry->published());
    }

    /** @test */
    public function can_store_entry_and_ensure_events_and_dispatched()
    {
        Event::fake();

        Collection::make('comments')->save();

        $this
            ->post(route('statamic.guest-entries.store'), [
                '_collection' => 'comments',
                'title' => 'This is great',
                'slug' => 'this-is-great',
            ])
            ->assertRedirect();

        $entry = Entry::all()->last();

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'comments');
        $this->assertSame($entry->get('title'), 'This is great');
        $this->assertSame($entry->slug(), 'this-is-great');

        Event::assertDispatchedTimes(GuestEntryCreated::class, 1);
    }

    /** @test */
    public function can_update_entry()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');
    }

    /** @test */
    public function can_update_entry_with_custom_form_request()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                '_request' => FirstCustomUpdateRequest::class,
                'record_label' => 'Unknown',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('record_label');
    }

    /** @test */
    public function cant_update_entry_if_collection_has_not_been_whitelisted()
    {
        Collection::make('hahahahaha')->save();

        Entry::make()
            ->id('hahahahaha-idee')
            ->collection('hahahahaha')
            ->slug('smth')
            ->data([
                'title' => 'Smth',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'hahahahaha',
                '_id' => 'hahahahaha-idee',
                'title' => 'Something',
            ])
            ->assertForbidden();

        $entry = Entry::find('hahahahaha-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'hahahahaha');
        $this->assertSame($entry->get('title'), 'Smth'); // Has not changed
        $this->assertSame($entry->slug(), 'smth');
    }

    /** @test */
    public function can_update_entry_and_user_is_redirected()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                '_redirect' => '/good-good-night',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect('/good-good-night');

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');
    }

    /** @test */
    public function can_update_entry_and_ensure_required_parameters_are_not_saved()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                '_redirect' => '/something',
                '_error_redirect' => '/something-else',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');

        $this->assertNull($entry->get('_collection'));
        $this->assertNull($entry->get('_id'));
        $this->assertNull($entry->get('_redirect'));
        $this->assertNull($entry->get('_error_redirect'));
    }

    /** @test */
    public function can_update_entry_and_ensure_updated_at_is_set()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
                'updated_at' => 12345,
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');

        $this->assertNotNull($entry->get('updated_at'));
        $this->assertNotSame($entry->get('updated_at'), 12345);
    }

    /** @test */
    public function can_update_entry_and_ensure_published_status_is_updated()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
                'published' => '1',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');
        $this->assertTrue($entry->published());
    }

    /** @test */
    public function can_update_entry_and_ensure_events_are_dispatched()
    {
        Event::fake();

        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->post(route('statamic.guest-entries.update'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                'record_label' => 'Unknown',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNotNull($entry);
        $this->assertSame($entry->collectionHandle(), 'albums');
        $this->assertSame($entry->get('title'), 'Allo Mate!');
        $this->assertSame($entry->get('record_label'), 'Unknown');
        $this->assertSame($entry->slug(), 'allo-mate');

        Event::assertDispatchedTimes(GuestEntryUpdated::class, 1);
    }

    /** @test */
    public function can_destroy_entry()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->delete(route('statamic.guest-entries.destroy'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNull($entry);
    }

    /** @test */
    public function cant_destroy_entry_if_collection_has_not_been_whitelisted()
    {
        Collection::make('blahblah')->save();

        Entry::make()
            ->id('arg')
            ->collection('blahblah')
            ->slug('arg')
            ->data([
                'title' => 'Arrrrgg!',
            ])
            ->save();

        $this
            ->delete(route('statamic.guest-entries.destroy'), [
                '_collection' => 'blahblah',
                '_id' => 'arg',
            ])
            ->assertForbidden();

        $entry = Entry::find('arg');

        $this->assertNotNull($entry);
    }

    /** @test */
    public function can_destroy_entry_if_collection_has_not_been_whitelisted_and_user_is_redirected()
    {
        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->delete(route('statamic.guest-entries.destroy'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
                '_redirect' => '/allo-mate',
            ])
            ->assertRedirect('/allo-mate');

        $entry = Entry::find('allo-mate-idee');

        $this->assertNull($entry);
    }

    /** @test */
    public function can_destroy_entry_and_ensure_events_are_dispatched()
    {
        Event::fake();

        Collection::make('albums')->save();

        Entry::make()
            ->id('allo-mate-idee')
            ->collection('albums')
            ->slug('allo-mate')
            ->data([
                'title' => 'Allo Mate!',
                'artist' => 'Guvna B',
            ])
            ->save();

        $this
            ->delete(route('statamic.guest-entries.destroy'), [
                '_collection' => 'albums',
                '_id' => 'allo-mate-idee',
            ])
            ->assertRedirect();

        $entry = Entry::find('allo-mate-idee');

        $this->assertNull($entry);

        Event::assertDispatchedTimes(GuestEntryDeleted::class, 1);
    }
}

class FirstCustomStoreRequest extends FormRequest
{
    public function rules()
    {
        return [
            'description' => ['required', 'string'],
        ];
    }
}

class FirstCustomUpdateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'record_label' => ['required', 'string', 'max:2'],
        ];
    }
}
